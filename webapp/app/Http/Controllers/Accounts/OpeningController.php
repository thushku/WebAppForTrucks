<?php

namespace App\Http\Controllers\Accounts;

use App\Modules\Accounts\Models\LedgerAccount;
use App\Modules\Accounts\Services\ChartOfAccounts;
use App\Modules\Accounts\Services\OpeningBalances;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Accounts → Opening balances (M19): type them per ledger, or bring them from Tally or Excel as a CSV. */
class OpeningController extends AccountsController
{
    public function edit(ChartOfAccounts $chart): View
    {
        $chart->ensure();
        $difference = $chart->ledger('opening_difference');
        $ledgers = LedgerAccount::query()->join('account_groups as g', 'g.id', '=', 'ledger_accounts.account_group_id')
            ->where('ledger_accounts.id', '!=', $difference->id)->where('ledger_accounts.active', true)->whereIn('g.nature', OpeningBalances::NATURES)
            ->orderBy('g.sort_order')->orderBy('ledger_accounts.name')
            ->get(['ledger_accounts.id', 'ledger_accounts.name', 'ledger_accounts.kind', 'ledger_accounts.opening_balance', 'g.name as group', 'g.nature']);

        return view('accounts.opening', ['ledgers' => $ledgers->groupBy('group'), 'difference' => -(float) $difference->fresh()->opening_balance]);
    }

    public function update(Request $request, OpeningBalances $openings): RedirectResponse
    {
        $data = $request->validate(['dr' => ['nullable', 'array'], 'dr.*' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'cr' => ['nullable', 'array'], 'cr.*' => ['nullable', 'numeric', 'min:0', 'max:999999999999']]);
        $ids = array_unique(array_merge(array_keys($data['dr'] ?? []), array_keys($data['cr'] ?? [])));
        $balances = [];
        foreach ($ids as $id) {
            $balances[(int) $id] = (float) ($data['dr'][$id] ?? 0) - (float) ($data['cr'][$id] ?? 0);
        }
        $difference = $openings->save($balances);

        return back()->with('status', abs($difference) < 0.005 ? __('Opening balances saved; they balance.') : __('Opening balances saved. :amount does not balance yet and sits in “Difference in opening balances”.', ['amount' => \App\Support\Money::company(abs($difference))]));
    }

    public function import(Request $request, OpeningBalances $openings): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:2048', 'mimes:csv,txt']]);
        ['balances' => $balances, 'unknown' => $unknown] = $openings->fromCsv($request->file('file')->getRealPath());
        $difference = $openings->save($balances);

        return back()->with('status', trans_choice(':count opening balance brought in.|:count opening balances brought in.', count($balances)) . (abs($difference) >= 0.005 ? ' ' . __('Difference: :amount.', ['amount' => \App\Support\Money::company(abs($difference))]) : ''))
            ->with('unknown', $unknown);
    }
}
