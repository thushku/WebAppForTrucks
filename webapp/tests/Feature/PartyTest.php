<?php

use App\Modules\Parties\Models\Party;
use App\Modules\Parties\Models\PartyBankAccount;
use App\Modules\Parties\Models\PartyTaxId;
use App\Modules\Parties\Models\RateContract;
use App\Modules\Parties\Models\SupplierVehicle;
use App\Modules\Parties\Services\PartyService;
use App\Modules\Parties\Services\RateFinder;
use App\Modules\Parties\Services\TaxIds;
use App\Modules\Settings\Models\Place;
use App\Platform\Audit\AuditLog;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Facades\DB;

// Parties (M3): one firm with many roles, tax registrations, contacts, bank
// accounts, hired trucks and rate contracts.

/** A valid GSTIN for a state and PAN (the check character worked out). */
function gstinFor(string $state, string $pan = 'AAACA1234F', string $entity = '1'): string
{
    $first14 = $state . $pan . $entity . 'Z';

    return $first14 . TaxIds::gstinCheckCharacter($first14);
}

it('checks GSTIN, PAN and TRN numbers', function () {
    expect(TaxIds::isGstin('27AAPFU0939F1ZV'))->toBeTrue()
        ->and(TaxIds::isGstin('27AAPFU0939F1ZW'))->toBeFalse()
        ->and(TaxIds::isGstin('27 aapfu0939f1zv'))->toBeTrue()
        ->and(TaxIds::isGstin(gstinFor('32')))->toBeTrue()
        ->and(TaxIds::isGstin(gstinFor('25')))->toBeFalse()
        ->and(TaxIds::gstinState('32AAACA1234F1Z5'))->toBe('32')
        ->and(TaxIds::isPan('AAACA1234F'))->toBeTrue()
        ->and(TaxIds::isPan('AAAXA1234F'))->toBeFalse()
        ->and(TaxIds::isTrn('100123456700003'))->toBeTrue()
        ->and(TaxIds::isTrn('200123456700003'))->toBeFalse();
});

it('adds a client with a GSTIN, filling its state and PAN', function () {
    $company = makeCompany();
    signInTo($company);
    $gstin = gstinFor('32');

    $this->post(route('parties.store'), [
        'name' => 'ACC Limited', 'short_code' => 'acc', 'roles' => ['client', 'consignee'], 'tax_id' => strtolower($gstin),
        'mobile' => '98470 12345', 'credit_days' => 30, 'credit_limit' => 500000, 'due_basis' => 'submission',
    ])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($company);
    $party = Party::with('roles', 'taxIds')->firstWhere('name', 'ACC Limited');
    expect($party->short_code)->toBe('ACC')
        ->and($party->roleCodes())->toEqualCanonicalizing(['client', 'consignee'])
        ->and($party->mobile)->toBe('+919847012345')
        ->and($party->state_code)->toBe('32')
        ->and($party->pan)->toBe('AAACA1234F')
        ->and($party->primaryTaxId()->tax_id)->toBe($gstin)
        ->and($party->primaryTaxId()->is_default)->toBeTrue();

    $this->get(route('parties.show', $party))->assertOk()->assertSee($gstin)->assertSee('Kerala');
});

it('refuses a wrong GSTIN, a PAN that does not match, and a GSTIN another party has', function () {
    $company = makeCompany();
    signInTo($company);
    $gstin = gstinFor('27');

    $this->post(route('parties.store'), ['name' => 'Typo Co', 'roles' => ['client'], 'tax_id' => substr($gstin, 0, 14) . 'Q'])->assertSessionHasErrors('tax_id');
    $this->post(route('parties.store'), ['name' => 'Mismatch Co', 'roles' => ['client'], 'tax_id' => $gstin, 'pan' => 'BBBCB1234F'])->assertSessionHasErrors('pan');
    $this->post(route('parties.store'), ['name' => 'First Co', 'roles' => ['client'], 'tax_id' => $gstin])->assertSessionHasNoErrors();
    $this->post(route('parties.store'), ['name' => 'Second Co', 'roles' => ['client'], 'tax_id' => $gstin])->assertSessionHasErrors('tax_id');

    // The same GSTIN may be a party in another company.
    $other = makeCompany(['name' => 'Other Transport']);
    signInTo($other);
    $this->post(route('parties.store'), ['name' => 'First Co', 'roles' => ['client'], 'tax_id' => $gstin])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($company);
    expect(Party::count())->toBe(1);
});

it('keeps several registrations, one of them the main one', function () {
    $company = makeCompany();
    signInTo($company);
    app(CompanyContext::class)->set($company);
    $party = app(PartyService::class)->save(null, ['name' => 'UltraTech', 'country' => 'IN'], ['client'], gstinFor('27', 'AAACU1234B'));

    $this->post(route('parties.tax-ids.store', $party), ['tax_id' => gstinFor('29', 'AAACU1234B'), 'address' => 'Plant, Karnataka'])->assertSessionHasNoErrors();
    $this->post(route('parties.tax-ids.store', $party), ['tax_id' => gstinFor('33', 'ZZZCZ1234Z')])->assertSessionHasErrors('tax_id');

    app(CompanyContext::class)->set($company);
    $karnataka = PartyTaxId::where('state_code', '29')->first();
    $main      = PartyTaxId::where('state_code', '27')->first();

    $this->post(route('parties.tax-ids.toggle', [$party, $main]))->assertSessionHas('error');
    $this->post(route('parties.tax-ids.default', [$party, $karnataka]));

    app(CompanyContext::class)->set($company);
    expect(PartyTaxId::where('is_default', true)->pluck('state_code')->all())->toBe(['29']);
});

it('lists parties by role and finds them by GSTIN', function () {
    $company = makeCompany();
    signInTo($company);
    app(CompanyContext::class)->set($company);
    app(PartyService::class)->save(null, ['name' => 'ACC Limited', 'country' => 'IN'], ['client'], gstinFor('32'));
    makeParty(['name' => 'Indian Oil Pump Aluva', 'vendor_category' => 'fuel_pump'], ['vendor']);
    makeParty(['name' => 'Raju Transport'], ['supplier']);

    $this->get(route('parties.index', ['role' => 'vendor']))->assertOk()->assertSee('Indian Oil Pump Aluva')->assertDontSee('Raju Transport');
    $this->get(route('parties.index', ['q' => substr(gstinFor('32'), 0, 8)]))->assertOk()->assertSee('ACC Limited')->assertDontSee('Raju Transport');
    $this->getJson(route('parties.lookup', ['q' => 'raju', 'role' => 'supplier']))->assertJsonPath('0.name', 'Raju Transport');
});

it('lets a viewer look but not change, and hides other companies\' parties', function () {
    $company = makeCompany();
    $other   = makeCompany(['name' => 'Other Transport']);
    $theirs  = makeParty(['name' => 'Their Client'], ['client'], $other);
    $mine    = makeParty(['name' => 'My Client'], ['client'], $company);

    signInTo($company, 'viewer');
    $this->get(route('parties.show', $mine))->assertOk();
    $this->get(route('parties.create'))->assertForbidden();
    $this->put(route('parties.update', $mine), ['name' => 'Changed', 'roles' => ['client']])->assertForbidden();
    $this->get(route('parties.show', $theirs))->assertNotFound();
});

it('changes roles and records it, but never leaves a party with none', function () {
    $company = makeCompany();
    signInTo($company);
    $party = makeParty(['name' => 'Sharma Traders'], ['client'], $company);

    $this->put(route('parties.update', $party), ['name' => 'Sharma Traders', 'roles' => [], 'active' => '1'])->assertSessionHasErrors('roles');
    $this->put(route('parties.update', $party), ['name' => 'Sharma Traders', 'roles' => ['client', 'vendor'], 'active' => '1', 'vendor_category' => 'spares'])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($company);
    expect($party->fresh()->roleCodes())->toEqualCanonicalizing(['client', 'vendor'])
        ->and(AuditLog::where('action', 'party.roles_changed')->exists())->toBeTrue();
});

it('needs a date for an opening balance and a reason for a blacklist', function () {
    $company = makeCompany();
    signInTo($company);

    $this->post(route('parties.store'), ['name' => 'Old Client', 'roles' => ['client'], 'opening_balance' => 18750])->assertSessionHasErrors('opening_as_of');
    $this->post(route('parties.store'), ['name' => 'Bad Supplier', 'roles' => ['supplier'], 'blacklisted' => '1'])->assertSessionHasErrors('blacklist_reason');
    $this->post(route('parties.store'), ['name' => 'Old Client', 'roles' => ['client'], 'opening_balance' => 18750, 'opening_side' => 'receivable', 'opening_as_of' => '2026-04-01'])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($company);
    expect(Party::firstWhere('name', 'Old Client')->openingSigned())->toBe(18750.0);
});

it('suggests a free short code from the name', function () {
    $company = makeCompany();
    signInTo($company);
    makeParty(['name' => 'Sharma Traders', 'short_code' => 'SHA'], ['client'], $company);

    $this->getJson(route('parties.suggest', ['name' => 'ACC Limited']))->assertJson(['code' => 'ACC']);
    $this->getJson(route('parties.suggest', ['name' => 'Sharma Logistics']))->assertJson(['code' => 'SHA2']);
});

it('stores bank accounts encrypted and only for people allowed to change bank details', function () {
    $company = makeCompany();
    $party   = makeParty(['name' => 'Raju Transport'], ['supplier'], $company);

    signInTo($company, 'manager');
    $this->post(route('parties.banks.store', $party), ['account_holder_name' => 'Raju K', 'account_number' => '50100123454321', 'ifsc' => 'HDFC0001234'])->assertForbidden();

    signInTo($company);
    $this->post(route('parties.banks.store', $party), ['account_holder_name' => 'Raju K', 'account_number' => '50100123454321'])->assertSessionHasErrors('ifsc');
    $this->post(route('parties.banks.store', $party), ['account_holder_name' => 'Raju K', 'bank_name' => 'HDFC Bank', 'account_number' => '50100123454321', 'ifsc' => 'hdfc0001234', 'upi_id' => 'raju@okhdfc'])->assertSessionHasNoErrors();

    $raw = DB::table('party_bank_accounts')->first();
    expect($raw->account_number)->not->toContain('50100123454321')->and($raw->account_last4)->toBe('4321')->and($raw->ifsc)->toBe('HDFC0001234');

    app(CompanyContext::class)->set($company);
    $bank = PartyBankAccount::first();
    expect($bank->account_number)->toBe('50100123454321')->and($bank->maskedUpi())->toBe('r***@okhdfc');

    $entry = AuditLog::where('action', 'party_bank_account.created')->first();
    expect($entry->severity)->toBe('sensitive')->and(json_encode($entry->change_set))->not->toContain('50100123454321')->not->toContain('raju@okhdfc');

    $this->get(route('parties.show', $party))->assertSee('HDFC Bank ···· 4321')->assertDontSee('50100123454321');
});

it('adds contacts with checked mobile numbers', function () {
    $company = makeCompany();
    signInTo($company);
    $party = makeParty([], ['client'], $company);

    $this->post(route('parties.contacts.store', $party), ['name' => 'Suresh (dispatch)', 'role' => 'dispatch', 'mobile' => '12345'])->assertSessionHasErrors('mobile');
    $this->post(route('parties.contacts.store', $party), ['name' => 'Suresh (dispatch)', 'role' => 'dispatch', 'mobile' => '9847012345', 'is_primary' => '1'])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($company);
    $contact = $party->contacts()->first();
    expect($contact->mobile)->toBe('+919847012345')->and($contact->is_primary)->toBeTrue();

    $this->delete(route('parties.contacts.destroy', [$party, $contact]))->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($company);
    expect($party->contacts()->count())->toBe(0);
});

it('lists a hired truck under one supplier only, however it is typed', function () {
    $company = makeCompany();
    signInTo($company);
    $raju  = makeParty(['name' => 'Raju Transport'], ['supplier'], $company);
    $other = makeParty(['name' => 'Kumar Roadways'], ['supplier'], $company);

    $this->post(route('parties.vehicles.store', $raju), ['reg_no' => 'kl-07-ab-1234', 'rc_owner_name' => 'Raju K', 'capacity_mt' => 25])->assertSessionHasNoErrors();
    $this->post(route('parties.vehicles.store', $other), ['reg_no' => 'KL 07 AB 1234'])->assertSessionHasErrors('reg_no');

    app(CompanyContext::class)->set($company);
    expect(SupplierVehicle::first()->reg_no)->toBe('KL-07-AB-1234')->and(SupplierVehicle::first()->reg_key)->toBe('KL07AB1234');
});

it('makes, approves and versions a rate contract, and finds the most specific rate', function () {
    $company = makeCompany();
    signInTo($company);
    app(CompanyContext::class)->set($company);
    $client = makeParty(['name' => 'ACC Limited'], ['client']);
    $cok = Place::create(['name' => 'Kochi', 'short_code' => 'COK']);
    $bom = Place::create(['name' => 'Mumbai', 'short_code' => 'BOM']);

    $this->post(route('parties.rates.store', $client), [
        'name' => 'Rates 2026', 'valid_from' => '2026-10-01',
        'lines' => [
            ['from_place_id' => $cok->id, 'to_place_id' => $bom->id, 'basis' => 'tonne', 'rate' => '3200', 'min_weight_mt' => '20'],
            ['from_place_id' => $cok->id, 'to_place_id' => $bom->id, 'material' => 'cement', 'truck_type' => 'w12', 'basis' => 'tonne', 'rate' => '3400', 'min_weight_mt' => '21'],
            ['from_place_id' => '', 'to_place_id' => '', 'rate' => ''],
        ],
    ])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($company);
    $contract = RateContract::first();
    expect($contract->status)->toBe('draft')->and($contract->lines()->count())->toBe(2);

    // A draft does not count yet.
    $finder = app(RateFinder::class);
    expect($finder->find($client->id, $cok->id, $bom->id, 'cement', 'w12', now()))->toBeNull();

    $this->post(route('parties.rates.approve', [$client, $contract]))->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($company);

    $specific = $finder->find($client->id, $cok->id, $bom->id, 'cement', 'w12', now());
    $generic  = $finder->find($client->id, $cok->id, $bom->id, 'steel', 'w10', now());
    expect((float) $specific->rate)->toBe(3400.0)
        ->and((float) $generic->rate)->toBe(3200.0)
        ->and($finder->freight($specific, 18.5))->toBe(71400.0)   // charged at the 21 MT minimum
        ->and($finder->freight($specific, 24.0))->toBe(81600.0)
        ->and($finder->find($client->id, $bom->id, $cok->id, null, null, now()))->toBeNull();

    // Approved rates are not edited; a new version takes over from its date.
    $this->put(route('parties.rates.update', [$client, $contract]), ['name' => 'Rates 2026', 'valid_from' => '2026-10-01'])->assertSessionHas('error');
    $this->post(route('parties.rates.version', [$client, $contract]), ['valid_from' => '2026-11-01'])->assertRedirect();

    app(CompanyContext::class)->set($company);
    $v2 = RateContract::where('version', 2)->first();
    $v2->lines()->where('material', 'cement')->update(['rate' => 3500]);
    app(PartyService::class)->approve($v2);

    expect($contract->fresh()->valid_to->toDateString())->toBe('2026-10-31')
        ->and((float) $finder->find($client->id, $cok->id, $bom->id, 'cement', 'w12', now())->rate)->toBe(3400.0)
        ->and((float) $finder->find($client->id, $cok->id, $bom->id, 'cement', 'w12', now()->setDate(2026, 11, 5))->rate)->toBe(3500.0);
});

it('refuses half-filled or repeated rate rows', function () {
    $company = makeCompany();
    signInTo($company);
    app(CompanyContext::class)->set($company);
    $client = makeParty([], ['client']);
    $cok = Place::create(['name' => 'Kochi', 'short_code' => 'COK']);
    $bom = Place::create(['name' => 'Mumbai', 'short_code' => 'BOM']);
    $other = makeCompany(['name' => 'Other Transport']);
    $foreign = app(CompanyContext::class)->runAs($other, fn () => Place::create(['name' => 'Pune', 'short_code' => 'PNQ']));

    $this->post(route('parties.rates.store', $client), ['name' => 'Rates', 'valid_from' => '2026-10-01', 'lines' => [['from_place_id' => $cok->id, 'rate' => '100']]])->assertSessionHasErrors('lines.0.to_place_id');
    $this->post(route('parties.rates.store', $client), ['name' => 'Rates', 'valid_from' => '2026-10-01', 'lines' => [
        ['from_place_id' => $cok->id, 'to_place_id' => $bom->id, 'rate' => '100'],
        ['from_place_id' => $cok->id, 'to_place_id' => $bom->id, 'rate' => '200'],
    ]])->assertSessionHasErrors('lines.1.from_place_id');
    $this->post(route('parties.rates.store', $client), ['name' => 'Rates', 'valid_from' => '2026-10-01', 'lines' => [['from_place_id' => $foreign->id, 'to_place_id' => $bom->id, 'rate' => '100']]])->assertSessionHasErrors('lines.0.from_place_id');
});
