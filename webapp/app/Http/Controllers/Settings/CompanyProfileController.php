<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Tenancy\CompanyContext;
use App\Platform\Tenancy\CompanyProfile;
use App\Support\Regions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The company's identity: names, address, contact details and logo, as they
 * will print on LRs, invoices and statements.
 *
 * Country, currency and the financial year are fixed at sign-up: changing
 * them later would re-date every report and number series. Tax registrations
 * (GSTIN, TRN) arrive with the tax engine (M8).
 */
class CompanyProfileController extends Controller
{
    public function __construct(private CompanyContext $context, private AuditRecorder $audit) {}

    public function edit(): View
    {
        $company = $this->context->company();

        return view('settings.company', [
            'company' => $company,
            'regions' => Regions::for($company->country),
        ]);
    }

    public function update(Request $request, CompanyProfile $profile): RedirectResponse
    {
        $company = $this->context->company();
        $data = $request->validate($profile->rules($company), $profile->messages());
        $profile->save($company, $data, $request->file('logo'));

        return back()->with('status', __('Company details saved.'));
    }

    /** The logo, served to signed-in members only (files are never public). */
    public function logo(): Response
    {
        $path = $this->context->company()->logo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=3600']);
    }
}
