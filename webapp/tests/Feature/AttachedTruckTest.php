<?php

use App\Modules\Accounts\Models\Voucher;
use App\Modules\Accounts\Services\Statements;
use App\Modules\Fleet\Services\Fines;
use App\Modules\Trips\Models\HireChallan;
use App\Modules\Trips\Services\TripService;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Facades\DB;

// Attached trucks (M49): the owner's truck runs our trips. Each trip's
// owner's challan is drafted with the loads' freight as the hire and our
// commission kept back at the truck's rate; what we spent on the trip is
// the owner's and is charged back as a deduction. The trip earns us the
// commission; the truck page shows what we owe the owner.

/** An attached truck of Joseph (an individual) at 8 %, its ended trip with ₹51,000 of freight and a ₹500 fine we paid on the way; and an own truck's trip. */
function atWorld(): array
{
    $company = makeCompany();
    $owner = signInTo($company);

    return app(CompanyContext::class)->runAs($company, function () use ($company, $owner) {
        auth()->setUser($owner);
        $joseph = makeParty(['name' => 'Joseph Mathew', 'pan' => 'ABCPJ1234K'], ['supplier']);
        $acc = makeParty(['name' => 'ACC Limited', 'short_code' => 'ACC'], ['client', 'consignor']);
        $truck = makeTruck(['reg_no' => 'KL-41-J-7070', 'ownership' => 'attached', 'owner_party_id' => $joseph->id, 'attached_commission_pct' => 8, 'odometer_km' => 50000]);
        $own = makeTruck(['reg_no' => 'KL-07-AB-1234', 'odometer_km' => 90000]);
        $driver = makePerson(['name' => 'Thomas Varghese', 'mobile' => '+919847055555', 'licence_no' => 'KL4120180012345', 'licence_expiry' => '2030-01-01']);
        $cok = \App\Modules\Settings\Models\Place::create(['name' => 'Kochi', 'short_code' => 'COK']);
        $bom = \App\Modules\Settings\Models\Place::create(['name' => 'Mumbai', 'short_code' => 'BOM']);
        $trips = app(TripService::class);
        $load = ['billing_party_id' => $acc->id, 'consignor_party_id' => $acc->id, 'material' => 'cement', 'actual_weight_mt' => 15, 'freight_basis' => 'trip', 'rate' => 50000,
            'freight' => 50000, 'loading_charge' => 1000, 'payment_type' => 'to_be_billed'];
        $trip = $trips->create(['trip_date' => '2026-10-25', 'truck_id' => $truck->id, 'from_place_id' => $cok->id, 'to_place_id' => $bom->id], [['person_id' => $driver->id, 'role' => 'driver']], $load);
        $trips->assign($trip->fresh());
        $trips->start($trip->fresh(), 50100);
        DB::table('trips')->where('id', $trip->id)->update(['started_at' => '2026-10-25 03:00:00']);
        $fines = app(Fines::class);
        $fine = $fines->record(['truck_id' => $truck->id, 'issued_at' => '2026-10-25 10:00', 'offence' => 'No entry', 'amount' => 500, 'bears' => 'company'], $owner);
        $fines->pay($fine, ['paid_on' => '2026-10-25', 'mode' => 'cash'], $owner);
        $trips->end($trip->fresh(), 51500);
        $ourDriver = makePerson(['name' => 'Ravi Kumar', 'mobile' => '+919847066666', 'licence_no' => 'KL0720150012345', 'licence_expiry' => '2030-01-01']);
        $ownTrip = $trips->create(['trip_date' => '2026-10-25', 'truck_id' => $own->id, 'from_place_id' => $cok->id, 'to_place_id' => $bom->id], [['person_id' => $ourDriver->id, 'role' => 'driver']], $load);

        return ['company' => $company, 'owner' => $owner, 'joseph' => $joseph, 'truck' => $truck, 'trip' => $trip->fresh(), 'own' => $ownTrip];
    });
}

it('pays an attached truck’s owner the freight less our commission and charges back what we spent', function () {
    $w = atWorld();
    $trip = $w['trip'];

    $this->get(route('trips.show', $trip))->assertOk()->assertSeeText('Attached truck')->assertSeeText('Owner: Joseph Mathew')->assertSeeText('Make the owner’s challan');
    $this->get(route('dashboard'))->assertSeeText('1 attached truck’s trip has no owner’s challan');
    $this->get(route('trips.index', ['attached' => 'unpaid', 'status' => 'all']))->assertOk()->assertSeeText($trip->trip_number);

    // The draft: ₹51,000 of freight and charges, our 8 % (₹4,080) kept back, 1 % TDS for an individual.
    $this->get(route('trips.hire.create', $trip))->assertOk()->assertSeeText('Attached truck — the owner: Joseph Mathew')
        ->assertSee('value="51000.00"', false)->assertSee('value="4080.00"', false)->assertSee('value="KL-41-J-7070"', false)->assertSee('value="Thomas Varghese"', false);
    $this->post(route('trips.hire.store', $trip), ['challan_date' => '2026-10-25', 'vehicle_reg' => 'KL-41-J-7070', 'hire_basis' => 'fixed', 'hire_amount' => 51000,
        'commission_amount' => 4080, 'commission_mode' => 'deducted', 'tds_section' => '393(1)', 'tds_rate' => 1])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $challan = HireChallan::sole();
    expect($challan->supplier_party_id)->toBe($w['joseph']->id);
    $this->get(route('trips.hire.edit', [$trip, $challan]))->assertSeeText('Spent by us on this trip')->assertSeeText('Issue the challan to charge it.');
    $this->post(route('trips.hire.issue', [$trip, $challan]))->assertSessionHasNoErrors();

    // What we spent (the ₹500 fine) is charged back to the owner with one tap.
    $this->get(route('trips.hire.edit', [$trip, $challan]))->assertSeeText('Charge ₹500.00 to the owner');
    $this->post(route('trips.hire.lines.store', [$trip, $challan]), ['kind' => 'deduction', 'amount' => 500, 'line_date' => '2026-10-25', 'note' => 'Spent by us on the trip'])->assertSessionHasNoErrors();
    $this->get(route('trips.hire.edit', [$trip, $challan]))->assertSeeText('Charged to the owner so far')->assertDontSeeText('Charge ₹');

    // The trip earns us our commission once its ₹51,000 is billed (freight is income at billing): the hire, the fine and its
    // charge-back cancel out. The owner is owed the rest less TDS and the charge-back.
    app(CompanyContext::class)->set($w['company']);
    expect(app(Statements::class)->tripProfit($trip->id)['profit'] + 51000)->toEqual(4080.0)
        ->and(Voucher::where('source_type', 'hire_challan')->count())->toBe(1);
    $this->get(route('trucks.show', $w['truck']))->assertOk()->assertSeeText('Attached; owner')->assertSeeText('our commission 8%')->assertSeeText('We owe the owner ₹45,910.00');
    $this->get(route('dashboard'))->assertDontSeeText('has no owner’s challan');

    // An own truck's trip has no hire challan.
    $this->get(route('trips.hire.create', $w['own']))->assertNotFound();
});

it('gives the owner app the owner’s challan of an attached truck’s trip', function () {
    $w = atWorld();
    phoneReset();
    $this->withHeaders(['Authorization' => 'Bearer ' . $w['owner']->createToken('owner:android')->plainTextToken, 'X-Company' => $w['company']->uuid, 'Accept' => 'application/json']);

    $this->getJson('/api/v1/owner/trips/' . $w['trip']->uuid . '/hire')->assertOk()->assertJsonPath('attached', true)->assertJsonPath('supplier.name', 'Joseph Mathew')
        ->assertJsonPath('new.hire_amount', 51000)->assertJsonPath('new.commission_amount', 4080)->assertJsonPath('new.tds_rate', 1)
        ->assertJsonPath('spent.0.amount', 500)->assertJsonPath('charged', 0);
    $this->getJson('/api/v1/owner/trips/' . $w['own']->uuid . '/hire')->assertNotFound();
});
