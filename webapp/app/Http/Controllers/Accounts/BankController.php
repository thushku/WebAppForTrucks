<?php

namespace App\Http\Controllers\Accounts;

use App\Modules\Accounts\Models\BankStatementLine;
use App\Modules\Accounts\Models\LedgerAccount;
use App\Modules\Accounts\Services\BankReconciliation;
use App\Modules\Accounts\Services\Statements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Accounts → Bank reconciliation (M19): bring the bank's statement in and match it with the book. */
class BankController extends AccountsController
{
    public function index(Request $request, BankReconciliation $recon, Statements $statements): View
    {
        $banks = $recon->bankLedgers();
        $bank = $request->filled('ledger') ? $banks->firstWhere('id', (int) $request->query('ledger')) : $banks->first();
        $show = $request->validate(['show' => ['nullable', 'in:open,matched,ignored,all']])['show'] ?? 'open';

        $lines = $bank ? BankStatementLine::with('voucherLine.voucher:id,number,voucher_date')->where('ledger_account_id', $bank->id)
            ->when($show !== 'all', fn ($q) => $q->where('status', $show))->orderByDesc('line_date')->orderByDesc('id')->limit(300)->get() : collect();

        return view('accounts.bank', [
            'banks' => $banks, 'bank' => $bank, 'show' => $show, 'lines' => $lines,
            'candidates' => $recon->candidatesFor($lines, 31),
            'summary' => $bank ? $recon->summary($bank, $statements) : null,
        ]);
    }

    public function import(Request $request, BankReconciliation $recon): RedirectResponse
    {
        $data = $request->validate(['ledger_account_id' => ['required', 'integer'], 'file' => ['required', 'file', 'max:4096', 'mimes:csv,txt']]);
        $bank = LedgerAccount::where('kind', 'bank')->findOrFail($data['ledger_account_id']);
        $statement = $recon->import($bank, $request->file('file')->getRealPath(), $request->file('file')->getClientOriginalName(), $request->user());
        $open = BankStatementLine::where('bank_statement_id', $statement->id)->where('status', 'open')->count();

        return redirect()->route('accounts.bank', ['ledger' => $bank->id])->with('status', __(':n lines brought in (:d seen before, left out); :m matched by themselves, :o to look at.', [
            'n' => $statement->lines, 'd' => $statement->duplicates, 'm' => $statement->lines - $open, 'o' => $open]));
    }

    public function match(Request $request, BankStatementLine $line, BankReconciliation $recon): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', 'in:match,ignore,book,undo'], 'voucher_line_id' => ['nullable', 'required_if:action,match', 'integer'], 'note' => ['nullable', 'required_if:action,ignore', 'string', 'max:255']]);
        match ($data['action']) {
            'match' => $recon->match($line, (int) $data['voucher_line_id'], $request->user()),
            'ignore' => $recon->ignore($line, (string) $data['note'], $request->user()),
            'book' => $recon->book($line, $request->user()),
            'undo' => $recon->unmatch($line),
        };

        return back()->with('status', match ($data['action']) {
            'match' => __('Matched.'), 'ignore' => __('Left out, with your note.'), 'book' => (float) $line->amount > 0 ? __('Booked as bank interest and matched.') : __('Booked as bank charges and matched.'), 'undo' => __('Open again.'),
        });
    }

    public function auto(Request $request, BankReconciliation $recon): RedirectResponse
    {
        $bank = LedgerAccount::where('kind', 'bank')->findOrFail((int) $request->input('ledger_account_id'));

        return back()->with('status', trans_choice(':count line matched.|:count lines matched.', $recon->autoMatch($bank)));
    }
}
