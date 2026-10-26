<?php

namespace App\Modules\Fleet\Services;

use App\Models\Account;
use App\Modules\Accounts\Models\LedgerAccount;
use App\Modules\Accounts\Services\Books;
use App\Modules\Accounts\Services\ChartOfAccounts;
use App\Modules\Accounts\Services\OpeningBalances;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Fleet\Models\TruckEmi;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Truck loans and their EMIs (M35). A truck with an EMI and its day has a
 * loan: its own liability ledger ("Truck loan — KL 07 AB 1234 (HDFC)"),
 * opened with what was owed at the start of the financial year. Each EMI
 * falls due on its day of the month (the last day in a short month) from the
 * first EMI — or from when the company started here — to the loan's end.
 *
 * Paying one is a payment voucher: the principal off the loan, the interest
 * (by default the loan's rate on what is still owed) an expense of the truck,
 * from cash or the bank. An EMI is paid once; a wrong one is reversed in the
 * books like any voucher.
 */
class TruckLoans
{
    public function __construct(private Books $books, private ChartOfAccounts $chart, private CompanyContext $context, private AuditRecorder $audit) {}

    public function hasLoan(Truck $truck): bool
    {
        return (float) $truck->emi_amount > 0 && $truck->emi_day;
    }

    /** The EMI date of a month: its day, or the month's last day when shorter. */
    public function dueIn(Truck $truck, CarbonImmutable $month): CarbonImmutable
    {
        $first = $month->startOfMonth();

        return $first->setDay(min((int) $truck->emi_day, $first->daysInMonth));
    }

    /**
     * Every EMI date from the first one tracked up to $until.
     *
     * @return CarbonImmutable[]
     */
    public function dueDates(Truck $truck, CarbonImmutable $until): array
    {
        if (! $this->hasLoan($truck)) {
            return [];
        }
        $company = $this->context->company();
        $started = CarbonImmutable::parse($company->created_at)->setTimezone($company->timezone ?: 'UTC')->startOfDay();
        $from = $truck->loan_starts_on && CarbonImmutable::parse($truck->loan_starts_on)->greaterThan($started) ? CarbonImmutable::parse($truck->loan_starts_on) : $started;
        $end = $truck->loan_ends_on ? CarbonImmutable::parse($truck->loan_ends_on)->endOfMonth() : null;

        $dates = [];
        for ($month = $from->startOfMonth(); $month <= $until && count($dates) < 600; $month = $month->addMonthNoOverflow()) {
            $due = $this->dueIn($truck, $month);
            if ($due->lessThan($from) || $due->greaterThan($until) || ($end && $due->greaterThan($end))) {
                continue;
            }
            $dates[] = $due;
        }

        return $dates;
    }

    /**
     * Where a truck's loan stands today.
     *
     * @return array{next: ?CarbonImmutable, overdue: CarbonImmutable[], outstanding: ?float, interest: ?float, last: ?TruckEmi, open: CarbonImmutable[]}
     */
    public function status(Truck $truck, CarbonImmutable $today): array
    {
        $paid = TruckEmi::where('truck_id', $truck->id)->pluck('due_on')->map(fn ($d) => CarbonImmutable::parse($d)->toDateString())->all();
        $open = array_values(array_filter($this->dueDates($truck, $today->addMonthNoOverflow()), fn ($d) => ! in_array($d->toDateString(), $paid, true)));
        $overdue = array_values(array_filter($open, fn ($d) => $d->lessThan($today)));

        return [
            'next' => $open[0] ?? null, 'overdue' => $overdue, 'open' => $open,
            'outstanding' => $this->outstanding($truck), 'interest' => $this->interestFor($truck),
            'last' => TruckEmi::where('truck_id', $truck->id)->orderByDesc('due_on')->first(),
        ];
    }

    /** What is still owed on the loan, from its ledger; null before the loan is set up in the books. */
    public function outstanding(Truck $truck): ?float
    {
        $ledger = LedgerAccount::where('system_key', 'truck_loan:' . $truck->id)->first();
        if (! $ledger) {
            return null;
        }
        $moved = (float) DB::table('voucher_lines as l')->join('vouchers as v', 'v.id', '=', 'l.voucher_id')->where('l.ledger_account_id', $ledger->id)
            ->where('v.status', '!=', 'cancelled')->sum(DB::raw('l.debit - l.credit'));

        return round(-((float) $ledger->opening_balance + $moved), 2);
    }

    /** This month's interest at the loan's rate on what is still owed (the rest of the EMI is principal). */
    public function interestFor(Truck $truck): ?float
    {
        $owed = $this->outstanding($truck);
        if (! $truck->loan_rate || ! $owed || $owed <= 0) {
            return null;
        }

        return round(min((float) $truck->emi_amount, $owed * (float) $truck->loan_rate / 1200), 2);
    }

    /** What was owed at the start of this financial year: the loan ledger's opening balance. */
    public function setOpening(Truck $truck, float $owed): void
    {
        $ledger = $this->chart->truckLoan($truck);
        app(OpeningBalances::class)->save([$ledger->id => -round($owed, 2)]);
    }

    public function rules(): array
    {
        $company = $this->context->id();
        $today = CarbonImmutable::now($this->context->company()->timezone ?: 'UTC')->toDateString();

        return [
            'due_on' => ['required', 'date_format:Y-m-d'],
            'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:' . $today],
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'interest' => ['nullable', 'numeric', 'min:0', 'lte:amount'],
            'mode' => ['required', Rule::in(['cash', 'bank'])],
            'bank_account_id' => ['nullable', 'integer', Rule::exists('company_bank_accounts', 'id')->where('company_id', $company)],
            'reference' => ['nullable', 'string', 'max:60', 'required_if:mode,bank'],
        ];
    }

    /** @param array{due_on: string, paid_on: string, amount: mixed, interest?: mixed, mode: string, bank_account_id?: ?int, reference?: ?string} $data */
    public function pay(Truck $truck, array $data, Account $by): TruckEmi
    {
        if (! $this->hasLoan($truck)) {
            throw ValidationException::withMessages(['truck' => __('Give the truck its EMI and the day it falls due first (edit the truck).')]);
        }
        $due = CarbonImmutable::parse($data['due_on']);
        if (! collect($this->dueDates($truck, $due->addDay()))->contains(fn ($d) => $d->isSameDay($due))) {
            throw ValidationException::withMessages(['due_on' => __('No EMI of this truck falls due on :date.', ['date' => $due->format('d M Y')])]);
        }
        if (TruckEmi::where('truck_id', $truck->id)->whereDate('due_on', $due->toDateString())->exists()) {
            throw ValidationException::withMessages(['due_on' => __('The EMI of :month is recorded already.', ['month' => $due->translatedFormat('F Y')])]);
        }
        $amount = round((float) $data['amount'], 2);
        $interest = round((float) ($data['interest'] ?? $this->interestFor($truck) ?? 0), 2);

        return DB::transaction(function () use ($truck, $data, $by, $due, $amount, $interest) {
            $emi = TruckEmi::create(['truck_id' => $truck->id, 'due_on' => $due->toDateString(), 'paid_on' => $data['paid_on'], 'amount' => $amount,
                'principal' => round($amount - $interest, 2), 'interest' => $interest, 'mode' => $data['mode'], 'bank_account_id' => $data['bank_account_id'] ?? null,
                'reference' => ($data['reference'] ?? null) ?: null, 'created_by' => $by->id]);
            $centres = ['truck_id' => $truck->id, 'branch_id' => $truck->branch_id];
            $voucher = $this->books->post([
                'type' => 'payment', 'series' => 'payment_voucher', 'date' => $data['paid_on'], 'reference' => $emi->reference ?? __('EMI :month', ['month' => $due->format('M Y')]),
                'narration' => __('EMI of :truck for :month', ['truck' => $truck->reg_no, 'month' => $due->translatedFormat('F Y')]) . ($truck->financier ? ' — ' . $truck->financier : ''),
                'source_type' => 'truck_emi', 'source_id' => $emi->id, 'created_by' => $by->id,
            ], [
                ['ledger' => $this->chart->truckLoan($truck), 'debit' => $emi->principal] + $centres,
                ['ledger' => $this->chart->ledger('loan_interest'), 'debit' => $interest] + $centres,
                ['ledger' => $this->chart->money($data['mode'], $data['bank_account_id'] ?? null), 'credit' => $amount],
            ]);
            $emi->forceFill(['voucher_id' => $voucher?->id])->saveQuietly();
            $this->audit->record('truck.emi_paid', "EMI of {$truck->reg_no} for " . $due->format('M Y') . ' paid: ' . number_format($amount, 2) . ' (interest ' . number_format($interest, 2) . ')',
                'truck', (string) $truck->id, $truck->reg_no, severity: 'notable');

            return $emi;
        });
    }

    /**
     * The EMIs overdue or falling due within $days, for the trucks shown
     * (the dashboard, the summary, the EMIs page's top).
     *
     * @return Collection<int, array{truck: Truck, due_on: CarbonImmutable, amount: float, overdue: bool}>
     */
    public function due(CarbonImmutable $today, int $days = 7): Collection
    {
        return Truck::inBranch()->where('status', '!=', 'sold')->where('emi_amount', '>', 0)->whereNotNull('emi_day')->orderBy('reg_no')->get()
            ->flatMap(fn (Truck $t) => collect($this->status($t, $today)['open'])->filter(fn ($d) => $d->lessThanOrEqualTo($today->addDays($days)))
                ->map(fn ($d) => ['truck' => $t, 'due_on' => $d, 'amount' => (float) $t->emi_amount, 'overdue' => $d->lessThan($today)]))
            ->sortBy(fn ($r) => $r['due_on']->timestamp)->values();
    }
}
