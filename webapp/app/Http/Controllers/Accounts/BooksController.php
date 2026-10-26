<?php

namespace App\Http\Controllers\Accounts;

use App\Modules\Accounts\Models\AccountGroup;
use App\Modules\Accounts\Models\LedgerAccount;
use App\Modules\Accounts\Models\Voucher;
use App\Modules\Accounts\Services\Books;
use App\Modules\Accounts\Services\ChartOfAccounts;
use App\Modules\Accounts\Services\Statements;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Settings\Models\Branch;
use App\Modules\Trips\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Accounts (blueprint 07 §1): the book itself for the accountant — the
 * overview, day book, trial balance, profit and loss (for the company or one
 * truck, trip or branch), balance sheet, truck profit, each voucher, and the
 * chart of accounts with the ledgers they add (capital, loans, expenses the
 * operations do not know).
 */
class BooksController extends AccountsController
{
    public function index(Request $request, Statements $statements, Books $books, ChartOfAccounts $chart): View
    {
        $chart->ensure();
        [$from, $to, $fy] = $this->period($request);
        $pl = $statements->profitAndLoss($from, $to);
        $tb = $statements->trialBalance($to);
        $balances = $statements->balances($to);

        return view('accounts.index', [
            'from' => $from, 'to' => $to, 'fy' => $fy, 'pl' => $pl, 'tb' => $tb, 'lockedUntil' => $books->lockedUntil(),
            'cash' => round($balances->whereIn('group_code', ['cash_in_hand'])->sum('balance'), 2),
            'bank' => round($balances->whereIn('group_code', ['bank_accounts'])->sum('balance'), 2),
            'receivable' => round($balances->where('group_code', 'sundry_debtors')->sum('balance'), 2),
            'payable' => round(-$balances->where('group_code', 'sundry_creditors')->sum('balance'), 2),
            'withCrew' => round($balances->where('group_code', 'crew_accounts')->sum('balance'), 2),
            'recent' => Voucher::orderByDesc('id')->limit(8)->get(),
        ]);
    }

    public function dayBook(Request $request, Statements $statements): View|StreamedResponse
    {
        [$from, $to, $fy] = $this->period($request);
        $vouchers = $statements->dayBook($from, $to);
        if ($request->query('format') === 'csv') {
            $rows = [];
            foreach ($vouchers as $v) {
                foreach ($v->lines as $l) {
                    $rows[] = [$v->voucher_date->format('d-m-Y'), $v->number, $v->typeLabel(), $v->reference, $v->narration, $l->ledger->name, (float) $l->debit ?: '', (float) $l->credit ?: '', $v->isCancelled() ? __('Cancelled') : ''];
                }
            }

            return $this->csv('Day-book-' . $from->format('Ymd') . '-' . $to->format('Ymd'), [__('Date'), __('Voucher'), __('Type'), __('Reference'), __('Narration'), __('Ledger'), __('Debit'), __('Credit'), __('Status')], $rows);
        }

        return view('accounts.day-book', ['from' => $from, 'to' => $to, 'fy' => $fy, 'vouchers' => $vouchers]);
    }

    public function trialBalance(Request $request, Statements $statements): View|StreamedResponse
    {
        [, $to, $fy] = $this->period($request);
        $tb = $statements->trialBalance($to);
        if ($request->query('format') === 'csv') {
            $rows = [];
            foreach ($tb['groups'] as $group => $ledgers) {
                foreach ($ledgers as $l) {
                    $rows[] = [$group, $l->name, $l->balance > 0 ? $l->balance : '', $l->balance < 0 ? -$l->balance : ''];
                }
            }
            $rows[] = ['', __('Total'), $tb['debit'], $tb['credit']];

            return $this->csv('Trial-balance-' . $to->format('Ymd'), [__('Group'), __('Ledger'), __('Debit'), __('Credit')], $rows);
        }

        return view('accounts.trial-balance', ['to' => $to, 'fy' => $fy, 'tb' => $tb]);
    }

    public function profitLoss(Request $request, Statements $statements): View
    {
        [$from, $to, $fy] = $this->period($request);
        $centres = $request->validate([
            'truck_id' => ['nullable', 'integer', Rule::exists('trucks', 'id')->where('company_id', $this->context->id())],
            'trip_id' => ['nullable', 'integer', Rule::exists('trips', 'id')->where('company_id', $this->context->id())],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $this->context->id())],
        ]);

        return view('accounts.profit-loss', [
            'from' => $from, 'to' => $to, 'fy' => $fy, 'centres' => array_filter($centres), 'pl' => $statements->profitAndLoss($from, $to, array_filter($centres)),
            'trucks' => Truck::orderBy('reg_no')->pluck('reg_no', 'id'), 'branches' => Branch::orderBy('name')->pluck('name', 'id'),
            'trip' => isset($centres['trip_id']) ? Trip::find($centres['trip_id']) : null,
        ]);
    }

    public function balanceSheet(Request $request, Statements $statements): View
    {
        [, $to, $fy] = $this->period($request);

        return view('accounts.balance-sheet', ['to' => $to, 'fy' => $fy, 'bs' => $statements->balanceSheet($to)]);
    }

    /** The truck ledger (09 §3): profit per truck for the period, and one truck month by month. */
    public function trucks(Request $request, Statements $statements): View
    {
        [$from, $to, $fy] = $this->period($request);
        $rows = $statements->truckProfit($from, $to);
        $truck = $request->validate(['truck' => ['nullable', 'integer']])['truck'] ?? null;
        $truck = $truck ? Truck::findOrFail($truck) : null;

        return view('accounts.trucks', [
            'from' => $from, 'to' => $to, 'fy' => $fy, 'rows' => $rows, 'trucks' => Truck::whereIn('id', $rows->pluck('truck_id'))->get()->keyBy('id'),
            'truck' => $truck, 'months' => $truck ? $statements->truckMonths($truck->id, $from->startOfMonth(), $to) : collect(),
        ]);
    }

    public function voucher(Voucher $voucher): View
    {
        $voucher->load(['lines.ledger.group', 'lines.trip:id,trip_number', 'lines.truck:id,reg_no', 'reverses', 'reversedBy', 'author']);

        return view('accounts.voucher', ['voucher' => $voucher, 'source' => self::sourceLink($voucher)]);
    }

    /**
     * The voucher to print and sign (M21): who was paid (or paid us), through
     * which cash or bank ledger, the amount in words, and the signature boxes.
     */
    public function printVoucher(Voucher $voucher, \App\Modules\Invoicing\Services\DocumentData $data): \Illuminate\Http\Response
    {
        $voucher->load(['lines.ledger', 'lines.trip:id,trip_number', 'lines.truck:id,reg_no', 'author:id,name']);
        $money = $voucher->lines->filter(fn ($l) => in_array($l->ledger->kind, ['cash', 'bank'], true));
        $others = $voucher->lines->reject(fn ($l) => in_array($l->ledger->kind, ['cash', 'bank'], true));
        $party = match ($voucher->voucher_type) {
            'payment' => $others->firstWhere(fn ($l) => (float) $l->debit > 0)?->ledger->name,
            'receipt' => $others->firstWhere(fn ($l) => (float) $l->credit > 0)?->ledger->name,
            default => null,
        };
        $amount = $money->isNotEmpty() ? $money->sum(fn ($l) => (float) $l->debit + (float) $l->credit) : (float) $voucher->lines->sum('debit');
        $company = $this->context->company();

        return response(view('documents.voucher', [
            'voucher' => $voucher, 'seller' => $data->letterhead(), 'party' => $party, 'through' => $money->pluck('ledger.name')->unique()->join(', ') ?: null,
            'amount' => round($amount, 2), 'words' => \App\Support\NumberWords::amount(round($amount, 2), $company->currency ?: 'INR'),
            'docTitle' => ['payment' => __('Payment voucher'), 'receipt' => __('Receipt voucher'), 'contra' => __('Contra voucher')][$voucher->voucher_type] ?? __('Journal voucher'),
            'tz' => $company->timezone ?: 'UTC',
        ])->render());
    }

    /** The chart of accounts: groups, ledgers and their balance today; ledgers the accountant adds. */
    public function chart(Statements $statements, ChartOfAccounts $chart): View
    {
        $chart->ensure();
        $groups = AccountGroup::orderBy('sort_order')->get();
        // The tree flattened in reading order, each group with its depth.
        $tree = [];
        $walk = function (?int $parent, int $depth) use (&$walk, &$tree, $groups) {
            foreach ($groups->where('parent_id', $parent) as $group) {
                $tree[] = [$group, $depth];
                $walk($group->id, $depth + 1);
            }
        };
        $walk(null, 0);

        return view('accounts.chart', [
            'groups' => $groups, 'tree' => $tree,
            'balances' => $statements->balances($this->today())->keyBy('id'),
            'ledgers' => LedgerAccount::orderBy('name')->get()->groupBy('account_group_id'),
        ]);
    }

    public function storeLedger(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('ledger_accounts', 'name')->where('company_id', $this->context->id())],
            'account_group_id' => ['required', 'integer', Rule::exists('account_groups', 'id')->where('company_id', $this->context->id())],
            'code' => ['nullable', 'string', 'max:30'],
            'opening_balance' => ['nullable', 'numeric', 'between:-999999999999,999999999999'],
            'opening_side' => ['nullable', Rule::in(['dr', 'cr'])],
        ]);
        $opening = round(abs((float) ($data['opening_balance'] ?? 0)), 3) * (($data['opening_side'] ?? 'dr') === 'cr' ? -1 : 1);
        LedgerAccount::create(['name' => $data['name'], 'account_group_id' => $data['account_group_id'], 'code' => $data['code'] ?? null, 'kind' => 'general',
            'opening_balance' => $opening, 'opening_date' => $opening ? \App\Support\FiscalYear::containing($this->today(), (int) ($this->context->company()->fy_start_month ?: 4))->start : null]);

        return back()->with('status', __('Ledger “:name” added.', ['name' => $data['name']]));
    }

    /** Where a voucher came from, as a link for the screens. */
    public static function sourceLink(Voucher $voucher): ?array
    {
        if (! $voucher->source_type || ! $voucher->source_id) {
            return null;
        }
        $id = $voucher->source_id;
        $trip = fn (string $model) => ($tripId = $model::whereKey($id)->value('trip_id')) ? ['label' => __('The trip'), 'url' => route('trips.show', $tripId)] : null;

        return match ($voucher->source_type) {
            'invoice'      => ['label' => __('The bill'), 'url' => route('invoices.show', $id)],
            'receipt'      => ['label' => __('The receipt'), 'url' => route('receipts.show', $id)],
            'trip_advance' => $trip(\App\Modules\Trips\Models\TripAdvance::class),
            'road_event'   => $trip(\App\Modules\Road\Models\RoadEvent::class),
            'settlement'   => $trip(\App\Modules\Trips\Models\TripSettlement::class),
            'office_entry' => $trip(\App\Modules\Trips\Models\TripOfficeEntry::class),
            'hire_challan' => $trip(\App\Modules\Trips\Models\HireChallan::class),
            'broker_commission' => $trip(\App\Modules\Trips\Models\BrokerCommission::class),
            'hire_line'    => ($tripId = \App\Modules\Trips\Models\HireChallan::whereKey(\App\Modules\Trips\Models\HireChallanLine::whereKey($id)->value('hire_challan_id'))->value('trip_id'))
                ? ['label' => __('The trip'), 'url' => route('trips.show', $tripId)] : null,
            'service_job'  => ($truck = \App\Modules\Fleet\Models\ServiceJob::whereKey($id)->value('truck_id')) ? ['label' => __('The truck'), 'url' => route('trucks.show', $truck)] : null,
            default        => null,
        };
    }
}
