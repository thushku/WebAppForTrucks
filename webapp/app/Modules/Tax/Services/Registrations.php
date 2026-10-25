<?php

namespace App\Modules\Tax\Services;

use App\Modules\Parties\Services\TaxIds;
use App\Modules\Tax\Models\TaxRegistration;
use App\Platform\Tenancy\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adding the company's own tax registration (GSTIN, TRN): checked against
 * the country's format and never twice; the first one becomes the default.
 * Used by Settings → Tax and by the getting-started steps.
 */
class Registrations
{
    /** @param array{tax_id: string, legal_name?: ?string, address?: ?string, state_code?: ?string, registered_on?: ?string} $data */
    public function add(Company $company, array $data): TaxRegistration
    {
        $taxId = TaxIds::normalise($data['tax_id']);

        if (! TaxIds::isValid($company->country, $taxId)) {
            throw ValidationException::withMessages(['tax_id' => __('That is not a valid :label.', ['label' => TaxIds::label($company->country)])]);
        }
        if (TaxRegistration::where('tax_id', $taxId)->exists()) {
            throw ValidationException::withMessages(['tax_id' => __('This registration is already here.')]);
        }

        return DB::transaction(function () use ($company, $data, $taxId) {
            $first = ! TaxRegistration::exists();

            return TaxRegistration::create([
                'country' => $company->country, 'regime' => TaxPresets::REGIME_FOR[$company->country] ?? 'in_gst', 'tax_id' => $taxId,
                // India: the state is the GSTIN's first two digits; the UAE: the emirate chosen.
                'state_code' => $company->country === 'IN' ? TaxIds::gstinState($taxId) : ($data['state_code'] ?? null),
                'legal_name' => $data['legal_name'] ?? $company->legal_name, 'address' => $data['address'] ?? null,
                'registered_on' => $data['registered_on'] ?? null, 'is_default' => $first,
            ]);
        });
    }
}
