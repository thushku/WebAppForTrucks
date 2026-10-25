<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Tenancy\CompanyContext;
use App\Support\Regions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

    public function update(Request $request): RedirectResponse
    {
        $company = $this->context->company();

        $data = $request->validate([
            'name'          => ['required', 'string', 'min:2', 'max:150'],
            'legal_name'    => ['nullable', 'string', 'max:200'],
            'short_code'    => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{2,6}$/'],
            'address_line1' => ['nullable', 'string', 'max:200'],
            'address_line2' => ['nullable', 'string', 'max:200'],
            'city'          => ['nullable', 'string', 'max:100'],
            'state_code'    => ['nullable', Rule::in(array_keys(Regions::for($company->country)))],
            'postal_code'   => ['nullable', 'string', $company->country === 'IN' ? 'regex:/^\d{6}$/' : 'max:20'],
            'phone'         => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{6,20}$/'],
            'email'         => ['nullable', 'email:filter', 'max:255'],
            'website'       => ['nullable', 'url:http,https', 'max:255'],
            'logo'          => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:300', 'dimensions:max_width=2000,max_height=2000'],
            'remove_logo'   => ['nullable', 'boolean'],
        ], [
            'postal_code.regex' => __('A PIN code has 6 digits.'),
            'short_code.regex'  => __('2 to 6 letters or digits.'),
            'logo.max'          => __('The logo may be at most 300 KB.'),
        ]);

        $fields = collect($data)->except(['logo', 'remove_logo'])->map(fn ($v) => is_string($v) ? trim($v) : $v)->all();
        $fields['short_code'] = isset($fields['short_code']) ? strtoupper($fields['short_code']) : null;

        if ($request->boolean('remove_logo') && $company->logo_path) {
            Storage::disk('local')->delete($company->logo_path);
            $fields['logo_path'] = null;
        }

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            // Check the content, not the name: it must really be an image.
            if (@getimagesize($file->getRealPath()) === false) {
                return back()->withErrors(['logo' => __('That file is not an image.')]);
            }
            if ($company->logo_path) {
                Storage::disk('local')->delete($company->logo_path);
            }
            $fields['logo_path'] = $file->storeAs("companies/{$company->uuid}", 'logo-' . now()->format('YmdHis') . '.' . $file->extension(), 'local');
        }

        $company->forceFill($fields)->save();

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
