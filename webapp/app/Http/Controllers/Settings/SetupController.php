<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Modules\Tax\Models\TaxRegistration;
use App\Modules\Tax\Services\Registrations;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Settings\PlatformSettings;
use App\Platform\Signup\GettingStarted;
use App\Platform\Tenancy\CompanyContext;
use App\Support\QrCode;
use App\Support\Regions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Getting started (blueprint 04 §5, the company set-up wizard): the address,
 * tax ID and financial year, the first truck, the first driver with
 * their activation code, the crew app, the first trip. The steps tick
 * themselves (GettingStarted); the truck and driver steps use their own
 * pages, with every check they have.
 * The first sign-in after confirming the email opens it; the dashboard shows
 * it until the owner says they are done.
 */
class SetupController extends Controller
{
    public function __construct(private CompanyContext $context, private GettingStarted $start) {}

    public function show(PlatformSettings $settings): View
    {
        $company = $this->context->company();
        $steps = $this->start->steps();
        $app = $settings->get('crew_app_url');

        return view('settings.welcome', [
            'company' => $company, 'steps' => $steps, 'done' => count(array_filter($steps)),
            'regions' => Regions::for($company->country), 'taxLabel' => \App\Modules\Parties\Services\TaxIds::label($company->country),
            'registration' => TaxRegistration::orderByDesc('is_default')->first(),
            'booksStarted' => DB::table('vouchers')->where('company_id', $company->id)->exists(),
            'appQr' => $app ? QrCode::encode($app)->svg(3, '#0F1A2B', __('Crew app download')) : null, 'appUrl' => $app,
        ]);
    }

    /** Step 1: where the company is, its tax ID (both print on every bill) and its financial year. */
    public function company(Request $request, Registrations $registrations): RedirectResponse
    {
        $company = $this->context->company();
        $data = $request->validate([
            'legal_name' => ['nullable', 'string', 'max:200'],
            'address_line1' => ['required', 'string', 'max:200'],
            'city' => ['required', 'string', 'max:100'],
            'state_code' => ['required', Rule::in(array_keys(Regions::for($company->country)))],
            'postal_code' => ['nullable', 'string', $company->country === 'IN' ? 'regex:/^\d{6}$/' : 'max:20'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{6,20}$/'],
            'tax_id' => ['nullable', 'string', 'max:20'],
            'fy_start_month' => ['required', 'integer', 'between:1,12'],
        ], ['postal_code.regex' => __('A PIN code has 6 digits.')]);

        // The year's start month is fixed once the books have an entry: numbering and statements depend on it.
        if ((int) $data['fy_start_month'] !== $company->fy_start_month && DB::table('vouchers')->where('company_id', $company->id)->exists()) {
            return back()->withInput()->withErrors(['fy_start_month' => __('The books have entries already; the financial year can no longer change.')]);
        }

        DB::transaction(function () use ($company, $data, $registrations) {
            $company->forceFill(collect($data)->except('tax_id')->map(fn ($v) => is_string($v) ? trim($v) : $v)->all())->save();
            if (filled($data['tax_id'] ?? null) && ! TaxRegistration::exists()) {
                $registrations->add($company, ['tax_id' => $data['tax_id'], 'legal_name' => $data['legal_name'] ?? null]);
            }
        });

        return redirect()->to(route('welcome') . '#truck')->with('status', __('Company details saved.'));
    }

    /** Done (or skipped): the dashboard stops showing the steps. */
    public function finish(AuditRecorder $audit): RedirectResponse
    {
        $company = $this->context->company();
        $company->forceFill(['setup_done_at' => now()])->save();
        $progress = $this->start->progress();
        $audit->record('company.setup_done', "Getting started finished ({$progress['done']} of {$progress['total']} steps)", 'company', (string) $company->id, $company->name);

        return redirect()->route('dashboard')->with('status', __('All set. Every step has its own page in the menu.'));
    }
}
