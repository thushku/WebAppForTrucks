<?php

namespace App\Platform\Tenancy;

use App\Modules\Tax\Models\TaxRegistration;
use App\Support\Regions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The company's identity as it prints on LRs, invoices and statements:
 * names, address, contact details and logo — saved from Settings → Company on
 * the web and from the owner app's profile (Business, M28).
 *
 * Country, currency and the financial year are fixed at sign-up; tax
 * registrations (GSTIN, TRN) have their own page (Settings → Tax) and are
 * only listed here.
 */
class CompanyProfile
{
    public function rules(Company $company): array
    {
        return [
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
        ];
    }

    public function messages(): array
    {
        return [
            'postal_code.regex' => __('A PIN code has 6 digits.'),
            'short_code.regex'  => __('2 to 6 letters or digits.'),
            'logo.max'          => __('The logo may be at most 300 KB.'),
        ];
    }

    /** Save validated fields; a new logo replaces the old file, "remove" drops it. */
    public function save(Company $company, array $data, ?UploadedFile $logo = null): Company
    {
        $fields = collect($data)->except(['logo', 'remove_logo'])->map(fn ($v) => is_string($v) ? trim($v) : $v)->all();
        $fields['short_code'] = isset($fields['short_code']) ? strtoupper($fields['short_code']) : null;

        if (filter_var($data['remove_logo'] ?? false, FILTER_VALIDATE_BOOLEAN) && $company->logo_path) {
            Storage::disk('local')->delete($company->logo_path);
            $fields['logo_path'] = null;
        }

        if ($logo) {
            // Check the content, not the name: it must really be an image.
            if (@getimagesize($logo->getRealPath()) === false) {
                throw ValidationException::withMessages(['logo' => __('That file is not an image.')]);
            }
            if ($company->logo_path) {
                Storage::disk('local')->delete($company->logo_path);
            }
            $fields['logo_path'] = $logo->storeAs("companies/{$company->uuid}", 'logo-' . now()->format('YmdHis') . '.' . $logo->extension(), 'local');
        }

        $company->forceFill($fields)->save();

        return $company;
    }

    /** The Business tab of the owner app's profile. */
    public function toApi(Company $company, bool $canEdit): array
    {
        return [
            'can_edit' => $canEdit,
            'name' => $company->name, 'legal_name' => $company->legal_name, 'short_code' => $company->short_code,
            'address_line1' => $company->address_line1, 'address_line2' => $company->address_line2, 'city' => $company->city,
            'state_code' => $company->state_code, 'state' => Regions::name($company->country, $company->state_code), 'postal_code' => $company->postal_code,
            'phone' => $company->phone, 'email' => $company->email, 'website' => $company->website,
            'country' => $company->country, 'currency' => $company->currency, 'timezone' => $company->timezone,
            'logo_url' => $company->logo_path ? route('api.owner.company.logo', ['v' => substr(md5($company->logo_path), 0, 8)]) : null,
            'tax_ids' => TaxRegistration::where('active', true)->orderByDesc('is_default')->get(['regime', 'tax_id', 'state_code', 'is_default'])
                ->map(fn ($r) => ['regime' => $r->regime, 'number' => $r->tax_id, 'state_code' => $r->state_code, 'default' => $r->is_default])->values(),
            'regions' => Regions::for($company->country),
            'regions_label' => Regions::label($company->country),
        ];
    }
}
