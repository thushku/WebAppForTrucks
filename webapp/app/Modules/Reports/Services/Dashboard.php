<?php

namespace App\Modules\Reports\Services;

use App\Modules\Accounts\Services\Statements;
use App\Modules\Documents\Services\Compliance;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Road\Models\DrivingShift;
use App\Modules\Road\Models\RoadEvent;
use App\Modules\Road\Services\Approvals;
use App\Modules\Road\Services\DrivingHours;
use App\Modules\Road\Services\EventTypes;
use App\Modules\Settings\Services\CompanySettings;
use App\Modules\Trips\Models\Consignment;
use App\Modules\Trips\Models\Trip;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The owner's control room (blueprint 04 §3.1), in numbers from the trips
 * and the book: trucks by state, trips running and waiting to settle, crew
 * entries for the office, the month's profit against last month, the
 * alerts (papers expiring, a driver long at the wheel, a phone gone quiet,
 * flagged entries), what was spent on the road today, six months of income
 * and cost, and the work queues from LR to cash.
 */
class Dashboard
{
    public function __construct(
        private CompanyContext $context,
        private CompanySettings $settings,
        private Statements $statements,
        private LiveMap $map,
        private Compliance $compliance,
        private Approvals $approvals,
        private DrivingHours $hours,
    ) {}

    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->context->company()->timezone ?: 'UTC')->startOfDay();
    }

    /** @return array{trucks: array, trips: array, approvals: array} */
    public function counts(): array
    {
        $trucks = Truck::inBranch()->where('status', '!=', 'sold')->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n);
        $trips = Trip::inBranch()->whereIn('status', ['assigned', 'accepted', 'running', 'ended'])->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n);

        return [
            'trucks' => ['total' => $trucks->sum(), 'on_trip' => $trucks['on_trip'] ?? 0, 'idle' => $trucks['idle'] ?? 0, 'workshop' => ($trucks['workshop'] ?? 0) + ($trucks['breakdown'] ?? 0)],
            'trips' => ['running' => $trips['running'] ?? 0, 'planned' => ($trips['assigned'] ?? 0) + ($trips['accepted'] ?? 0), 'to_settle' => $trips['ended'] ?? 0],
            'approvals' => $this->approvals->counts(true) + ['waiting_check' => 0, 'disputed' => 0, 'queried' => 0, 'waiting_2nd' => 0],
        ];
    }

    /** This month's profit from the book, and the change against last month. */
    public function profit(): array
    {
        $today = $this->today();
        $centres = $this->branchCentres();
        $now = $this->statements->profitAndLoss($today->startOfMonth(), $today, $centres);
        // Last month up to the same day, so a part month is compared with a part month.
        $lastFrom = $today->startOfMonth()->subMonthNoOverflow();
        $then = $this->statements->profitAndLoss($lastFrom, $lastFrom->addDays(min($today->day, $lastFrom->daysInMonth) - 1), $centres);
        $change = abs($then['profit']) >= 1 ? round(($now['profit'] - $then['profit']) / abs($then['profit']) * 100) : null;

        return ['profit' => $now['profit'], 'income' => $now['total_income'], 'expense' => $now['total_expense'], 'last' => $then['profit'], 'change' => $change];
    }

    /**
     * What needs a look now, most urgent first.
     *
     * @return array<int, array{level: string, text: string, url: ?string}>
     */
    public function alerts(?\Illuminate\Support\Collection $positions = null): array
    {
        $alerts = [];
        $docs = $this->compliance->counts();
        if ($docs['expired']) {
            $alerts[] = ['level' => 'critical', 'text' => trans_choice(':count paper has expired|:count papers have expired', $docs['expired']), 'url' => route('documents.index', ['show' => 'expired'])];
        }
        if ($docs['d7']) {
            $alerts[] = ['level' => 'warning', 'text' => trans_choice(':count paper expires within 7 days|:count papers expire within 7 days', $docs['d7']), 'url' => route('documents.index', ['show' => 'd7'])];
        }

        // A driver at the wheel longer than the company's limit without a break.
        $limit = (int) $this->settings->get('max_continuous_driving_minutes');
        $open = DrivingShift::with(['person:id,name', 'trip:id,trip_number,status'])->whereNull('ended_at')->whereHas('trip', fn ($q) => $q->where('status', 'running'))->get();
        foreach ($open as $shift) {
            $minutes = $this->hours->minutes($shift, $this->hours->rests($shift->trip));
            if ($limit > 0 && $minutes > $limit) {
                $alerts[] = ['level' => 'serious', 'text' => __(':name has driven :h h :m min without a break (:trip)', ['name' => $shift->person->name, 'h' => intdiv($minutes, 60), 'm' => $minutes % 60, 'trip' => $shift->trip->trip_number]),
                    'url' => route('trips.show', $shift->trip_id)];
            }
        }

        foreach (($positions ?? $this->map->positions())->where('quiet', true) as $p) {
            $alerts[] = ['level' => 'warning', 'text' => $p['minutes'] === null
                ? __('No GPS yet from :vehicle (:trip)', ['vehicle' => $p['vehicle'], 'trip' => $p['trip']])
                : __('No GPS from :vehicle for :n min (:trip)', ['vehicle' => $p['vehicle'], 'n' => $p['minutes'], 'trip' => $p['trip']]), 'url' => $p['url']];
        }

        // Truck EMIs overdue or due within three days (M35).
        $emis = app(\App\Modules\Fleet\Services\TruckLoans::class)->due($this->today(), 3);
        if ($late = $emis->where('overdue', true)->count()) {
            $alerts[] = ['level' => 'critical', 'text' => trans_choice(':count truck EMI is overdue|:count truck EMIs are overdue', $late), 'url' => route('trucks.emis')];
        }
        foreach ($emis->where('overdue', false) as $emi) {
            $alerts[] = ['level' => 'warning', 'text' => __('EMI of :truck, :amount, due :date', ['truck' => $emi['truck']->reg_no, 'amount' => \App\Support\Money::company($emi['amount']), 'date' => $emi['due_on']->format('d M')]), 'url' => route('trucks.emis')];
        }

        // Leave waiting for a decision (M40), for those who decide it.
        if (auth()->user()?->can('leave.approve')) {
            $asked = \App\Modules\People\Models\LeaveRequest::where('status', 'waiting')->whereIn('person_id', \App\Modules\People\Models\Person::inBranch()->select('id'))->count();
            if ($asked) {
                $alerts[] = ['level' => 'warning', 'text' => trans_choice(':count leave request waits for you|:count leave requests wait for you', $asked), 'url' => route('people.leave')];
            }
        }

        // Fitted tyres near or past the km expected of them (M38).
        $worn = app(\App\Modules\Fleet\Services\Tyres::class)->nearEnd();
        if ($worn->isNotEmpty()) {
            $alerts[] = ['level' => $worn->contains(fn ($r) => $r['used'] >= 1) ? 'serious' : 'warning',
                'text' => trans_choice(':count tyre is near the end of its expected km|:count tyres are near the end of their expected km', $worn->count()), 'url' => route('trucks.tyres')];
        }

        // Odometer and GPS apart on a trip of the last 30 days (M34).
        $apart = Trip::inBranch()->where('km_check', 'differs')->where('ended_at', '>=', now()->subDays(30))->count();
        if ($apart) {
            $alerts[] = ['level' => 'warning', 'text' => trans_choice(':count trip of the last 30 days has the odometer and GPS apart|:count trips of the last 30 days have the odometer and GPS apart', $apart),
                'url' => route('trips.index', ['status' => 'all', 'km' => 'differs'])];
        }

        $flagged = RoadEvent::whereIn('status', RoadEvent::OPEN)->whereNotNull('flags')->where('flags', '!=', '[]')->count();
        if ($flagged) {
            $alerts[] = ['level' => 'warning', 'text' => trans_choice(':count crew entry has a flag to look at|:count crew entries have flags to look at', $flagged), 'url' => route('approvals.index')];
        }

        // The plan's storage nearly or quite full (M17).
        if ($share = app(\App\Platform\Billing\Limits::class)->storageAlert($this->context->company())) {
            $alerts[] = ['level' => $share >= 100 ? 'serious' : 'warning', 'text' => __('Storage is :p% full: new documents stop at 100%. Remove old files or move to a bigger plan.', ['p' => $share]), 'url' => route('billing.index')];
        }

        $order = ['critical' => 0, 'serious' => 1, 'warning' => 2];
        usort($alerts, fn ($a, $b) => $order[$a['level']] <=> $order[$b['level']]);

        return $alerts;
    }

    /** Money spent on the road today, by category (approved or still waiting; rejected left out). */
    public function spendingToday(): array
    {
        $from = $this->today()->setTimezone('UTC');

        return RoadEvent::whereHas('trip', fn ($t) => $t->inBranch())->whereNotNull('amount')->where('type', '!=', 'money_received')->whereNotIn('status', ['rejected', 'cancelled'])
            ->whereBetween('event_at', [$from, $from->addDay()])
            ->get(['type', 'amount'])
            ->groupBy(fn ($e) => EventTypes::label($e->type))
            ->map(fn ($set) => round($set->sum(fn ($e) => (float) $e->amount), 2))
            ->sortDesc()->all();
    }

    /**
     * Income and cost per month from the book, the last six months
     * including this one.
     *
     * @return array<int, array{month: string, label: string, income: float, cost: float}>
     */
    public function months(int $count = 6): array
    {
        $today = $this->today();
        $from = $today->startOfMonth()->subMonthsNoOverflow($count - 1);
        $rows = DB::table('voucher_lines as l')->join('vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->join('ledger_accounts as a', 'a.id', '=', 'l.ledger_account_id')->join('account_groups as g', 'g.id', '=', 'a.account_group_id')
            ->where('l.company_id', $this->context->id())->whereIn('g.nature', ['income', 'expense'])
            ->whereBetween('v.voucher_date', [$from->toDateString(), $today->toDateString()])
            ->when($this->branchCentres()['branch_id'] ?? null, fn ($q, $ids) => $q->whereIn('l.branch_id', $ids))
            ->groupByRaw("date_format(v.voucher_date, '%Y-%m')")
            ->selectRaw("date_format(v.voucher_date, '%Y-%m') as month,
                sum(case when g.nature = 'income' then l.credit - l.debit else 0 end) as income,
                sum(case when g.nature = 'expense' then l.debit - l.credit else 0 end) as cost")
            ->get()->keyBy('month');

        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $m = $from->addMonthsNoOverflow($i);
            $row = $rows[$m->format('Y-m')] ?? null;
            $out[] = ['month' => $m->format('Y-m'), 'label' => $m->translatedFormat('M'), 'income' => round((float) ($row->income ?? 0), 2), 'cost' => round((float) ($row->cost ?? 0), 2)];
        }

        return $out;
    }

    /** The book's cost centres for the branch filter (M22): the branch(es) shown, or none for the whole company. */
    private function branchCentres(): array
    {
        $ids = app(\App\Platform\Tenancy\BranchContext::class)->ids();

        return $ids === null ? [] : ['branch_id' => $ids];
    }

    /** From LR to cash (09 §2.3): how many wait at each step. */
    public function queues(): array
    {
        $today = $this->today()->toDateString();

        return [
            'pod_pending' => Consignment::whereHas('trip', fn ($t) => $t->inBranch())->where('status', 'delivered')->whereNull('pod_received_at')->count(),
            'ready_to_bill' => Consignment::whereHas('trip', fn ($t) => $t->inBranch())->whereNotNull('lr_number')->whereNull('invoice_id')->where('status', 'pod_received')->where('payment_type', 'to_be_billed')->count(),
            'not_submitted' => Invoice::inBranch()->whereIn('status', ['finalised'])->where('doc_type', '!=', 'credit_note')->whereNull('submitted_on')->whereNull('sent_at')->count(),
            'overdue' => Invoice::inBranch()->whereIn('status', Invoice::OPEN_STATUSES)->where('doc_type', '!=', 'credit_note')->where('balance_due', '>', 0)->where('due_date', '<', $today)->count(),
            'overdue_amount' => round((float) Invoice::inBranch()->whereIn('status', Invoice::OPEN_STATUSES)->where('doc_type', '!=', 'credit_note')->where('due_date', '<', $today)->sum('balance_due'), 2),
        ];
    }
}
