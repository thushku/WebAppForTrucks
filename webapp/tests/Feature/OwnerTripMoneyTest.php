<?php

use App\Modules\Parties\Models\SupplierVehicle;
use App\Modules\Trips\Models\TripAdvance;
use App\Modules\Trips\Services\TripService;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Str;

// The owner app settles a trip's crew cash and keeps a hired truck's hire
// challan (M42): the web's rules, every write once per Idempotency-Key.

/** The owner's phone on this company. */
function otmPhone(\App\Models\Account $owner, $company): void
{
    phoneReset();
    test()->withHeaders(['Authorization' => 'Bearer ' . $owner->createToken('owner:android')->plainTextToken, 'X-Company' => $company->uuid, 'Accept' => 'application/json']);
}

it('settles each crew member from the owner app and closes the trip', function () {
    $w = phoneWorld();
    $owner = signInTo($w['company']);
    otmPhone($owner, $w['company']);
    $url = '/api/v1/owner/trips/' . $w['trip']->uuid;

    // Sent, not yet run: nothing to settle.
    $this->getJson($url . '/settlement')->assertOk()->assertJsonPath('can_settle', false)->assertJsonPath('crew', []);

    app(CompanyContext::class)->runAs($w['company'], function () use ($w, $owner) {
        auth()->setUser($owner);
        $trips = app(TripService::class);
        $trips->start($w['trip']->fresh(), 100100);
        $trips->end($w['trip']->fresh(), 101500);
        TripAdvance::create(['trip_id' => $w['trip']->id, 'person_id' => $w['driver']->id, 'amount' => 5000, 'mode' => 'cash', 'given_on' => '2026-10-25', 'created_by' => $owner->id]);
    });
    otmPhone($owner, $w['company']);

    $state = $this->getJson($url . '/settlement')->assertOk()->assertJsonPath('can_settle', true)->assertJsonPath('trip.status', 'ended')->json();
    $driver = collect($state['crew'])->firstWhere('person.name', 'Ravi Kumar');
    expect($driver)->toMatchArray(['role' => 'driver', 'advances' => 5000, 'balance' => 5000, 'settlement' => null])
        ->and(collect($state['modes'])->pluck('code')->all())->toContain('cash_returned');

    // The driver holds the company's money: it is returned, not paid.
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($url . '/settle/' . $w['driver']->id, ['mode' => 'paid_to_crew'])->assertStatus(422);
    $key = (string) Str::uuid();
    $this->withHeader('Idempotency-Key', $key)->postJson($url . '/settle/' . $w['driver']->id, ['mode' => 'cash_returned', 'reference' => 'Receipt 41'])->assertCreated()
        ->assertJsonPath('trip.status', 'ended');
    $this->withHeader('Idempotency-Key', $key)->postJson($url . '/settle/' . $w['driver']->id, ['mode' => 'cash_returned'])->assertJsonPath('replayed', true);
    foreach (['co', 'helper'] as $who) {
        $last = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($url . '/settle/' . $w[$who]->id, ['mode' => 'nil'])->assertCreated();
    }
    $last->assertJsonPath('trip.status', 'settled')->assertJsonPath('can_close', true);
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($url . '/close')->assertCreated()->assertJsonPath('trip.status', 'closed')
        ->assertJsonPath('crew.0.settlement.mode', 'cash_returned');
});

it('keeps a hired truck’s hire challan from the owner app', function () {
    $company = makeCompany();
    $owner = signInTo($company);
    $trip = app(CompanyContext::class)->runAs($company, function () use ($owner) {
        auth()->setUser($owner);
        $raju = makeParty(['name' => 'Raju Transport', 'pan' => 'ABCPR1234K'], ['supplier']);
        $vehicle = SupplierVehicle::create(['party_id' => $raju->id, 'reg_no' => 'MH-04-KL-7788', 'capacity_mt' => 21, 'driver_name' => 'Ganesh']);
        $from = \App\Modules\Settings\Models\Place::create(['name' => 'Kochi', 'short_code' => 'COK']);
        $to = \App\Modules\Settings\Models\Place::create(['name' => 'Mumbai', 'short_code' => 'BOM']);

        return app(TripService::class)->create(['trip_date' => '2026-10-25', 'truck_source' => 'hired', 'supplier_party_id' => $raju->id, 'supplier_vehicle_id' => $vehicle->id,
            'from_place_id' => $from->id, 'to_place_id' => $to->id], []);
    });
    otmPhone($owner, $company);
    $url = '/api/v1/owner/trips/' . $trip->uuid . '/hire';

    // A new challan starts from the trip: the vehicle, its driver and 1 % TDS for an individual supplier.
    $this->getJson($url)->assertOk()->assertJsonPath('challans', [])->assertJsonPath('new.vehicle_reg', 'MH-04-KL-7788')->assertJsonPath('new.driver_name', 'Ganesh')
        ->assertJsonPath('new.tds_rate', 1);
    $draft = ['challan_date' => '2026-10-25', 'vehicle_reg' => 'mh-04-kl-7788', 'hire_basis' => 'fixed', 'hire_amount' => 30000, 'commission_mode' => 'deducted',
        'commission_amount' => 500, 'tds_section' => '393(1)', 'tds_rate' => 1];
    $key = (string) Str::uuid();
    $id = $this->withHeader('Idempotency-Key', $key)->postJson($url, $draft)->assertCreated()->assertJsonPath('challan.status', 'draft')
        ->assertJsonPath('challan.vehicle_reg', 'MH-04-KL-7788')->assertJsonPath('challan.tds_amount', 300)->json('challan.id');
    $this->withHeader('Idempotency-Key', $key)->postJson($url, $draft)->assertJsonPath('replayed', true);

    // A draft is changed whole; issued, it gets its number and takes money lines only.
    $this->putJson($url . '/' . $id, ['hire_amount' => 32000] + $draft)->assertOk()->assertJsonPath('challan.hire_amount', 32000)->assertJsonPath('challan.balance', 31180);
    $issued = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($url . '/' . $id . '/issue')->assertCreated()->assertJsonPath('challan.status', 'issued')->json('challan');
    expect($issued['number'])->not->toBeNull();
    $this->putJson($url . '/' . $id, $draft)->assertStatus(422);

    $line = fn (array $body) => $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($url . '/' . $id . '/lines', $body + ['line_date' => '2026-10-25']);
    $line(['kind' => 'advance', 'amount' => 10000])->assertStatus(422)->assertJsonPath('message', 'How was it paid?');
    $line(['kind' => 'advance', 'amount' => 10000, 'mode' => 'cash', 'paid_to' => 'driver'])->assertCreated()->assertJsonPath('challan.balance', 21180);
    $line(['kind' => 'payment', 'amount' => 30000, 'mode' => 'bank'])->assertStatus(422);
    $line(['kind' => 'payment', 'amount' => 21180, 'mode' => 'bank', 'reference' => 'UTR 7788'])->assertCreated()->assertJsonPath('challan.status', 'settled')
        ->assertJsonPath('challan.balance', 0)->assertJsonPath('challan.lines.1.label', 'Balance paid');
});
