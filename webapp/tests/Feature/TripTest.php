<?php

use App\Modules\Documents\Services\DocumentService;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Parties\Models\RateContract;
use App\Modules\Parties\Models\SupplierVehicle;
use App\Modules\Settings\Models\Place;
use App\Modules\Trips\Models\BrokerCommission;
use App\Modules\Trips\Models\Consignment;
use App\Modules\Trips\Models\HireChallan;
use App\Modules\Trips\Models\Trip;
use App\Modules\Trips\Services\LoadPlan;
use App\Platform\Audit\AuditLog;
use App\Platform\Tenancy\CompanyContext;

// Trips (M5): full and part loads, the dispatch gate, numbers, hired trucks.

/** Places, a client with a short code, a truck and a driver, ready for a trip. */
function tripWorld($company): array
{
    return app(CompanyContext::class)->runAs($company, fn () => [
        'cok'    => Place::create(['name' => 'Kochi', 'short_code' => 'COK']),
        'bom'    => Place::create(['name' => 'Mumbai', 'short_code' => 'BOM']),
        'pnq'    => Place::create(['name' => 'Pune', 'short_code' => 'PNQ']),
        'acc'    => makeParty(['name' => 'ACC Limited', 'short_code' => 'ACC'], ['client', 'consignor']),
        'truck'  => makeTruck(['payload_mt' => 25, 'odometer_km' => 100000]),
        'driver' => makePerson(['name' => 'Ravi Kumar', 'licence_no' => 'KL0720150012345', 'licence_expiry' => '2030-01-01']),
        'helper' => makePerson(['name' => 'Binu Joseph', 'type' => 'helper', 'mobile' => '+919847022222']),
    ]);
}

function tripPost(array $w, array $override = [], array $load = []): array
{
    return array_replace_recursive([
        'trip_date' => '2026-10-25', 'load_type' => 'ftl', 'trip_kind' => 'loaded', 'truck_source' => 'own',
        'truck_id' => $w['truck']->id, 'driver_id' => $w['driver']->id, 'helper_ids' => [$w['helper']->id],
        'from_place_id' => $w['cok']->id, 'to_place_id' => $w['bom']->id, 'planned_km' => 1380,
        'load' => array_merge(['billing_party_id' => $w['acc']->id, 'consignor_party_id' => $w['acc']->id, 'material' => 'cement',
            'actual_weight_mt' => '18.5', 'freight_basis' => 'tonne', 'rate' => '3400', 'payment_type' => 'to_be_billed', 'gst_payable_by' => 'consignor'], $load),
    ], $override);
}

it('plans a full-load trip as a draft with its load, stops and crew', function () {
    $company = makeCompany();
    signInTo($company);
    $w = tripWorld($company);

    $this->post(route('trips.store'), tripPost($w, [], ['eway_no' => '331001234567', 'eway_valid_until' => '2026-10-28']))->assertSessionHasNoErrors()->assertRedirect();

    app(CompanyContext::class)->set($company);
    $trip = Trip::with('crew', 'stops', 'consignments.ewaybills')->first();
    $load = $trip->consignments->first();
    expect($trip->status)->toBe('draft')->and($trip->trip_number)->toBeNull()
        ->and($trip->stops->pluck('seq')->all())->toBe([1, 2])
        ->and($trip->crew->pluck('role', 'person_id')->all())->toBe([$w['driver']->id => 'driver', $w['helper']->id => 'helper'])
        ->and((float) $load->freight)->toBe(62900.0)
        ->and($load->lr_number)->toBeNull()
        ->and($load->ewaybills->first()->number)->toBe('331001234567');

    $this->get(route('trips.show', $trip))->assertOk()->assertSee('Draft trip')->assertSee('Truck and crew are free');
});

it('uses the rate contract and its minimum weight for the freight', function () {
    $company = makeCompany();
    signInTo($company);
    $w = tripWorld($company);
    $line = app(CompanyContext::class)->runAs($company, function () use ($w) {
        $c = RateContract::create(['party_id' => $w['acc']->id, 'name' => 'Cement', 'valid_from' => '2026-10-01', 'status' => 'approved']);

        return $c->lines()->create(['from_place_id' => $w['cok']->id, 'to_place_id' => $w['bom']->id, 'material' => 'cement', 'basis' => 'tonne', 'rate' => 3400, 'min_weight_mt' => 21]);
    });

    $this->getJson(route('trips.rate', ['party' => $w['acc']->id, 'from' => $w['cok']->id, 'to' => $w['bom']->id, 'material' => 'cement', 'truck' => $w['truck']->id, 'date' => '2026-10-25']))
        ->assertJsonPath('rate.rate', 3400)->assertJsonPath('rate.min_weight', 21)->assertJsonPath('rate.line_id', $line->id);

    $this->post(route('trips.store'), tripPost($w, [], ['rate_contract_line_id' => $line->id]))->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($company);
    expect((float) Consignment::first()->freight)->toBe(71400.0);
});

it('checks the truck, the driver and the crew before saving', function () {
    $company = makeCompany();
    signInTo($company);
    $w = tripWorld($company);

    $this->post(route('trips.store'), tripPost($w, ['driver_id' => null]))->assertSessionHasErrors('driver_id');
    $this->post(route('trips.store'), tripPost($w, ['to_place_id' => $w['cok']->id]))->assertSessionHasErrors('to_place_id');
    $this->post(route('trips.store'), tripPost($w, ['co_driver_id' => $w['driver']->id]))->assertSessionHasErrors('co_driver_id');
    $this->post(route('trips.store'), tripPost($w, [], ['freight_basis' => 'tonne', 'actual_weight_mt' => null]))->assertSessionHasErrors('load.actual_weight_mt');
    $this->post(route('trips.store'), tripPost($w, [], ['billing_party_id' => null]))->assertSessionHasErrors('load.billing_party_id');
});

it('sends a trip to the crew: number from the short codes, LR numbers, and the dispatch gate', function () {
    $company = makeCompany();
    signInTo($company);
    $w = tripWorld($company);
    $this->post(route('trips.store'), tripPost($w));
    app(CompanyContext::class)->set($company);
    $trip = Trip::first();
    // The truck's insurance expired: dispatch-blocking.
    app(DocumentService::class)->create('truck', $w['truck']->id, ['document_type' => 'insurance', 'expires_on' => '2026-10-20']);

    $this->post(route('trips.assign', $trip))->assertSessionHasErrors('override_reason');
    $this->post(route('trips.assign', $trip), ['override_reason' => 'short'])->assertSessionHasErrors('override_reason');
    $this->post(route('trips.assign', $trip), ['override_reason' => 'Renewal paid today, copy by evening'])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($company);
    $trip->refresh();
    expect($trip->status)->toBe('assigned')
        ->and($trip->trip_number)->toBe('261025-ACC-COK-BOM-01')
        ->and($trip->dispatch_override_reason)->toBe('Renewal paid today, copy by evening')
        ->and(Consignment::first()->lr_number)->toBe('LR/26-27/00001')
        ->and(AuditLog::where('action', 'trip.dispatch_override')->value('severity'))->toBe('sensitive');
});

it('will not send a truck or a driver that is already on a trip', function () {
    $company = makeCompany();
    signInTo($company);
    $w = tripWorld($company);
    $this->post(route('trips.store'), tripPost($w));
    $this->post(route('trips.store'), tripPost($w));
    app(CompanyContext::class)->set($company);
    [$first, $second] = Trip::orderBy('id')->get();

    $this->post(route('trips.assign', $first))->assertSessionHasNoErrors();
    $this->post(route('trips.assign', $second))->assertSessionHasErrors('trip');
    expect($second->fresh()->status)->toBe('draft');

    $this->get(route('trips.show', $second))->assertSee('is on trip 261025-ACC-COK-BOM-01');
});

it('blocks a driver without a valid licence but not a helper', function () {
    $company = makeCompany();
    signInTo($company);
    $w = tripWorld($company);
    app(CompanyContext::class)->runAs($company, fn () => $w['driver']->forceFill(['licence_expiry' => '2026-10-01'])->save());
    $this->post(route('trips.store'), tripPost($w));
    app(CompanyContext::class)->set($company);
    $trip = Trip::first();

    $check = app(\App\Modules\Trips\Services\TripService::class)->dispatchCheck($trip);
    expect(collect($check['papers'])->pluck('who')->all())->toBe(['Ravi Kumar']);
});

it('numbers an empty run without a load', function () {
    $company = makeCompany();
    signInTo($company);
    $w = tripWorld($company);

    $this->post(route('trips.store'), tripPost($w, ['trip_kind' => 'empty']))->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($company);
    $trip = Trip::first();
    expect($trip->consignments()->count())->toBe(0);

    $this->post(route('trips.assign', $trip))->assertSessionHasNoErrors();
    expect($trip->fresh()->trip_number)->toBe('261025-MTY-COK-BOM-01');
});

it('runs a part-load trip: stops, loads picked and dropped on the way, and the load per leg', function () {
    $company = makeCompany();
    signInTo($company);
    $w = tripWorld($company);
    $this->post(route('trips.store'), tripPost($w, ['load_type' => 'ptl'], ['actual_weight_mt' => '12']));
    app(CompanyContext::class)->set($company);
    $trip = Trip::first();

    $this->post(route('trips.stops.store', $trip), ['place_id' => $w['pnq']->id, 'kind' => 'both', 'after_seq' => 1])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($company);
    $stops = $trip->stops()->with('place')->get();
    expect($stops->pluck('place.short_code')->all())->toBe(['COK', 'PNQ', 'BOM']);

    $other = makeParty(['name' => 'Kerala Agro', 'short_code' => 'KAF'], ['client'], $company);
    $base = ['billing_party_id' => $other->id, 'material' => 'food_grains', 'freight_basis' => 'tonne', 'rate' => '1800', 'payment_type' => 'to_pay'];
    $this->post(route('trips.loads.store', $trip), $base + ['actual_weight_mt' => '10', 'pickup_stop_id' => $stops[1]->id, 'drop_stop_id' => $stops[0]->id])->assertSessionHasErrors('drop_stop_id');
    $this->post(route('trips.loads.store', $trip), $base + ['actual_weight_mt' => '10', 'pickup_stop_id' => $stops[0]->id, 'drop_stop_id' => $stops[1]->id])->assertSessionHasNoErrors();
    $this->post(route('trips.loads.store', $trip), $base + ['actual_weight_mt' => '9', 'pickup_stop_id' => $stops[1]->id, 'drop_stop_id' => $stops[2]->id])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($company);
    $legs = app(LoadPlan::class)->legs($trip->fresh());
    expect($legs)->toHaveCount(2)
        ->and($legs[0]['weight'])->toBe(22.0)->and($legs[0]['percent'])->toBe(88)
        ->and($legs[1]['weight'])->toBe(21.0)->and($legs[1]['loads'])->toBe(2);

    // A stop with a load cannot go.
    $this->delete(route('trips.stops.destroy', [$trip, $stops[1]]))->assertSessionHasErrors('stop');
});

it('starts and ends a trip, moving the truck and its loads along', function () {
    $company = makeCompany();
    signInTo($company);
    $w = tripWorld($company);
    $this->post(route('trips.store'), tripPost($w));
    app(CompanyContext::class)->set($company);
    $trip = Trip::first();

    $this->post(route('trips.start', $trip), ['start_odometer' => 100500])->assertSessionHasErrors('trip');
    $this->post(route('trips.assign', $trip));
    $this->post(route('trips.start', $trip), ['start_odometer' => 99000])->assertSessionHasErrors('start_odometer');
    $this->post(route('trips.start', $trip), ['start_odometer' => 100500])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($company);
    expect($trip->fresh()->status)->toBe('running')->and($w['truck']->fresh()->status)->toBe('on_trip')
        ->and(Consignment::first()->status)->toBe('in_transit');

    // A running trip's load is fixed; a new load still gets its LR at once.
    $load = Consignment::first();
    $this->put(route('trips.loads.update', [$trip, $load]), ['billing_party_id' => $w['acc']->id, 'freight_basis' => 'trip', 'rate' => 1, 'payment_type' => 'paid', 'pickup_stop_id' => $load->pickup_stop_id, 'drop_stop_id' => $load->drop_stop_id])->assertSessionHasErrors('consignment');
    $this->post(route('trips.cancel', $trip), ['reason' => 'Client changed plan'])->assertSessionHasErrors('trip');

    $this->post(route('trips.end', $trip), ['end_odometer' => 100400])->assertSessionHasErrors('end_odometer');
    $this->post(route('trips.end', $trip), ['end_odometer' => 101910])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($company);
    expect($trip->fresh()->status)->toBe('ended')->and($w['truck']->fresh()->status)->toBe('idle')
        ->and($w['truck']->fresh()->odometer_km)->toBe(101910)
        ->and(Consignment::first()->status)->toBe('delivered');
});

it('cancels a trip before it runs, keeping the LR numbers used', function () {
    $company = makeCompany();
    signInTo($company);
    $w = tripWorld($company);
    $this->post(route('trips.store'), tripPost($w));
    app(CompanyContext::class)->set($company);
    $trip = Trip::first();
    $this->post(route('trips.assign', $trip));

    $this->post(route('trips.cancel', $trip), ['reason' => 'x'])->assertSessionHasErrors('reason');
    $this->post(route('trips.cancel', $trip), ['reason' => 'Client cancelled the order'])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($company);
    expect($trip->fresh()->status)->toBe('cancelled')->and(Consignment::first()->status)->toBe('cancelled')->and(Consignment::first()->lr_number)->toBe('LR/26-27/00001');

    // The truck is free again for the next trip.
    $this->post(route('trips.store'), tripPost($w));
    app(CompanyContext::class)->set($company);
    $next = Trip::orderByDesc('id')->first();
    $this->post(route('trips.assign', $next))->assertSessionHasNoErrors();
    expect($next->fresh()->trip_number)->toBe('261025-ACC-COK-BOM-02');
});

it('hires a market truck: challan, TDS, commission, advances and the balance', function () {
    $company = makeCompany();
    signInTo($company);
    $w = tripWorld($company);
    [$raju, $vehicle, $broker, $other] = app(CompanyContext::class)->runAs($company, function () {
        $raju = makeParty(['name' => 'Raju Transport', 'pan' => 'ABCPR1234K'], ['supplier']);
        $vehicle = SupplierVehicle::create(['party_id' => $raju->id, 'reg_no' => 'MH-04-KL-7788', 'capacity_mt' => 21, 'driver_name' => 'Ganesh']);
        $broker = makeParty(['name' => 'Patil Brokers'], ['broker']);
        $other = makeParty(['name' => 'Other Supplier'], ['supplier']);

        return [$raju, $vehicle, $broker, $other];
    });

    $hired = ['truck_source' => 'hired', 'truck_id' => null, 'driver_id' => null, 'helper_ids' => [], 'supplier_party_id' => $other->id, 'supplier_vehicle_id' => $vehicle->id];
    $this->post(route('trips.store'), tripPost($w, $hired))->assertSessionHasErrors('supplier_vehicle_id');
    $this->post(route('trips.store'), tripPost($w, ['supplier_party_id' => $raju->id] + $hired))->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($company);
    $trip = Trip::first();
    expect($trip->truck_id)->toBeNull()->and($trip->vehicleLabel())->toBe('MH-04-KL-7788 (hired)');

    // Individual supplier (PAN 4th letter P): TDS 1 % suggested.
    $this->get(route('trips.hire.create', $trip))->assertOk()->assertSee('value="1.00"', false)->assertSee('Ganesh');

    $this->post(route('trips.hire.store', $trip), [
        'challan_date' => '2026-10-25', 'vehicle_reg' => 'MH-04-KL-7788', 'hire_basis' => 'fixed', 'hire_amount' => '52000',
        'broker_party_id' => $broker->id, 'commission_amount' => '1500', 'commission_mode' => 'deducted',
        'office_charge' => '200', 'tds_section' => '194C', 'tds_rate' => '1',
    ])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($company);
    $challan = HireChallan::first();
    expect((float) $challan->tds_amount)->toBe(520.0)->and($challan->balance())->toBe(49780.0)
        ->and(BrokerCommission::first()->direction)->toBe('deducted');

    $this->post(route('trips.hire.lines.store', [$trip, $challan]), ['kind' => 'advance', 'amount' => 20000, 'line_date' => '2026-10-25', 'mode' => 'bank'])->assertSessionHasErrors('challan');
    $this->post(route('trips.hire.issue', [$trip, $challan]))->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($company);
    expect($challan->fresh()->number)->toBe('HC/26-27/00001');

    $this->post(route('trips.hire.lines.store', [$trip, $challan]), ['kind' => 'advance', 'amount' => 60000, 'line_date' => '2026-10-25', 'mode' => 'bank'])->assertSessionHasErrors('amount');
    $this->post(route('trips.hire.lines.store', [$trip, $challan]), ['kind' => 'advance', 'amount' => 20000, 'line_date' => '2026-10-25', 'mode' => 'bank', 'paid_to' => 'owner', 'reference' => 'UTR123'])->assertSessionHasNoErrors();
    $this->post(route('trips.hire.lines.store', [$trip, $challan]), ['kind' => 'deduction', 'amount' => 780, 'line_date' => '2026-10-25'])->assertSessionHasErrors('note');
    $this->post(route('trips.hire.lines.store', [$trip, $challan]), ['kind' => 'deduction', 'amount' => 780, 'line_date' => '2026-10-25', 'note' => '3 bags short'])->assertSessionHasNoErrors();
    $this->post(route('trips.hire.lines.store', [$trip, $challan]), ['kind' => 'payment', 'amount' => 29000, 'line_date' => '2026-10-25', 'mode' => 'upi'])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($company);
    $challan = $challan->fresh();
    expect($challan->balance())->toBe(0.0)->and($challan->status)->toBe('settled');

    // An issued challan's hire is fixed.
    $this->put(route('trips.hire.update', [$trip, $challan]), ['challan_date' => '2026-10-25', 'vehicle_reg' => 'X', 'hire_basis' => 'fixed', 'hire_amount' => '1', 'commission_mode' => 'paid'])->assertSessionHasErrors('challan');
});

it('suggests no TDS when the supplier gave this year\'s 10-truck declaration', function () {
    $company = makeCompany();
    $raju = makeParty(['name' => 'Raju Transport', 'pan' => 'AABFR1234K'], ['supplier'], $company);
    $hire = fn () => app(\App\Modules\Trips\Services\HireService::class)->tdsFor($raju);

    app(CompanyContext::class)->set($company);
    expect($hire()['rate'])->toBe(2.0);

    app(DocumentService::class)->create('party', $raju->id, ['document_type' => 'no_tds_decl', 'expires_on' => '2027-03-31']);
    expect($hire())->toMatchArray(['rate' => 0.0, 'nil_reason' => 'Declaration of 10 trucks or fewer on file for 2026-27']);
});

it('records crew advances and broker commissions on a trip', function () {
    $company = makeCompany();
    signInTo($company);
    $w = tripWorld($company);
    $this->post(route('trips.store'), tripPost($w));
    app(CompanyContext::class)->set($company);
    $trip = Trip::first();
    $broker = makeParty(['name' => 'Load Broker'], ['broker'], $company);
    $stranger = makePerson(['name' => 'Not Crew', 'mobile' => '+919847033333'], $company);

    $this->post(route('trips.advances.store', $trip), ['person_id' => $stranger->id, 'amount' => 5000, 'mode' => 'cash', 'given_on' => '2026-10-25'])->assertSessionHasErrors('person_id');
    $this->post(route('trips.advances.store', $trip), ['person_id' => $w['driver']->id, 'amount' => 5000, 'mode' => 'upi', 'given_on' => '2026-10-25'])->assertSessionHasErrors('reference');
    $this->post(route('trips.advances.store', $trip), ['person_id' => $w['driver']->id, 'amount' => 20000, 'mode' => 'cash', 'given_on' => '2026-10-25'])->assertSessionHasNoErrors();

    $this->post(route('trips.commissions.store', $trip), ['broker_party_id' => $w['acc']->id, 'direction' => 'payable', 'basis' => 'fixed', 'amount' => 500])->assertSessionHasErrors('broker_party_id');
    $this->post(route('trips.commissions.store', $trip), ['broker_party_id' => $broker->id, 'direction' => 'payable', 'basis' => 'percent', 'percent' => 2])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($company);
    expect((float) $trip->advances()->sum('amount'))->toBe(20000.0)
        ->and((float) BrokerCommission::first()->amount)->toBe(1258.0);
});

it('lists trips with filters and totals that follow them', function () {
    $company = makeCompany();
    signInTo($company);
    $w = tripWorld($company);
    $this->post(route('trips.store'), tripPost($w, ['other_charges' => 1000]));
    $truck2 = makeTruck(['reg_no' => 'KL-07-ZZ-0002'], $company);
    $driver2 = makePerson(['name' => 'Suresh', 'mobile' => '+919847044444', 'licence_no' => 'X1', 'licence_expiry' => '2030-01-01'], $company);
    $this->post(route('trips.store'), tripPost($w, ['truck_id' => $truck2->id, 'driver_id' => $driver2->id, 'helper_ids' => [], 'to_place_id' => $w['pnq']->id], ['actual_weight_mt' => '10']));

    $this->get(route('trips.index'))->assertOk()->assertSeeText('2 trips')->assertSeeText('Total ₹97,900.00');
    $this->get(route('trips.index', ['place' => $w['pnq']->id]))->assertOk()->assertSeeText('1 trip')->assertSeeText('Total ₹34,000.00');
    $this->get(route('trips.index', ['truck' => $w['truck']->id]))->assertOk()->assertSeeText('Total ₹63,900.00');
});

it('keeps trips inside their company and their permissions', function () {
    $company = makeCompany();
    signInTo($company);
    $w = tripWorld($company);
    $this->post(route('trips.store'), tripPost($w));
    app(CompanyContext::class)->set($company);
    $trip = Trip::first();

    signInTo($company, 'viewer');
    $this->get(route('trips.show', $trip))->assertOk();
    $this->get(route('trips.create'))->assertForbidden();
    $this->post(route('trips.assign', $trip))->assertForbidden();

    signInTo(makeCompany(['name' => 'Other Transport']));
    $this->get(route('trips.show', $trip))->assertNotFound();
});
