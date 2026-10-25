<?php

use App\Modules\Parties\Models\PartyTaxId;
use App\Modules\Parties\Services\TaxIds;
use App\Modules\Settings\Services\CompanySettings;
use App\Modules\Tax\Models\TaxCode;
use App\Modules\Tax\Models\TaxRate;
use App\Modules\Tax\Models\TaxRegistration;
use App\Modules\Tax\Models\TaxRegistrationYear;
use App\Modules\Tax\Services\TaxCalculator;
use App\Modules\Tax\Services\TaxContext;
use App\Modules\Tax\Services\TaxEngine;
use App\Modules\Tax\Services\TaxException;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;

// The tax engine (M8): India GST for goods transport agencies, UAE VAT on
// domestic and international transport, worked out from data (presets).

/** A valid GSTIN for a state and PAN. */
function taxGstin(string $state, string $pan = 'AABCS1234F'): string
{
    $first14 = $state . $pan . '1Z';

    return $first14 . TaxIds::gstinCheckCharacter($first14);
}

/** A company in context with one registration (and, for India, the GTA option for 2026-27). */
function taxWorld(string $country = 'IN', ?string $gtaOption = null, string $state = '32'): array
{
    $company = makeCompany($country === 'AE' ? ['name' => 'Gulf Haulage', 'country' => 'AE', 'currency' => 'AED', 'timezone' => 'Asia/Dubai', 'fy_start_month' => 1] : []);
    app(CompanyContext::class)->set($company);

    $registration = TaxRegistration::create($country === 'AE'
        ? ['country' => 'AE', 'regime' => 'ae_vat', 'tax_id' => '100123456700003', 'state_code' => 'DU', 'is_default' => true]
        : ['country' => 'IN', 'regime' => 'in_gst', 'tax_id' => taxGstin($state), 'state_code' => $state, 'is_default' => true]);
    if ($gtaOption) {
        TaxRegistrationYear::create(['tax_registration_id' => $registration->id, 'fy_key' => '2026-27', 'gta_option' => $gtaOption]);
    }

    return ['company' => $company, 'registration' => $registration->fresh()];
}

/** A client, registered in a state (or not). */
function taxBuyer(?string $state = '27', array $attributes = [], string $country = 'IN')
{
    $party = makeParty($attributes + ['name' => 'ACC Limited', 'country' => $country, 'state_code' => $state], ['client']);
    if ($state && $country === 'IN') {
        // Each client its own PAN, from its name.
        $n = crc32($party->name);
        PartyTaxId::create(['party_id' => $party->id, 'country' => 'IN', 'tax_id' => taxGstin($state, 'AAAC' . chr(65 + $n % 26) . str_pad((string) ($n % 10000), 4, '0', STR_PAD_LEFT) . 'B'), 'state_code' => $state, 'is_default' => true]);
    }
    if ($country === 'AE') {
        PartyTaxId::create(['party_id' => $party->id, 'country' => 'AE', 'tax_id' => '100987654300003', 'state_code' => $state, 'is_default' => true]);
    }

    return $party->fresh('taxIds');
}

function taxRun(array $w, $buyer, array $lines, array $facts = []): array
{
    $ctx = new TaxContext(
        date: CarbonImmutable::parse($facts['date'] ?? '2026-10-25'), registration: array_key_exists('registration', $facts) ? $facts['registration'] : $w['registration'], buyer: $buyer,
        loadingState: $facts['loading'] ?? null, transportType: $facts['transport'] ?? null, carrierRole: $facts['role'] ?? null,
        evidence: $facts['evidence'] ?? [], inclusive: $facts['inclusive'] ?? false, roundTotal: $facts['round'] ?? null,
    );

    return app(TaxEngine::class)->calculate($ctx, $lines);
}

function taxFreight(float $amount, string $class = 'FREIGHT_ROAD', array $extra = []): array
{
    return ['service_class' => $class, 'description' => 'Freight', 'quantity' => 1, 'unit_rate' => $amount] + $extra;
}

it('puts GTA freight under reverse charge by default: no tax charged, the notional IGST kept', function () {
    $w = taxWorld();
    $r = taxRun($w, taxBuyer('27'), [taxFreight(50000)]);

    expect($r['document'])->toBe('tax_invoice')
        ->and($r['reverse_charge'])->toBeTrue()
        ->and($r['tax_charged'])->toBe(0.0)
        ->and($r['tax_notional'])->toBe(2500.0)
        ->and($r['total'])->toBe(50000.0)
        ->and($r['taxes'][0])->toMatchArray(['code' => 'IGST', 'percent' => 5.0, 'charged' => false, 'amount' => 2500.0])
        ->and($r['lines'][0])->toMatchArray(['tax_code' => 'GTA_RCM5', 'category' => 'AE', 'sac_code' => '996791'])
        ->and($r['place_of_supply'])->toMatchArray(['code' => '27', 'where' => 'inter'])
        ->and($r['legal_texts'][0])->toStartWith('Tax payable on reverse charge: Yes');
});

it('charges 18% forward charge as CGST + SGST in the same state, and expects the client’s TDS', function () {
    $w = taxWorld(gtaOption: 'fcm_18');
    $r = taxRun($w, taxBuyer('32', ['tds_deducted' => true]), [taxFreight(50000)]);

    expect($r['reverse_charge'])->toBeFalse()
        ->and(collect($r['taxes'])->pluck('amount', 'code')->all())->toBe(['CGST' => 4500.0, 'SGST' => 4500.0])
        ->and($r['total'])->toBe(59000.0)
        // Our PAN (AABCS…) is a company's: 2%, on the freight without GST.
        ->and($r['withholding'])->toMatchArray(['section' => '393(1)', 'old_section' => '194C', 'rate' => 2.0, 'base' => 50000.0, 'amount' => 1000.0]);

    // With our declaration of 10 trucks or fewer, nothing is deducted.
    app(CompanySettings::class)->save(['tds_declaration_given' => true]);
    expect(taxRun($w, taxBuyer('32', ['tds_deducted' => true, 'name' => 'Other client']), [taxFreight(50000)])['withholding'])->toMatchArray(['section' => '393(4)', 'amount' => 0.0]);
});

it('takes the rate in force on the invoice date: 12% before GST 2.0, 18% after', function () {
    $w = taxWorld();
    TaxRegistrationYear::create(['tax_registration_id' => $w['registration']->id, 'fy_key' => '2025-26', 'gta_option' => 'fcm_18']);
    $buyer = taxBuyer('27');

    $before = taxRun($w, $buyer, [taxFreight(10000)], ['date' => '2025-09-20']);
    $after  = taxRun($w, $buyer, [taxFreight(10000)], ['date' => '2025-09-25']);

    expect([$before['lines'][0]['tax_code'], $before['tax_charged']])->toBe(['GTA_FCM12', 1200.0])
        ->and([$after['lines'][0]['tax_code'], $after['tax_charged']])->toBe(['GTA_FCM18', 1800.0]);
});

it('exempts freight for an unregistered buyer, and needs the loading state to know the place of supply', function () {
    $w = taxWorld();
    $farmer = makeParty(['name' => 'Joseph Farms', 'state_code' => null], ['client']);

    expect(fn () => taxRun($w, $farmer, [taxFreight(8000)]))->toThrow(TaxException::class, 'Place of supply unknown: add the buyer’s GSTIN, or the state of the loading place.');

    $r = taxRun($w, $farmer, [taxFreight(8000)], ['loading' => '32']);
    expect($r['document'])->toBe('bill_of_supply')->and($r['lines'][0]['tax_code'])->toBe('GST_EXEMPT')->and($r['tax_charged'])->toBe(0.0)
        ->and($r['place_of_supply']['reason'])->toContain('handed over for transport');
});

it('charges add-ons like the freight and keeps disbursements outside the scope', function () {
    $w = taxWorld(gtaOption: 'fcm_5');
    $r = taxRun($w, taxBuyer('32'), [
        taxFreight(40000),
        taxFreight(3000, 'DETENTION', ['description' => 'Detention 2 days']),
        taxFreight(1200, 'DISBURSEMENT', ['description' => 'Weighbridge paid for you']),
    ]);

    expect(collect($r['lines'])->pluck('tax_code')->all())->toBe(['GTA_FCM5', 'GTA_FCM5', 'OUT_SCOPE'])
        ->and($r['tax_charged'])->toBe(2150.0)          // 5% of 43,000
        ->and($r['total'])->toBe(46350.0)
        ->and(collect($r['trace'])->implode(' '))->toContain('Detention / halting follows the freight line');

    // An add-on alone has no freight to follow.
    expect(fn () => taxRun($w, taxBuyer('32', ['name' => 'B2']), [taxFreight(3000, 'DETENTION')]))->toThrow(TaxException::class, 'Detention / halting is charged with the freight: add the freight line.');
});

it('splits into CGST + UTGST in a union territory without a legislature', function () {
    $w = taxWorld(gtaOption: 'fcm_18', state: '04');
    $r = taxRun($w, taxBuyer('04'), [taxFreight(10000)]);

    expect(collect($r['taxes'])->pluck('amount', 'code')->all())->toBe(['CGST' => 900.0, 'UTGST' => 900.0])->and($r['place_of_supply']['where'])->toBe('ut');
});

it('carves tax out of inclusive prices and rounds the total to the rupee', function () {
    $w = taxWorld(gtaOption: 'fcm_18');
    $inclusive = taxRun($w, taxBuyer('32'), [taxFreight(1000)], ['inclusive' => true]);
    expect([$inclusive['taxable_total'], $inclusive['tax_charged'], $inclusive['total']])->toBe([847.46, 152.54, 1000.0]);

    // Each part is rounded on its own: CGST 111.12 + SGST 111.12, so 1,234.70 + 222.24 = 1,456.94.
    $rounded = taxRun($w, taxBuyer('32', ['name' => 'B3']), [taxFreight(1234.70)]);
    expect([$rounded['tax_charged'], $rounded['round_off'], $rounded['total']])->toBe([222.24, 0.06, 1457.0]);
});

it('works out UAE VAT: domestic 5%, zero-rated exports as main carrier with papers, 5% as subcontractor', function () {
    $w = taxWorld('AE');
    $buyer = taxBuyer('AZ', [], 'AE');

    $domestic = taxRun($w, $buyer, [taxFreight(1200)], ['transport' => 'domestic']);
    expect($domestic['tax_charged'])->toBe(60.0)->and($domestic['taxes'][0]['code'])->toBe('VAT')->and($domestic['round_off'])->toBe(0.0);

    expect(fn () => taxRun($w, $buyer, [taxFreight(8000)]))->toThrow(TaxException::class, 'Set the transport type on the load');
    expect(fn () => taxRun($w, $buyer, [taxFreight(8000)], ['transport' => 'export', 'role' => 'main', 'evidence' => ['transport_document']]))
        ->toThrow(TaxException::class, 'Zero-rated international transport needs these papers on file: Exit or customs document.');

    $export = taxRun($w, $buyer, [taxFreight(8000)], ['transport' => 'export', 'role' => 'main', 'evidence' => ['transport_document', 'exit_or_customs']]);
    expect([$export['lines'][0]['tax_code'], $export['tax_charged'], $export['total']])->toBe(['VAT_0_INTL', 0.0, 8000.0]);

    $sub = taxRun($w, $buyer, [taxFreight(1200)], ['transport' => 'export', 'role' => 'sub']);
    expect([$sub['lines'][0]['tax_code'], $sub['tax_charged']])->toBe(['VAT_5', 60.0]);

    // "Warn" instead of block: the invoice is marked pending evidence.
    app(CompanySettings::class)->save(['tax_evidence_policy' => 'warn']);
    $pending = taxRun($w, $buyer, [taxFreight(8000)], ['transport' => 'import', 'role' => 'main']);
    expect($pending['pending_evidence'])->toBeTrue()->and($pending['warnings'])->toHaveCount(1);
});

it('issues without tax when not registered, and changes a tax by hand only with a reason', function () {
    $w = taxWorld();
    $none = taxRun($w, taxBuyer('27'), [taxFreight(5000)], ['registration' => null]);
    expect([$none['document'], $none['tax_charged'], $none['total']])->toBe(['bill_of_supply', 0.0, 5000.0]);

    expect(fn () => taxRun($w, taxBuyer('27', ['name' => 'B4']), [taxFreight(5000, extra: ['tax_code' => 'GST_18', 'override_reason' => 'client'])]))
        ->toThrow(TaxException::class, 'say why the tax is changed by hand');
    $manual = taxRun($w, taxBuyer('27', ['name' => 'B5']), [taxFreight(5000, extra: ['tax_code' => 'GST_18', 'override_reason' => 'Warehousing contract billed as one line'])]);
    expect([$manual['lines'][0]['tax_code'], $manual['tax_charged']])->toBe(['GST_18', 900.0])
        ->and(collect($manual['trace'])->implode(' '))->toContain('changed by hand — Warehousing contract billed as one line');
});

it('uses a new dated rate only from its date, and keeps three decimals for Gulf currencies', function () {
    $w = taxWorld(gtaOption: 'fcm_5');
    $code = TaxCode::where('code', 'GTA_FCM5')->first();
    $old = $code->rates()->first();
    $old->forceFill(['valid_to' => '2026-12-31'])->save();
    TaxRate::create(['tax_code_id' => $code->id, 'valid_from' => '2027-01-01', 'rate' => 6, 'parts' => ['intra' => ['CGST' => 3, 'SGST' => 3], 'inter' => ['IGST' => 6]]]);

    $buyer = taxBuyer('32');
    expect(taxRun($w, $buyer, [taxFreight(10000)])['tax_charged'])->toBe(500.0)
        ->and(taxRun($w, $buyer, [taxFreight(10000)], ['date' => '2027-01-05'])['tax_charged'])->toBe(600.0);

    $omr = TaxCalculator::compute([['quantity' => 3, 'unit_rate' => 12.345, 'parts' => ['VAT' => 5]]], ['decimals' => 3]);
    expect([$omr['subtotal'], $omr['tax_charged'], $omr['total']])->toBe([37.035, 1.852, 38.887]);
});

it('keeps registrations, the yearly GTA option, dated rates and rules in Settings → Tax', function () {
    $company = makeCompany();
    signInTo($company);

    $this->get(route('settings.tax'))->assertOk()->assertSeeText('No GSTIN yet: invoices are issued as bills of supply, without GST.')->assertSeeText('GTA freight, 5% under reverse charge');

    $this->post(route('settings.tax.registrations.store'), ['tax_id' => '32AABCS1234F1Z9'])->assertSessionHasErrors('tax_id');
    $gstin = taxGstin('32');
    $this->post(route('settings.tax.registrations.store'), ['tax_id' => strtolower($gstin), 'legal_name' => 'Sharma Roadlines Pvt Ltd'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($company);
    $reg = TaxRegistration::first();
    expect([$reg->tax_id, $reg->state_code, $reg->is_default])->toBe([$gstin, '32', true]);

    // The option for the year is recorded once.
    $this->post(route('settings.tax.years.store', $reg), ['fy_key' => '2026-27', 'gta_option' => 'fcm_18', 'annexure_ref' => 'ARN-AA3210'])->assertSessionHasNoErrors();
    $this->post(route('settings.tax.years.store', $reg), ['fy_key' => '2026-27', 'gta_option' => 'rcm_5'])->assertSessionHasErrors('gta_option');
    app(CompanyContext::class)->set($company);
    expect($reg->fresh()->gtaOption(CarbonImmutable::parse('2026-10-25')))->toBe('fcm_18');

    // A new rate closes the one before, the day before.
    $code = TaxCode::where('code', 'GST_18')->first();
    $this->post(route('settings.tax.rates.store', $code), ['rate' => 16, 'valid_from' => '2017-01-01'])->assertSessionHasErrors('valid_from');
    $this->post(route('settings.tax.rates.store', $code), ['rate' => 16, 'valid_from' => '2027-04-01', 'note' => 'Budget 2027 (example)'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($company);
    $rates = $code->rates()->get();
    expect($rates->pluck('valid_to', 'rate')->map(fn ($d) => $d?->toDateString())->all())->toBe(['16.000000' => null, '18.000000' => '2027-03-31'])
        ->and($rates->first()->parts['intra'])->toBe(['CGST' => 8, 'SGST' => 8]);

    // A rule pointed at another code.
    $rule = \App\Modules\Tax\Models\TaxRule::where('service_class', 'WAREHOUSING')->first();
    $this->put(route('settings.tax.rule.update', $rule), ['tax_code' => 'GST_5', 'sac_code' => '996729', 'active' => '1'])->assertSessionHasNoErrors();
    $this->put(route('settings.tax.rule.update', $rule), ['tax_code' => 'VAT_5'])->assertSessionHasErrors('tax_code');

    // Try a case on the page.
    $buyer = app(CompanyContext::class)->runAs($company, fn () => taxBuyer('32'));
    $this->get(route('settings.tax', ['try' => 1, 'registration_id' => $reg->id, 'party_id' => $buyer->id, 'service_class' => 'FREIGHT_ROAD', 'amount' => 50000, 'date' => '2026-10-25']))
        ->assertOk()->assertSeeText('CGST 9%')->assertSeeText('₹59,000.00')->assertSeeText('Place of supply Kerala');
});

it('shows Settings → Tax only to people allowed to change tax', function () {
    $company = makeCompany();
    signInTo($company, 'viewer');
    $this->get(route('settings.tax'))->assertForbidden();

    signInTo($company, 'accountant');
    $this->get(route('settings.tax'))->assertOk();
});
