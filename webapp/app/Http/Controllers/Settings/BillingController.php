<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Platform\Billing\Billing;
use App\Platform\Billing\Limits;
use App\Platform\Billing\Plan;
use App\Platform\Billing\Subscription;
use App\Platform\Settings\PlatformSettings;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\View\View;

/**
 * Settings → Plan and billing: the company's plan or trial, what it uses
 * against the plan's limits, what it has paid, and the plans it can move
 * to with their price for its trucks. Until a payment gateway is approved
 * the owner pays the provider directly (bank, UPI) and the console records
 * it; the page gives the provider's contacts with the request filled in.
 * Open while the company is read-only (RequireCompany lets billing.* through).
 */
class BillingController extends Controller
{
    public function __invoke(CompanyContext $context, Billing $billing, Limits $limits, PlatformSettings $settings): View
    {
        $company = $context->company();
        $usage = $limits->usage($company);

        return view('settings.billing', [
            'company' => $company, 'plan' => $company->plan_id ? Plan::find($company->plan_id) : null, 'current' => $billing->current($company), 'usage' => $usage,
            'plans' => Plan::offered($company->country)->where('is_public', true)->get(),
            'paid' => Subscription::with('plan:id,name')->where('company_id', $company->id)->orderByDesc('starts_on')->orderByDesc('id')->limit(12)->get(),
            'contact' => ['email' => $settings->get('support_email'), 'phone' => $settings->get('support_phone'), 'whatsapp' => $settings->get('support_whatsapp')],
            'brand' => $settings->brandName(),
        ]);
    }
}
