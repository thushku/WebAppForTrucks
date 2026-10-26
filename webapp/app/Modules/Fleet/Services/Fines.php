<?php

namespace App\Modules\Fleet\Services;

use App\Models\Account;
use App\Modules\Accounts\Services\Books;
use App\Modules\Accounts\Services\ChartOfAccounts;
use App\Modules\Fleet\Models\Fine;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Road\Models\DrivingShift;
use App\Modules\Trips\Models\Trip;
use App\Modules\Trips\Models\TripCrew;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Notifications\Notices;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Fines and e-challans (M48). A fine is recorded against the truck at the
 * time on the challan: the trip on the road then and its driver — the
 * driving shift covering that moment, else the trip's driver — are found by
 * themselves. Paid from the office, it is booked as the truck's fine (the
 * same ledger as fines the crew pay on the road); paid by the crew on the
 * road it is already in the trip's expenses, so nothing new is booked. A
 * fine the driver bears comes off their next pay run as a "fine" deduction.
 * Black points (UAE) are added up per driver over 12 months.
 */
class Fines
{
    /** An unpaid fine this old without a due date is shown as late (e-challans go to court after a while). */
    public const LATE_DAYS = 60;

    public function __construct(private CompanyContext $context, private Books $books, private ChartOfAccounts $chart, private AuditRecorder $audit, private Notices $notices) {}

    public function rules(?Fine $fine = null): array
    {
        $companyId = $this->context->id();

        return [
            'truck_id' => ['nullable', 'required_without:person_id', 'integer', Rule::exists('trucks', 'id')->where('company_id', $companyId)],
            'person_id' => ['nullable', 'integer', Rule::exists('people', 'id')->where('company_id', $companyId)],
            'challan_no' => ['nullable', 'string', 'max:40', Rule::unique('fines', 'challan_no')->where('company_id', $companyId)->ignore($fine?->id)],
            'authority' => ['nullable', 'string', 'max:80'],
            'offence' => ['required', 'string', 'max:150'],
            'place' => ['nullable', 'string', 'max:150'],
            'issued_at' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'points' => ['nullable', 'integer', 'min:0', 'max:' . Fine::POINTS_LIMIT],
            'due_on' => ['nullable', 'date_format:Y-m-d'],
            'bears' => ['required', Rule::in(array_keys(Fine::BEARS))],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function payRules(): array
    {
        return [
            'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:' . $this->today()->toDateString()],
            'mode' => ['required', Rule::in(array_keys(Fine::PAID_MODES))],
            'bank_account_id' => ['nullable', 'integer', Rule::exists('company_bank_accounts', 'id')->where('company_id', $this->context->id())],
            'reference' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * The trip on the road then and who was driving: the driving shift that
     * covers the moment, else the trip's driver.
     *
     * @return array{trip: ?Trip, person_id: ?int}
     */
    public function whoWasDriving(int $truckId, CarbonImmutable $at): array
    {
        $trip = Trip::where('truck_id', $truckId)->whereNotNull('started_at')->where('started_at', '<=', $at)
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>=', $at))->orderByDesc('started_at')->first(['id', 'truck_id', 'branch_id', 'trip_number']);
        if (! $trip) {
            return ['trip' => null, 'person_id' => null];
        }
        $shift = DrivingShift::where('trip_id', $trip->id)->where('started_at', '<=', $at)
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>=', $at))->orderByDesc('started_at')->value('person_id');

        return ['trip' => $trip, 'person_id' => $shift ?? TripCrew::where('trip_id', $trip->id)->where('role', 'driver')->value('person_id')];
    }

    public function record(array $data, Account $by): Fine
    {
        $fine = DB::transaction(function () use ($data, $by) {
            $fields = $this->fields($data);
            $fine = Fine::create($fields + ['status' => 'unpaid', 'created_by' => $by->id]);
            $this->audit->record('fine.recorded', "Fine {$fine->label} of {$fine->amount} recorded" . ($fine->bears === 'driver' ? ', to be taken from the driver’s pay' : ''), 'fine', (string) $fine->id, $fine->label);

            return $fine;
        });
        if ($fine->bears === 'driver') {
            $this->notices->fineToRecover($fine);
        }

        return $fine;
    }

    /** Corrected while it is not paid and not in a pay run. */
    public function update(Fine $fine, array $data): Fine
    {
        if (! $fine->isOpen() || $fine->payslip_id) {
            throw ValidationException::withMessages(['fine' => __('A paid fine, or one in a pay run, is not changed.')]);
        }
        $was = $fine->bears;
        DB::transaction(fn () => $fine->update($this->fields($data)));
        if ($fine->bears === 'driver' && $was !== 'driver') {
            $this->notices->fineToRecover($fine);
        }

        return $fine;
    }

    /** Paid from the office (booked as the truck's fine), or by the crew on the road (already in the trip's expenses). */
    public function pay(Fine $fine, array $data, Account $by): Fine
    {
        if (! $fine->isOpen()) {
            throw ValidationException::withMessages(['fine' => __('This fine is not waiting to be paid.')]);
        }

        return DB::transaction(function () use ($fine, $data, $by) {
            $fine = Fine::with(['truck:id,reg_no,branch_id', 'trip:id,branch_id'])->whereKey($fine->id)->lockForUpdate()->firstOrFail();
            $voucher = null;
            if ($data['mode'] !== 'road') {
                $centres = ['truck_id' => $fine->truck_id, 'trip_id' => $fine->trip_id, 'branch_id' => $fine->trip?->branch_id ?? $fine->truck?->branch_id];
                $voucher = $this->books->post([
                    'type' => 'expense', 'date' => $data['paid_on'], 'reference' => mb_substr($data['reference'] ?? '', 0, 60) ?: $fine->challan_no,
                    'narration' => __('Fine :challan, :offence', ['challan' => $fine->challan_no ?: '—', 'offence' => $fine->offence]) . ($fine->truck ? ', ' . $fine->truck->reg_no : ''),
                    'source_type' => 'fine', 'source_id' => $fine->id, 'created_by' => $by->id,
                ], [
                    ['ledger' => $this->chart->expense('fine_receipt'), 'debit' => (float) $fine->amount] + $centres,
                    ['ledger' => $this->chart->money($data['mode'], $data['bank_account_id'] ?? null), 'credit' => (float) $fine->amount],
                ]);
            }
            $fine->forceFill(['status' => 'paid', 'paid_on' => $data['paid_on'], 'paid_mode' => $data['mode'], 'bank_account_id' => $data['mode'] === 'bank' ? ($data['bank_account_id'] ?? null) : null,
                'paid_reference' => isset($data['reference']) ? mb_substr($data['reference'], 0, 60) : null, 'voucher_id' => $voucher?->id])->save();
            $this->audit->record('fine.paid', "Fine {$fine->label} paid ({$data['mode']})", 'fine', (string) $fine->id, $fine->label, severity: 'notable');

            return $fine;
        });
    }

    /** Challenged with the authority: not paid, not taken from anyone's pay, until decided. */
    public function dispute(Fine $fine, string $note): void
    {
        if ($fine->status !== 'unpaid' || $fine->payslip_id) {
            throw ValidationException::withMessages(['fine' => __('Only an unpaid fine not in a pay run is disputed.')]);
        }
        if (mb_strlen(trim($note)) < 3) {
            throw ValidationException::withMessages(['note' => __('Say why it is disputed.')]);
        }
        $fine->update(['status' => 'disputed', 'status_note' => mb_substr(trim($note), 0, 255)]);
    }

    /** Back to unpaid after a dispute that was lost. */
    public function reopen(Fine $fine): void
    {
        if ($fine->status !== 'disputed') {
            throw ValidationException::withMessages(['fine' => __('This fine is not disputed.')]);
        }
        $fine->update(['status' => 'unpaid']);
    }

    /** Cancelled by the authority or entered by mistake; a paid one has its payment reversed (the money came back). */
    public function cancel(Fine $fine, string $reason): void
    {
        if ($fine->status === 'cancelled') {
            return;
        }
        if (mb_strlen(trim($reason)) < 3) {
            throw ValidationException::withMessages(['reason' => __('Say why it is cancelled.')]);
        }
        if ($fine->payslip_id) {
            $run = Payslip::with('run')->find($fine->payslip_id)?->run;
            throw ValidationException::withMessages(['fine' => $run && $run->status === 'draft'
                ? __('It is in the draft pay run of :period: take it out of that payslip first.', ['period' => $run->periodLabel()])
                : __('It was taken from the driver’s pay of :period: give it back as an addition in the next pay run, then cancel it.', ['period' => $run?->periodLabel() ?? '—'])]);
        }
        DB::transaction(function () use ($fine, $reason) {
            if ($fine->voucher_id) {
                $this->books->reverseSource('fine', $fine->id, $reason);
            }
            $fine->update(['status' => 'cancelled', 'status_note' => mb_substr(trim($reason), 0, 255)]);
            $this->audit->record('fine.cancelled', "Fine {$fine->label} cancelled", 'fine', (string) $fine->id, $fine->label, reason: $reason, severity: 'notable');
        });
    }

    /**
     * The fines to take back from this person's pay: borne by them, not
     * disputed or cancelled, issued by the end of the pay period, and not in
     * any payslip yet. Oldest first.
     *
     * @return Collection<int, Fine>
     */
    public function toRecover(int $personId, CarbonImmutable $upTo): Collection
    {
        return Fine::where('person_id', $personId)->where('bears', 'driver')->whereIn('status', ['unpaid', 'paid'])->whereNull('payslip_id')
            ->where('issued_at', '<', $upTo->addDay()->startOfDay())->orderBy('issued_at')->get();
    }

    /** The line a fine makes on a payslip. */
    public function payslipLine(Fine $fine): array
    {
        return ['kind' => 'deduction', 'type' => 'fine', 'fine_id' => $fine->id, 'amount' => round((float) $fine->amount, 2),
            'label' => __('Fine :challan, :date', ['challan' => $fine->challan_no ?: $fine->offence, 'date' => $fine->issued_at->format('d M Y')])];
    }

    /**
     * Black points of the last 12 months, per driver, highest first.
     *
     * @return Collection<int, object{person_id: int, points: int, fines: int}>
     */
    public function points(?int $personId = null): Collection
    {
        return Fine::where('points', '>', 0)->where('status', '!=', 'cancelled')->whereNotNull('person_id')->where('issued_at', '>=', now()->subYear())
            ->when($personId, fn ($q) => $q->where('person_id', $personId))
            ->selectRaw('person_id, sum(points) as points, count(*) as fines')->groupBy('person_id')->orderByDesc('points')->get()
            ->map(fn ($r) => (object) ['person_id' => (int) $r->person_id, 'points' => (int) $r->points, 'fines' => (int) $r->fines]);
    }

    /** Unpaid fines, and those past their due date (or old) among them. */
    public function open(): array
    {
        $today = $this->today();
        $unpaid = Fine::where('status', 'unpaid')->get(['id', 'amount', 'due_on', 'issued_at']);
        $late = $unpaid->filter(fn (Fine $f) => $f->due_on ? $f->due_on->toDateString() < $today->toDateString() : $f->issued_at->lt($today->subDays(self::LATE_DAYS)));

        return ['count' => $unpaid->count(), 'amount' => round((float) $unpaid->sum('amount'), 2), 'late' => $late->count()];
    }

    public function isLate(Fine $fine): bool
    {
        $today = $this->today();

        return $fine->status === 'unpaid' && ($fine->due_on ? $fine->due_on->toDateString() < $today->toDateString() : $fine->issued_at->lt($today->subDays(self::LATE_DAYS)));
    }

    /** The fields from the form: the time read in the company's zone, the trip and driver found from the truck. */
    private function fields(array $data): array
    {
        $tz = $this->context->company()->timezone ?: 'UTC';
        $at = CarbonImmutable::parse($data['issued_at'], $tz)->utc();
        if ($at->isFuture()) {
            throw ValidationException::withMessages(['issued_at' => __('The fine’s time cannot be in the future.')]);
        }
        $found = ! empty($data['truck_id']) ? $this->whoWasDriving((int) $data['truck_id'], $at) : ['trip' => null, 'person_id' => null];
        $personId = ! empty($data['person_id']) ? (int) $data['person_id'] : $found['person_id'];
        if ($data['bears'] === 'driver' && ! $personId) {
            throw ValidationException::withMessages(['person_id' => __('Nobody was found driving then: choose whose fine it is.')]);
        }

        return [
            'truck_id' => $data['truck_id'] ?? null, 'person_id' => $personId, 'trip_id' => $found['trip']?->id,
            'challan_no' => isset($data['challan_no']) && trim($data['challan_no']) !== '' ? strtoupper(trim($data['challan_no'])) : null,
            'authority' => $data['authority'] ?? null, 'offence' => trim($data['offence']), 'place' => $data['place'] ?? null,
            'issued_at' => $at, 'amount' => round((float) $data['amount'], 2), 'points' => (int) ($data['points'] ?? 0),
            'due_on' => $data['due_on'] ?? null, 'bears' => $data['bears'], 'notes' => $data['notes'] ?? null,
        ];
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->context->company()->timezone ?: 'UTC')->startOfDay();
    }
}
