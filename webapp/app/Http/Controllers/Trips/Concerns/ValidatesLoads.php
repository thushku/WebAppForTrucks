<?php

namespace App\Http\Controllers\Trips\Concerns;

use App\Modules\Trips\Models\Consignment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The fields of one load (consignment / LR), shared by the new-trip quick
 * form (prefixed "load.") and the consignment form on the trip page.
 */
trait ValidatesLoads
{
    /** @return array<string, mixed> consignment columns */
    protected function validatedLoad(Request $request, string $prefix = '', bool $withStops = false): array
    {
        $companyId = $this->context->id();
        $party = Rule::exists('parties', 'id')->where('company_id', $companyId);
        $p = $prefix;

        $rules = [
            $p . 'billing_party_id'     => ['required', 'integer', $party],
            $p . 'consignor_party_id'   => ['nullable', 'integer', $party],
            $p . 'consignee_party_id'   => ['nullable', 'integer', $party],
            $p . 'material'             => ['nullable', 'string', Rule::exists('master_items', 'code')->where('company_id', $companyId)->where('list', 'material')],
            $p . 'description'          => ['nullable', 'string', 'max:200'],
            $p . 'packages'             => ['nullable', 'integer', 'min:0', 'max:1000000'],
            $p . 'package_type'         => ['nullable', Rule::in(Consignment::PACKAGE_TYPES)],
            $p . 'actual_weight_mt'     => ['nullable', 'numeric', 'min:0', 'max:500'],
            $p . 'charged_weight_mt'    => ['nullable', 'numeric', 'min:0', 'max:500'],
            $p . 'freight_basis'        => ['required', Rule::in(array_keys(Consignment::BASES))],
            $p . 'rate'                 => ['required', 'numeric', 'min:0', 'max:99999999'],
            $p . 'rate_contract_line_id' => ['nullable', 'integer', Rule::exists('rate_contract_lines', 'id')->where('company_id', $companyId)],
            $p . 'loading_charge'       => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            $p . 'unloading_charge'     => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            $p . 'other_charges'        => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            $p . 'detention_free_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            $p . 'detention_rate_per_day' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            $p . 'advance_from_party'   => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            $p . 'payment_type'         => ['required', Rule::in(array_keys(Consignment::PAYMENT_TYPES))],
            $p . 'gst_payable_by'       => ['nullable', Rule::in(array_keys(Consignment::GST_PAYERS))],
            $p . 'consignor_invoice_no' => ['nullable', 'string', 'max:40'],
            $p . 'consignor_invoice_date' => ['nullable', 'date_format:Y-m-d'],
            $p . 'consignor_invoice_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            $p . 'declared_value'       => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            $p . 'risk'                 => ['nullable', Rule::in(['owner', 'carrier'])],
            $p . 'hazardous'            => ['nullable', 'boolean'],
            $p . 'over_dimension'       => ['nullable', 'boolean'],
            $p . 'seal_no'              => ['nullable', 'string', 'max:40'],
            $p . 'container_no'         => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z]{4}[0-9]{7}$/'],
            $p . 'client_ref'           => ['nullable', 'string', 'max:60'],
            $p . 'uae_transport_type'   => ['nullable', Rule::in(['domestic', 'export', 'import', 'international'])],
            $p . 'uae_role'             => ['nullable', Rule::in(['main', 'sub'])],
            $p . 'eway_no'              => ['nullable', 'string', 'regex:/^[0-9]{12}$/'],
            $p . 'eway_valid_until'     => ['nullable', 'date'],
            $p . 'remarks'              => ['nullable', 'string', 'max:2000'],
        ];
        if ($withStops) {
            $rules[$p . 'pickup_stop_id'] = ['required', 'integer'];
            $rules[$p . 'drop_stop_id']   = ['required', 'integer'];
        }

        $data = $request->validate($rules, [
            $p . 'container_no.regex' => __('A container number has 4 letters and 7 digits (ISO 6346).'),
            $p . 'eway_no.regex'      => __('An e-way bill number has 12 digits.'),
            $p . 'billing_party_id.required' => __('Choose who pays (the billing party).'),
        ]);

        $load = [];
        foreach ($data as $key => $value) {
            $load[substr($key, strlen($p))] = $value;
        }
        // Nested input ("load.x") comes back nested.
        if ($p !== '' && isset($data[rtrim($p, '.')])) {
            $load = $data[rtrim($p, '.')];
        }

        if (in_array($load['freight_basis'], ['tonne', 'kg'], true) && ! filled($load['charged_weight_mt'] ?? null) && ! filled($load['actual_weight_mt'] ?? null)) {
            throw ValidationException::withMessages([$p . 'actual_weight_mt' => __('A rate per tonne needs the weight.')]);
        }
        if ($load['freight_basis'] === 'package' && ! filled($load['packages'] ?? null)) {
            throw ValidationException::withMessages([$p . 'packages' => __('A rate per package needs the number of packages.')]);
        }
        if (filled($load['eway_no'] ?? null) && $this->context->company()->country !== 'IN') {
            unset($load['eway_no']);
        }

        foreach (['loading_charge', 'unloading_charge', 'other_charges', 'advance_from_party'] as $money) {
            $load[$money] = (float) ($load[$money] ?? 0);
        }
        $load['hazardous']      = (bool) ($load['hazardous'] ?? false);
        $load['over_dimension'] = (bool) ($load['over_dimension'] ?? false);
        $load['risk']         ??= 'owner';
        $load['container_no'] = isset($load['container_no']) ? strtoupper($load['container_no']) : null;

        return $load;
    }

    /** The e-way bill typed on the form, as rows for the consignment. */
    protected function ewaybillsFrom(array &$load): array
    {
        $rows = [];
        if (filled($load['eway_no'] ?? null)) {
            $rows[] = ['number' => $load['eway_no'], 'valid_until' => $load['eway_valid_until'] ?? null, 'invoice_no' => $load['consignor_invoice_no'] ?? null, 'value' => $load['consignor_invoice_value'] ?? null];
        }
        unset($load['eway_no'], $load['eway_valid_until']);

        return $rows;
    }
}
