<?php

namespace App\Http\Controllers\Accounts;

use App\Modules\Accounts\Models\LedgerAccount;
use App\Modules\Accounts\Models\Voucher;
use App\Modules\Accounts\Services\Books;
use App\Modules\Fleet\Models\Truck;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * What the accountant adds by hand (blueprint 07: bank charges, loans,
 * capital, depreciation, adjustments): a journal voucher of any ledgers that
 * balances, and its reversal. Vouchers the system made are cancelled from
 * their document (void the bill, cancel the entry), never here.
 */
class JournalController extends AccountsController
{
    public function create(): View
    {
        return view('accounts.journal', [
            'ledgers' => LedgerAccount::with('group:id,name')->where('active', true)->orderBy('name')->get(['id', 'name', 'account_group_id', 'kind']),
            'trucks' => Truck::orderBy('reg_no')->pluck('reg_no', 'id'),
            'today' => $this->today(),
        ]);
    }

    public function store(Request $request, Books $books): RedirectResponse
    {
        $company = $this->context->id();
        $data = $request->validate([
            'voucher_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:' . $this->today()->addDays(31)->toDateString()],
            'narration' => ['required', 'string', 'min:5', 'max:500'],
            'reference' => ['nullable', 'string', 'max:60'],
            'lines' => ['required', 'array', 'min:2', 'max:50'],
            'lines.*.ledger_account_id' => ['nullable', 'integer', Rule::exists('ledger_accounts', 'id')->where('company_id', $company)],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'lines.*.truck_id' => ['nullable', 'integer', Rule::exists('trucks', 'id')->where('company_id', $company)],
            'lines.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        $lines = [];
        foreach ($data['lines'] as $i => $row) {
            $debit = round((float) ($row['debit'] ?? 0), 3);
            $credit = round((float) ($row['credit'] ?? 0), 3);
            if (empty($row['ledger_account_id']) && $debit == 0 && $credit == 0) {
                continue;
            }
            if (empty($row['ledger_account_id']) || ($debit > 0) === ($credit > 0)) {
                throw ValidationException::withMessages(["lines.$i.debit" => __('Each line needs a ledger and either a debit or a credit.')]);
            }
            $lines[] = ['ledger' => (int) $row['ledger_account_id'], 'debit' => $debit, 'credit' => $credit, 'truck_id' => $row['truck_id'] ?? null, 'note' => $row['note'] ?? null];
        }
        $debits = round(array_sum(array_column($lines, 'debit')), 3);
        $credits = round(array_sum(array_column($lines, 'credit')), 3);
        if (count($lines) < 2 || abs($debits - $credits) >= 0.0005) {
            throw ValidationException::withMessages(['lines' => __('Debits (:d) and credits (:c) must be equal.', ['d' => number_format($debits, 2), 'c' => number_format($credits, 2)])]);
        }

        $voucher = $books->post(['type' => 'journal', 'date' => $data['voucher_date'], 'narration' => $data['narration'], 'reference' => $data['reference'] ?? null, 'created_by' => $request->user()->id], $lines);

        return redirect()->route('accounts.vouchers.show', $voucher)->with('status', __('Journal :no posted.', ['no' => $voucher->number]) . ($voucher->document_date ? ' ' . __('The books are locked on :date, so it is dated :posted.', ['date' => $voucher->document_date->format('d M Y'), 'posted' => $voucher->voucher_date->format('d M Y')]) : ''));
    }

    public function reverse(Request $request, Voucher $voucher, Books $books): RedirectResponse
    {
        if ($voucher->source_type) {
            throw ValidationException::withMessages(['voucher' => __('This voucher was made from a document. Cancel or void the document; the book reverses it.')]);
        }
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']]);
        $reversal = $books->reverse($voucher, $data['reason']);

        return redirect()->route('accounts.vouchers.show', $reversal)->with('status', __(':no reversed by :rev.', ['no' => $voucher->number, 'rev' => $reversal->number]));
    }
}
