<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Modules\Parties\Models\Party;
use App\Modules\Settings\Services\CompanySettings;
use App\Modules\Tax\Models\TaxCode;
use App\Modules\Tax\Models\TaxRate;
use App\Modules\Tax\Models\TaxRegistration;
use App\Modules\Tax\Models\TaxRegistrationYear;
use App\Modules\Tax\Models\TaxRule;
use App\Modules\Tax\Services\ServiceClasses;
use App\Modules\Tax\Services\TaxContext;
use App\Modules\Tax\Services\TaxEngine;
use App\Modules\Tax\Services\TaxException;
use App\Modules\Tax\Services\TaxPresets;
use App\Platform\Tenancy\CompanyContext;
use App\Support\FiscalYear;
use App\Support\Regions;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Settings → Tax (blueprint 11): our registrations and India's GTA option for
 * each year, the tax codes with their dated rates, the rules that pick a code
 * from the freight facts, the invoice rules, and a calculator to try a case
 * before invoicing it.
 *
 * Rates are never edited: a change is a new rate from a date, which closes
 * the one before. Rules can be pointed at another code or switched off; their
 * conditions stay as the preset made them.
 */
class TaxController extends Controller
{
    public const RULE_KEYS = ['invoice_round_total', 'tax_evidence_policy', 'tds_declaration_given'];

    public function __construct(private CompanyContext $context, private CompanySettings $settings) {}

    public function index(Request $request, TaxEngine $engine): View
    {
        $company = $this->context->company();
        $regime  = TaxPresets::REGIME_FOR[$company->country] ?? null;
        $fy      = FiscalYear::containing(now($company->timezone ?: 'UTC'), (int) ($company->fy_start_month ?: 1));

        return view('settings.tax', [
            'company'       => $company,
            'regime'        => $regime,
            'fy'            => $fy,
            'registrations' => TaxRegistration::with('years')->orderByDesc('is_default')->orderBy('tax_id')->get(),
            'codes'         => TaxCode::with('rates')->orderBy('category')->orderBy('code')->get(),
            'rules'         => TaxRule::where('regime', $regime)->orderBy('service_class')->orderByDesc('priority')->orderBy('id')->get(),
            'values'        => $this->settings->all(),
            'parties'       => Party::active()->withRole('client')->orderBy('name')->get(['id', 'name']),
            'regions'       => Regions::for($company->country),
            'trial'         => $request->boolean('try') ? $this->tryIt($request, $engine) : null,
        ]);
    }

    public function saveRules(Request $request): RedirectResponse
    {
        $data = $request->validate(['tax_evidence_policy' => ['required', Rule::in(['block', 'warn'])]]);
        $data['invoice_round_total']   = $request->boolean('invoice_round_total');
        $data['tds_declaration_given'] = $request->boolean('tds_declaration_given');
        $this->settings->save($data);

        return back()->with('status', __('Saved.'));
    }

    public function storeRegistration(Request $request): RedirectResponse
    {
        $company = $this->context->company();
        $data = $request->validate([
            'tax_id'        => ['required', 'string', 'max:20'],
            'legal_name'    => ['nullable', 'string', 'max:200'],
            'address'       => ['nullable', 'string', 'max:1000'],
            'state_code'    => ['nullable', Rule::in(array_keys(Regions::for($company->country)))],
            'registered_on' => ['nullable', 'date_format:Y-m-d'],
        ]);
        app(\App\Modules\Tax\Services\Registrations::class)->add($company, $data);

        return redirect()->route('settings.tax')->with('status', __('Registration added.'));
    }

    public function updateRegistration(Request $request, TaxRegistration $registration): RedirectResponse
    {
        $data = $request->validate([
            'legal_name'   => ['nullable', 'string', 'max:200'],
            'address'      => ['nullable', 'string', 'max:1000'],
            'cancelled_on' => ['nullable', 'date_format:Y-m-d'],
            'is_default'   => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($registration, $data, $request) {
            if ($request->boolean('is_default')) {
                TaxRegistration::where('id', '!=', $registration->id)->update(['is_default' => false]);
            }
            $registration->fill([
                'legal_name' => $data['legal_name'] ?? null, 'address' => $data['address'] ?? null, 'cancelled_on' => $data['cancelled_on'] ?? null,
                'is_default' => $request->boolean('is_default') || $registration->is_default,
            ])->save();
        });

        return redirect()->route('settings.tax')->with('status', __('Saved.'));
    }

    /** India: the GTA option chosen for a year through Annexure V. Locked once recorded. */
    public function storeYear(Request $request, TaxRegistration $registration): RedirectResponse
    {
        $data = $request->validate([
            'fy_key'       => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'gta_option'   => ['required', Rule::in(array_keys(TaxRegistration::GTA_OPTIONS))],
            'annexure_ref' => ['nullable', 'string', 'max:60'],
            'declared_on'  => ['nullable', 'date_format:Y-m-d'],
        ], ['fy_key.regex' => __('A financial year like 2026-27.')]);

        if ($registration->regime !== 'in_gst') {
            throw ValidationException::withMessages(['gta_option' => __('The GTA option is for Indian GST registrations.')]);
        }
        if ($registration->years()->where('fy_key', $data['fy_key'])->exists()) {
            throw ValidationException::withMessages(['gta_option' => __('The option for :fy is already recorded; it is locked for the year.', ['fy' => $data['fy_key']])]);
        }

        TaxRegistrationYear::create($data + ['tax_registration_id' => $registration->id]);

        return redirect()->route('settings.tax')->with('status', __('GTA option for :fy recorded.', ['fy' => $data['fy_key']]));
    }

    /** A new rate for a code from a date; the rate before ends the day before. */
    public function storeRate(Request $request, TaxCode $code): RedirectResponse
    {
        $data = $request->validate([
            'valid_from' => ['required', 'date_format:Y-m-d'],
            'rate'       => ['required', 'numeric', 'min:0', 'max:100'],
            'note'       => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($code, $data) {
            $last = $code->rates()->lockForUpdate()->first();
            if ($last && $data['valid_from'] <= $last->valid_from->toDateString()) {
                throw ValidationException::withMessages(['valid_from' => __('The new rate must start after :date, when the current one started.', ['date' => $last->valid_from->format('d M Y')])]);
            }
            if ($last && ($last->valid_to === null || $last->valid_to->toDateString() >= $data['valid_from'])) {
                $last->forceFill(['valid_to' => CarbonImmutable::parse($data['valid_from'])->subDay()->toDateString()])->save();
            }
            TaxRate::create([
                'tax_code_id' => $code->id, 'valid_from' => $data['valid_from'], 'rate' => $data['rate'],
                'parts' => app(TaxPresets::class)->parts($code->regime, (float) $data['rate']), 'note' => $data['note'] ?? null,
            ]);
        });

        return redirect()->to(route('settings.tax') . '#codes')->with('status', __('New rate for :code from :date.', ['code' => $code->name, 'date' => CarbonImmutable::parse($data['valid_from'])->format('d M Y')]));
    }

    public function updateRule(Request $request, TaxRule $rule): RedirectResponse
    {
        $codes = TaxCode::where('regime', $rule->regime)->pluck('code')->push('@main')->all();
        $data  = $request->validate([
            'tax_code' => ['required', Rule::in($codes)],
            'sac_code' => ['nullable', 'string', 'max:10', 'regex:/^[0-9A-Za-z]*$/'],
        ]);
        $rule->fill($data + ['active' => $request->boolean('active')])->save();

        return redirect()->to(route('settings.tax') . '#rules')->with('status', __('Rule saved.'));
    }

    /** The calculator: one case, worked out as an invoice would be. */
    private function tryIt(Request $request, TaxEngine $engine): array
    {
        $data = $request->validate([
            'registration_id' => ['nullable', 'integer'],
            'party_id'        => ['required', 'integer'],
            'service_class'   => ['required', Rule::in(array_keys(ServiceClasses::CLASSES))],
            'amount'          => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'date'            => ['required', 'date_format:Y-m-d'],
            'loading_state'   => ['nullable', 'string', 'max:10'],
            'transport_type'  => ['nullable', Rule::in(['domestic', 'export', 'import', 'international'])],
            'carrier_role'    => ['nullable', Rule::in(['main', 'sub'])],
            'evidence'        => ['nullable', 'array'],
            'evidence.*'      => [Rule::in(array_keys(TaxCode::EVIDENCE))],
        ]);

        try {
            $result = $engine->calculate(new TaxContext(
                date: CarbonImmutable::parse($data['date']),
                registration: ! empty($data['registration_id']) ? TaxRegistration::with('years')->find($data['registration_id']) : null,
                buyer: Party::with('taxIds')->findOrFail($data['party_id']),
                loadingState: $data['loading_state'] ?? null,
                transportType: $data['transport_type'] ?? null,
                carrierRole: $data['carrier_role'] ?? null,
                evidence: $data['evidence'] ?? [],
                inclusive: $request->boolean('inclusive'),
            ), [['service_class' => $data['service_class'], 'description' => ServiceClasses::label($data['service_class']), 'quantity' => 1, 'unit_rate' => (float) $data['amount']]]);

            return ['input' => $data, 'result' => $result, 'error' => null];
        } catch (TaxException $e) {
            return ['input' => $data, 'result' => null, 'error' => $e->getMessage()];
        }
    }
}
