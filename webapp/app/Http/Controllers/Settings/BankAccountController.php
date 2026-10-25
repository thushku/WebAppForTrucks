<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Modules\Invoicing\Services\PaymentQr;
use App\Modules\Settings\Models\CompanyBankAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Our bank accounts (Settings → Bank accounts): where clients pay, printed on
 * bills with the UPI QR. Numbers are encrypted and shown masked; every change
 * is in the audit log as sensitive.
 */
class BankAccountController extends Controller
{
    public function index(): View
    {
        return view('settings.banks', ['accounts' => CompanyBankAccount::orderByDesc('is_default')->orderBy('label')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $request) {
            $first = ! CompanyBankAccount::exists();
            if ($request->boolean('is_default')) {
                CompanyBankAccount::query()->update(['is_default' => false]);
            }
            CompanyBankAccount::create($data + ['account_last4' => substr(preg_replace('/\D/', '', $data['account_number']) ?: $data['account_number'], -4),
                'is_default' => $first || $request->boolean('is_default'), 'show_on_invoice' => true, 'currency' => $data['currency'] ?? app(\App\Platform\Tenancy\CompanyContext::class)->company()->currency]);
        });

        return back()->with('status', __('Bank account added.'));
    }

    public function update(Request $request, CompanyBankAccount $account): RedirectResponse
    {
        DB::transaction(function () use ($request, $account) {
            if ($request->boolean('is_default')) {
                CompanyBankAccount::where('id', '!=', $account->id)->update(['is_default' => false]);
            }
            $account->fill(['is_default' => $request->boolean('is_default') || $account->is_default, 'show_on_invoice' => $request->boolean('show_on_invoice'), 'active' => $request->boolean('active')])->save();
        });

        return back()->with('status', __('Saved.'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'label'          => ['required', 'string', 'max:60'],
            'bank_name'      => ['required', 'string', 'max:120'],
            'holder_name'    => ['required', 'string', 'max:150'],
            'account_number' => ['required', 'string', 'regex:/^[0-9A-Za-z]{6,34}$/'],
            'ifsc'           => ['nullable', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'iban'           => ['nullable', 'regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{10,30}$/'],
            'swift'          => ['nullable', 'regex:/^[A-Z0-9]{8}([A-Z0-9]{3})?$/'],
            'branch'         => ['nullable', 'string', 'max:120'],
            'upi_id'         => ['nullable', 'regex:' . PaymentQr::UPI_ID],
            'currency'       => ['nullable', 'string', 'size:3'],
        ], ['ifsc.regex' => __('An IFSC is 11 characters, like HDFC0001234.'), 'upi_id.regex' => __('A UPI id looks like name@bank.'), 'account_number.regex' => __('Letters and digits only.')]);
    }
}
