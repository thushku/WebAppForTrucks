<?php

namespace App\Http\Controllers\Accounts;

use App\Modules\Accounts\Models\LedgerAccount;
use App\Modules\Accounts\Services\Books;
use App\Modules\Accounts\Services\ChartOfAccounts;
use App\Modules\Settings\Models\CompanyBankAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A payment voucher (PV/26-27/00001): money paid to a vendor (the pump's
 * monthly bill, the workshop), a truck supplier outside a hire challan, a
 * crew member's earnings (bata, salary), or any expense ledger — from cash or
 * one of our bank accounts. One balanced voucher; the payee's ledger shows it.
 */
class PaymentController extends AccountsController
{
    public function create(Request $request, ChartOfAccounts $chart): View
    {
        $chart->ensure();
        $payees = LedgerAccount::with('group:id,name,nature')->where('active', true)->whereNotIn('kind', ['cash', 'bank'])->orderBy('name')->get(['id', 'name', 'kind', 'account_group_id']);

        return view('accounts.payment', [
            'payees' => $payees->filter(fn ($l) => in_array($l->kind, ['party', 'person'], true) || in_array($l->group->nature, ['expense', 'liability', 'asset'], true)),
            'banks' => CompanyBankAccount::where('active', true)->orderByDesc('is_default')->get(),
            'today' => $this->today(),
            'payee' => (int) $request->query('ledger'),
        ]);
    }

    public function store(Request $request, Books $books, ChartOfAccounts $chart): RedirectResponse
    {
        $company = $this->context->id();
        $data = $request->validate([
            'ledger_account_id' => ['required', 'integer', Rule::exists('ledger_accounts', 'id')->where('company_id', $company)->whereNotIn('kind', ['cash', 'bank'])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999'],
            'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:' . $this->today()->toDateString()],
            'mode' => ['required', Rule::in(['cash', 'bank'])],
            'bank_account_id' => ['nullable', 'integer', Rule::exists('company_bank_accounts', 'id')->where('company_id', $company)],
            'reference' => ['nullable', 'string', 'max:60', 'required_if:mode,bank'],
            'narration' => ['required', 'string', 'min:3', 'max:255'],
        ]);
        $payee = LedgerAccount::findOrFail($data['ledger_account_id']);

        $voucher = $books->post([
            'type' => 'payment', 'series' => 'payment_voucher', 'date' => $data['paid_on'], 'reference' => $data['reference'] ?? null,
            'narration' => __('Paid to :name: :what', ['name' => $payee->name, 'what' => $data['narration']]), 'created_by' => $request->user()->id,
        ], [
            ['ledger' => $payee, 'debit' => (float) $data['amount'], 'bucket' => $payee->kind === 'person' ? 'earnings' : null],
            ['ledger' => $chart->money($data['mode'], $data['bank_account_id'] ?? null), 'credit' => (float) $data['amount']],
        ]);

        return redirect()->route('accounts.vouchers.show', $voucher)->with('status', __('Payment :no posted.', ['no' => $voucher->number]));
    }
}
