<?php

namespace App\Http\Controllers\Accounts;

use App\Modules\Accounts\Services\Payments;
use App\Modules\Settings\Models\CompanyBankAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Accounts → Payment: a payment voucher to a vendor, supplier, crew member or expense (`Payments`). */
class PaymentController extends AccountsController
{
    public function create(Request $request, Payments $payments): View
    {
        return view('accounts.payment', [
            'payees' => $payments->payees(),
            'banks' => CompanyBankAccount::where('active', true)->orderByDesc('is_default')->get(),
            'today' => $this->today(),
            'payee' => (int) $request->query('ledger'),
            'trucks' => \App\Modules\Fleet\Models\Truck::inBranch()->where('status', '!=', 'sold')->orderBy('reg_no')->get(['id', 'reg_no']),
        ]);
    }

    public function store(Request $request, Payments $payments): RedirectResponse
    {
        $voucher = $payments->pay($request->validate($payments->rules()), $request->user());

        return redirect()->route('accounts.vouchers.show', $voucher)->with('status', __('Payment :no posted.', ['no' => $voucher->number]));
    }
}
