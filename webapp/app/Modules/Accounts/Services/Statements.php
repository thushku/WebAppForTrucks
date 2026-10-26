<?php

namespace App\Modules\Accounts\Services;

use App\Modules\Accounts\Models\LedgerAccount;
use App\Modules\Accounts\Models\Voucher;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything read from the book (blueprint 07 §1, 09 §0): a ledger's running
 * statement, the trial balance, profit and loss (for the company, a truck, a
 * trip or a branch), the balance sheet, the day book and the truck profit
 * table. Cancelled vouchers and their reversals both stay in the book, so
 * every figure is the plain sum of the lines.
 *
 * Amounts are signed debit-positive throughout; screens turn the sign into
 * words ("ACC will give you ₹4,200", "You will give Ravi ₹750").
 */
class Statements
{
    /**
     * A ledger between two dates: the opening balance, each line with the
     * balance after it, the closing balance.
     *
     * @return array{opening: float, rows: Collection, debit: float, credit: float, closing: float}
     */
    public function ledger(LedgerAccount $ledger, CarbonImmutable $from, CarbonImmutable $to, ?string $bucket = null, array $centres = []): array
    {
        $base = fn () => $this->lines($centres)->where('l.ledger_account_id', $ledger->id)->when($bucket, fn ($q) => $q->where('l.bucket', $bucket));
        $before = (float) $base()->where('v.voucher_date', '<', $from->toDateString())->sum(DB::raw('l.debit - l.credit'));
        $opening = round(($bucket || $centres ? 0 : (float) $ledger->opening_balance) + $before, 3);

        $running = $opening;
        $rows = $base()->whereBetween('v.voucher_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('v.voucher_date')->orderBy('v.id')->orderBy('l.sort_order')
            ->get(['v.id as voucher_id', 'v.number', 'v.voucher_type', 'v.voucher_date', 'v.document_date', 'v.reference', 'v.narration', 'v.status',
                'v.source_type', 'v.source_id', 'l.debit', 'l.credit', 'l.bucket', 'l.trip_id', 'l.truck_id', 'l.note'])
            ->map(function ($row) use (&$running) {
                $running = round($running + (float) $row->debit - (float) $row->credit, 3);
                $row->balance = $running;

                return $row;
            });

        $debit = round((float) $rows->sum('debit'), 3);
        $credit = round((float) $rows->sum('credit'), 3);

        return ['opening' => $opening, 'rows' => $rows, 'debit' => $debit, 'credit' => $credit, 'closing' => round($opening + $debit - $credit, 3)];
    }

    /**
     * Closing balance of every ledger on a date, with its group.
     *
     * @return Collection<int, object{id: int, name: string, group_id: int, group: string, nature: string, kind: string, balance: float}>
     */
    public function balances(CarbonImmutable $asOf, array $centres = [], ?CarbonImmutable $from = null): Collection
    {
        $sums = $this->lines($centres)->where('v.voucher_date', '<=', $asOf->toDateString())
            ->when($from, fn ($q) => $q->where('v.voucher_date', '>=', $from->toDateString()))
            ->groupBy('l.ledger_account_id')->selectRaw('l.ledger_account_id, sum(l.debit) as debit, sum(l.credit) as credit')->get()->keyBy('ledger_account_id');

        $withOpening = ! $centres && ! $from;

        return LedgerAccount::query()->join('account_groups as g', 'g.id', '=', 'ledger_accounts.account_group_id')
            ->orderBy('g.sort_order')->orderBy('ledger_accounts.name')
            ->get(['ledger_accounts.id', 'ledger_accounts.name', 'ledger_accounts.kind', 'ledger_accounts.opening_balance', 'ledger_accounts.party_id', 'ledger_accounts.person_id',
                'g.id as group_id', 'g.name as group', 'g.nature', 'g.code as group_code'])
            ->map(function ($ledger) use ($sums, $withOpening) {
                $sum = $sums[$ledger->id] ?? null;
                $ledger->debit = round((float) ($sum->debit ?? 0), 3);
                $ledger->credit = round((float) ($sum->credit ?? 0), 3);
                $ledger->balance = round(($withOpening ? (float) $ledger->opening_balance : 0) + $ledger->debit - $ledger->credit, 3);

                return $ledger;
            })
            ->filter(fn ($l) => abs($l->balance) >= 0.0005 || $l->debit > 0 || $l->credit > 0)
            ->values();
    }

    /**
     * The trial balance on a date: every ledger with a balance, grouped, and
     * the two totals — equal, or the book is broken.
     */
    public function trialBalance(CarbonImmutable $asOf): array
    {
        $ledgers = $this->balances($asOf)->filter(fn ($l) => abs($l->balance) >= 0.0005);
        $debit = round($ledgers->where('balance', '>', 0)->sum('balance'), 3);
        $credit = round(-$ledgers->where('balance', '<', 0)->sum('balance'), 3);

        return ['groups' => $ledgers->groupBy('group'), 'debit' => $debit, 'credit' => $credit, 'balanced' => abs($debit - $credit) < 0.005];
    }

    /**
     * Profit and loss for a period, for the company or one trip, truck or
     * branch (the cost centres on the lines).
     *
     * @param array{trip_id?: ?int, truck_id?: ?int, branch_id?: ?int} $centres
     */
    public function profitAndLoss(CarbonImmutable $from, CarbonImmutable $to, array $centres = []): array
    {
        $ledgers = $this->balances($to, $centres, $from)->whereIn('nature', ['income', 'expense']);
        $income = $ledgers->where('nature', 'income')->map(fn ($l) => tap($l, fn ($l) => $l->amount = -$l->balance));
        $expense = $ledgers->where('nature', 'expense')->map(fn ($l) => tap($l, fn ($l) => $l->amount = $l->balance));
        $totalIncome = round($income->sum('amount'), 3);
        $totalExpense = round($expense->sum('amount'), 3);

        return [
            'income' => $income->groupBy('group'), 'expense' => $expense->groupBy('group'),
            'total_income' => $totalIncome, 'total_expense' => $totalExpense, 'profit' => round($totalIncome - $totalExpense, 3),
        ];
    }

    /** The balance sheet on a date; the year's profit so far sits with the capital. */
    public function balanceSheet(CarbonImmutable $asOf): array
    {
        $ledgers = $this->balances($asOf)->filter(fn ($l) => abs($l->balance) >= 0.0005);
        $profit = round(-$ledgers->whereIn('nature', ['income', 'expense'])->sum('balance'), 3);
        $assets = $ledgers->where('nature', 'asset')->map(fn ($l) => tap($l, fn ($l) => $l->amount = $l->balance));
        $liabilities = $ledgers->whereIn('nature', ['liability', 'equity'])->map(fn ($l) => tap($l, fn ($l) => $l->amount = -$l->balance));

        return [
            'assets' => $assets->groupBy('group'), 'liabilities' => $liabilities->groupBy('group'), 'profit' => $profit,
            'total_assets' => round($assets->sum('amount'), 3), 'total_liabilities' => round($liabilities->sum('amount') + $profit, 3),
        ];
    }

    /** Every voucher of a period with its lines, in order (the day book). */
    public function dayBook(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Voucher::with(['lines.ledger:id,name'])->whereBetween('voucher_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('voucher_date')->orderBy('id')->get();
    }

    /**
     * Income, running cost, maintenance and profit per truck for a period —
     * the truck ledger (09 §3) read from the cost centre on every line.
     *
     * @return Collection<int, object>
     */
    public function truckProfit(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $this->lines([])->join('ledger_accounts as a', 'a.id', '=', 'l.ledger_account_id')->join('account_groups as g', 'g.id', '=', 'a.account_group_id')
            ->whereNotNull('l.truck_id')->whereIn('g.nature', ['income', 'expense'])
            ->whereBetween('v.voucher_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('l.truck_id')
            ->selectRaw("l.truck_id,
                sum(case when g.nature = 'income' then l.credit - l.debit else 0 end) as income,
                sum(case when g.nature = 'expense' and g.code <> 'maintenance' then l.debit - l.credit else 0 end) as running,
                sum(case when g.nature = 'expense' and g.code = 'maintenance' then l.debit - l.credit else 0 end) as maintenance,
                count(distinct l.trip_id) as trips")
            ->get()
            ->map(function ($row) {
                foreach (['income', 'running', 'maintenance'] as $k) {
                    $row->{$k} = round((float) $row->{$k}, 3);
                }
                $row->profit = round($row->income - $row->running - $row->maintenance, 3);

                return $row;
            });
    }

    /** One truck month by month: income, running cost, maintenance, profit. */
    public function truckMonths(int $truckId, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $month = "date_format(v.voucher_date, '%Y-%m')";

        return $this->lines(['truck_id' => $truckId])->join('ledger_accounts as a', 'a.id', '=', 'l.ledger_account_id')->join('account_groups as g', 'g.id', '=', 'a.account_group_id')
            ->whereIn('g.nature', ['income', 'expense'])->whereBetween('v.voucher_date', [$from->toDateString(), $to->toDateString()])
            ->groupByRaw($month)->orderByRaw($month)
            ->selectRaw("{$month} as month,
                sum(case when g.nature = 'income' then l.credit - l.debit else 0 end) as income,
                sum(case when g.nature = 'expense' and g.code <> 'maintenance' then l.debit - l.credit else 0 end) as running,
                sum(case when g.nature = 'expense' and g.code = 'maintenance' then l.debit - l.credit else 0 end) as maintenance")
            ->get()
            ->map(fn ($r) => tap($r, fn ($r) => $r->profit = round((float) $r->income - (float) $r->running - (float) $r->maintenance, 3)));
    }

    /**
     * A crew member's buckets (09 §1.1): trip cash still with them, earnings
     * owed to them, advances against pay still to be recovered (M27), and the
     * net — the whole ledger. Debit-positive: a positive trip cash or advance
     * is the company's money in their hands.
     *
     * @return array{trip_cash: float, earnings: float, loan: float, net: float}
     */
    public function crewBuckets(LedgerAccount $ledger, ?CarbonImmutable $asOf = null): array
    {
        $sums = $this->lines([])->where('l.ledger_account_id', $ledger->id)->when($asOf, fn ($q) => $q->where('v.voucher_date', '<=', $asOf->toDateString()))
            ->groupBy('l.bucket')->selectRaw('l.bucket, sum(l.debit - l.credit) as balance')->pluck('balance', 'bucket');
        $tripCash = round((float) ($sums['trip_cash'] ?? 0), 3);
        $earnings = round((float) ($sums['earnings'] ?? 0) + (float) ($sums[''] ?? 0) + (float) $ledger->opening_balance, 3);
        $loan = round((float) ($sums['loan'] ?? 0), 3);

        return ['trip_cash' => $tripCash, 'earnings' => $earnings, 'loan' => $loan, 'net' => round($tripCash + $earnings + $loan, 3)];
    }

    /** The trip's profit from the book: income less every cost tagged with it. */
    public function tripProfit(int $tripId): array
    {
        $pl = $this->profitAndLoss(CarbonImmutable::parse('1900-01-01'), CarbonImmutable::parse('2999-12-31'), ['trip_id' => $tripId]);

        return ['income' => $pl['total_income'], 'expense' => $pl['total_expense'], 'profit' => $pl['profit']];
    }

    // ---- Helpers ---------------------------------------------------------------------

    /** Voucher lines of this company joined to their voucher, narrowed to the cost centres asked for. */
    private function lines(array $centres): Builder
    {
        $company = app(\App\Platform\Tenancy\CompanyContext::class)->id();

        return DB::table('voucher_lines as l')->join('vouchers as v', 'v.id', '=', 'l.voucher_id')->where('l.company_id', $company)
            ->when($centres['trip_id'] ?? null, fn ($q, $id) => $q->where('l.trip_id', $id))
            ->when($centres['truck_id'] ?? null, fn ($q, $id) => $q->where('l.truck_id', $id))
            // One branch, or the several a branch-limited person works in (M22).
            ->when($centres['branch_id'] ?? null, fn ($q, $id) => is_array($id) ? $q->whereIn('l.branch_id', $id) : $q->where('l.branch_id', $id));
    }
}
