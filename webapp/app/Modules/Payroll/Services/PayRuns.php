<?php

namespace App\Modules\Payroll\Services;

use App\Models\Account;
use App\Modules\Accounts\Models\LedgerAccount;
use App\Modules\Accounts\Services\Statements;
use App\Modules\Payroll\Models\PayRun;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\People\Models\Person;
use App\Platform\Audit\AuditRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The pay run's life (13-parity #16, #24): made as a draft with a payslip for
 * everyone in scope who has a pay set; extra lines added and the base worked
 * out again while it is a draft; approved — each payslip's pay posted to the
 * person's earnings (BookKeeper); each payslip marked paid with its payment;
 * cancelled while nothing is paid (the postings reversed). Every step is one
 * transaction and is in the audit log.
 */
class PayRuns
{
    public function __construct(private PayCalculator $calculator, private AuditRecorder $audit, private Statements $statements, private \App\Platform\Notifications\Notices $notices, private \App\Modules\Fleet\Services\Fines $fines) {}

    /**
     * People already paid (or in another live run) for any day of the period
     * are left out, so nobody is paid twice for a day.
     *
     * @return array{run: PayRun, skipped: array<int, string>}
     */
    public function create(CarbonImmutable $from, CarbonImmutable $to, string $scope, Account $by, ?string $notes = null): array
    {
        if ($to->lt($from)) {
            throw ValidationException::withMessages(['period_to' => __('The period ends before it starts.')]);
        }
        if ($from->diffInDays($to) > 92) {
            throw ValidationException::withMessages(['period_to' => __('A pay run covers at most three months.')]);
        }

        return DB::transaction(function () use ($from, $to, $scope, $by, $notes) {
            $run = PayRun::create(['period_from' => $from->toDateString(), 'period_to' => $to->toDateString(), 'scope' => $scope, 'status' => 'draft', 'notes' => $notes, 'created_by' => $by->id]);
            $taken = Payslip::whereHas('run', fn ($q) => $q->where('status', '!=', 'cancelled')->where('id', '!=', $run->id)
                ->where('period_from', '<=', $to->toDateString())->where('period_to', '>=', $from->toDateString()))->pluck('person_id')->flip();

            $skipped = [];
            foreach ($this->people($scope, $from) as $person) {
                if (isset($taken[$person->id])) {
                    $skipped[] = __(':name is already in another pay run for these days.', ['name' => $person->name]);

                    continue;
                }
                $base = $this->calculator->base($person, $from, $to);
                $slip = Payslip::create(['pay_run_id' => $run->id, 'person_id' => $person->id, 'pay_basis' => $base['basis'], 'units' => $base['units'], 'rate' => $base['rate'],
                    'base_pay' => $base['base'], 'lines' => [], 'additions' => 0, 'deductions' => 0, 'net' => $base['base'], 'status' => 'pending']);
                $this->addFines($slip, $to);
            }
            $this->totals($run);
            $this->audit->record('payroll.run_created', "Pay run {$run->periodLabel()} made for {$run->people} people", 'pay_run', (string) $run->id, $run->label, severity: 'notable');

            return ['run' => $run, 'skipped' => $skipped];
        });
    }

    /** Work every base pay out again from the latest trips and rates (the extra lines stay). */
    public function recalculate(PayRun $run): void
    {
        $this->assertDraft($run);
        DB::transaction(function () use ($run) {
            foreach ($run->payslips()->with('person')->lockForUpdate()->get() as $slip) {
                $base = $this->calculator->base($slip->person, CarbonImmutable::parse($run->period_from->toDateString()), CarbonImmutable::parse($run->period_to->toDateString()));
                $slip->fill(['pay_basis' => $base['basis'], 'units' => $base['units'], 'rate' => $base['rate'], 'base_pay' => $base['base']]);
                $this->net($slip)->save();
                $this->addFines($slip, CarbonImmutable::parse($run->period_to->toDateString()));
            }
            $this->totals($run);
        });
    }

    /** Add a bonus, an allowance, a recovery… to a draft payslip. */
    public function addLine(Payslip $slip, string $kind, string $type, string $label, float $amount): void
    {
        $this->assertDraft($slip->run);
        $types = $kind === 'addition' ? Payslip::ADDITIONS : Payslip::DEDUCTIONS;
        if (! isset($types[$type]) || $amount <= 0) {
            throw ValidationException::withMessages(['amount' => __('Choose what it is and an amount above zero.')]);
        }
        DB::transaction(function () use ($slip, $kind, $type, $label, $amount) {
            $slip = Payslip::whereKey($slip->id)->lockForUpdate()->firstOrFail();
            $lines = $slip->lines ?? [];
            $lines[] = ['kind' => $kind, 'type' => $type, 'label' => trim($label) ?: __($kind === 'addition' ? Payslip::ADDITIONS[$type] : Payslip::DEDUCTIONS[$type]), 'amount' => round($amount, 2)];
            $slip->lines = $lines;
            if ($this->net($slip)->net < 0) {
                throw ValidationException::withMessages(['amount' => __('The deductions would be more than the pay.')]);
            }
            $slip->save();
            $this->totals($slip->run);
        });
    }

    public function removeLine(Payslip $slip, int $index): void
    {
        $this->assertDraft($slip->run);
        DB::transaction(function () use ($slip, $index) {
            $slip = Payslip::whereKey($slip->id)->lockForUpdate()->firstOrFail();
            $lines = $slip->lines ?? [];
            // A fine taken out of the payslip waits for the next pay run.
            if ($fineId = $lines[$index]['fine_id'] ?? null) {
                \App\Modules\Fleet\Models\Fine::whereKey($fineId)->where('payslip_id', $slip->id)->update(['payslip_id' => null]);
            }
            unset($lines[$index]);
            $slip->lines = array_values($lines);
            $this->net($slip)->save();
            $this->totals($slip->run);
        });
    }

    /** Leave one person out of this run (they can go in another). */
    public function removeSlip(Payslip $slip): void
    {
        $this->assertDraft($slip->run);
        DB::transaction(function () use ($slip) {
            $run = $slip->run;
            \App\Modules\Fleet\Models\Fine::where('payslip_id', $slip->id)->update(['payslip_id' => null]);
            $slip->delete();
            $this->totals($run);
        });
    }

    /** From here the pay is owed to each person; the run itself no longer changes. */
    public function approve(PayRun $run, Account $by): void
    {
        $this->assertDraft($run);
        if ($run->payslips()->count() === 0) {
            throw ValidationException::withMessages(['run' => __('There is nobody in this pay run.')]);
        }
        DB::transaction(function () use ($run, $by) {
            $run->forceFill(['status' => 'approved', 'approved_by' => $by->id, 'approved_at' => now()])->save();
            $this->audit->record('payroll.run_approved', "Pay run {$run->periodLabel()} approved: {$run->net_total} to pay", 'pay_run', (string) $run->id, $run->label, severity: 'notable');
        });
    }

    /**
     * Paid in cash, UPI or by bank. With $withDues the payment also clears
     * what else the person is owed (bata from settled trips): their whole
     * earnings balance, when it is more than the payslip.
     */
    public function pay(Payslip $slip, string $mode, CarbonImmutable $on, ?string $reference, bool $withDues, Account $by): Payslip
    {
        if ($slip->run->status !== 'approved' || $slip->status !== 'pending') {
            throw ValidationException::withMessages(['payslip' => __('Only a pending payslip of an approved pay run can be paid.')]);
        }
        if (! isset(Payslip::PAY_MODES[$mode])) {
            throw ValidationException::withMessages(['mode' => __('Choose how it was paid.')]);
        }

        $paid = DB::transaction(function () use ($slip, $mode, $on, $reference, $withDues, $by) {
            $slip = Payslip::with(['run', 'person'])->whereKey($slip->id)->lockForUpdate()->firstOrFail();
            $amount = (float) $slip->net;
            if ($withDues) {
                $amount = max($amount, $this->owed($slip->person));
            }
            $slip->forceFill(['status' => 'paid', 'paid_amount' => round($amount, 2), 'paid_mode' => $mode, 'paid_reference' => $reference ? mb_substr($reference, 0, 60) : null,
                'paid_at' => $on->setTimeFrom(now()), 'paid_by' => $by->id])->save();
            $this->audit->record('payroll.paid', "Paid {$slip->person->name} {$slip->paid_amount} ({$mode}) for {$slip->run->periodLabel()}", 'payslip', (string) $slip->id, $slip->person->name, severity: 'notable');

            $run = $slip->run;
            $run->forceFill(['paid_total' => round((float) $run->payslips()->where('status', 'paid')->sum('paid_amount'), 2)]);
            if (! $run->payslips()->where('status', 'pending')->where('net', '>', 0)->exists()) {
                $run->status = 'paid';
            }
            $run->save();

            return $slip;
        });
        $this->notices->payslipPaid($paid);

        return $paid;
    }

    /** A draft goes away; an approved run with nothing paid has its postings reversed. */
    public function cancel(PayRun $run, string $reason): void
    {
        if (! in_array($run->status, ['draft', 'approved'], true)) {
            throw ValidationException::withMessages(['run' => __('A paid or cancelled pay run cannot be cancelled.')]);
        }
        if ($run->payslips()->where('status', 'paid')->exists()) {
            throw ValidationException::withMessages(['run' => __('Some payslips are paid already. Undo those payments first.')]);
        }
        DB::transaction(function () use ($run, $reason) {
            $run->payslips()->get()->each(fn (Payslip $s) => $s->forceFill(['status' => 'cancelled'])->save());
            // Its fines wait for the next pay run.
            \App\Modules\Fleet\Models\Fine::whereIn('payslip_id', $run->payslips()->select('id'))->update(['payslip_id' => null]);
            $run->forceFill(['status' => 'cancelled', 'cancel_reason' => mb_substr($reason, 0, 255)])->save();
            $this->audit->record('payroll.run_cancelled', "Pay run {$run->periodLabel()} cancelled", 'pay_run', (string) $run->id, $run->label, reason: $reason, severity: 'notable');
        });
    }

    /** What the company owes the person today in their earnings (bata, unpaid pay); 0 when they owe the company. */
    public function owed(Person $person): float
    {
        $ledger = LedgerAccount::where('person_id', $person->id)->first();

        return $ledger ? round(max(0, -$this->statements->crewBuckets($ledger)['earnings']), 2) : 0.0;
    }

    /** People in scope with a pay set (a salary or a rate), working during the period. */
    public function people(string $scope, CarbonImmutable $from): Collection
    {
        return Person::query()->when($scope === 'crew', fn ($q) => $q->whereIn('type', ['driver', 'helper']))
            ->where(fn ($q) => $q->where('status', '!=', 'left')->orWhere('leaving_date', '>=', $from->toDateString()))
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('pay_basis', 'monthly')->where('monthly_salary', '>', 0))
                ->orWhere(fn ($q) => $q->where('pay_basis', 'per_trip')->where('per_trip_rate', '>', 0))
                ->orWhere(fn ($q) => $q->where('pay_basis', 'per_km')->where('per_km_rate', '>', 0)))
            ->orderBy('name')->get();
    }

    /** People in scope without a salary or rate: listed so the office can set one. */
    public function withoutPay(string $scope): Collection
    {
        $with = $this->people($scope, CarbonImmutable::today())->pluck('id');

        return Person::query()->when($scope === 'crew', fn ($q) => $q->whereIn('type', ['driver', 'helper']))->where('status', '!=', 'left')
            ->whereNotIn('id', $with)->orderBy('name')->get(['id', 'name', 'type']);
    }

    /**
     * The driver's fines not yet taken back (M48) go on the payslip as
     * "fine" deductions, oldest first, as long as the pay covers them; the
     * rest wait for the next pay run.
     */
    private function addFines(Payslip $slip, CarbonImmutable $upTo): void
    {
        $fines = $this->fines->toRecover($slip->person_id, $upTo);
        if ($fines->isEmpty()) {
            return;
        }
        $lines = $slip->lines ?? [];
        foreach ($fines as $fine) {
            $line = $this->fines->payslipLine($fine);
            $slip->lines = [...$lines, $line];
            if ($this->net($slip)->net < 0) {
                continue;
            }
            $lines[] = $line;
            $fine->forceFill(['payslip_id' => $slip->id])->save();
        }
        $slip->lines = $lines;
        $this->net($slip)->save();
    }

    private function net(Payslip $slip): Payslip
    {
        $lines = collect($slip->lines ?? []);
        $slip->additions = round($lines->where('kind', 'addition')->sum('amount'), 2);
        $slip->deductions = round($lines->where('kind', 'deduction')->sum('amount'), 2);
        $slip->net = round((float) $slip->base_pay + (float) $slip->additions - (float) $slip->deductions, 2);

        return $slip;
    }

    private function totals(PayRun $run): void
    {
        $slips = Payslip::where('pay_run_id', $run->id)->get(['base_pay', 'additions', 'deductions', 'net']);
        $run->forceFill([
            'people' => $slips->count(),
            'gross_total' => round($slips->sum(fn ($s) => (float) $s->base_pay + (float) $s->additions), 2),
            'deductions_total' => round($slips->sum(fn ($s) => (float) $s->deductions), 2),
            'net_total' => round($slips->sum(fn ($s) => (float) $s->net), 2),
        ])->save();
    }

    private function assertDraft(PayRun $run): void
    {
        if ($run->status !== 'draft') {
            throw ValidationException::withMessages(['run' => __('This pay run is approved; it no longer changes. Cancel it to start again.')]);
        }
    }
}
