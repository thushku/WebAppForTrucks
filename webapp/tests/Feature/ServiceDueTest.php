<?php

use App\Modules\Fleet\Models\ServiceSchedule;
use App\Modules\Trips\Services\TripService;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Facades\DB;

// Service due and when (M50): each schedule's km becomes a likely date from
// the truck's own running of the last 60 days; the fleet's list of services
// overdue or coming up; a warning — never a stop — when a trip would run
// past one; the dashboard and the owner app.

/** The phone world's truck after a 3,000 km trip (50 km a day over 60 days) with four schedules, and a draft trip of 2,500 km. */
function sdWorld(): array
{
    $w = phoneWorld();
    $owner = signInTo($w['company']);
    $draft = app(CompanyContext::class)->runAs($w['company'], function () use ($w, $owner) {
        auth()->setUser($owner);
        $trips = app(TripService::class);
        $trips->start($w['trip']->fresh(), 100000);
        $trips->end($w['trip']->fresh(), 103000);
        DB::table('trips')->where('id', $w['trip']->id)->update(['started_at' => '2026-10-10 03:00:00']);
        foreach ([['Tyre rotation', 20000, null, 80000, null], ['Engine oil', 30000, null, 75000, null], ['General service', 60000, null, 50000, null], ['Greasing', null, 30, null, '2026-10-01']]
            as [$name, $km, $days, $lastKm, $lastOn]) {
            ServiceSchedule::create(['truck_id' => $w['truck']->id, 'name' => $name, 'every_km' => $km, 'every_days' => $days, 'last_done_km' => $lastKm, 'last_done_on' => $lastOn]);
        }
        $trip = $trips->create(['trip_date' => '2026-10-25', 'truck_id' => $w['truck']->id, 'from_place_id' => $w['trip']->from_place_id, 'to_place_id' => $w['trip']->to_place_id, 'planned_km' => 2500],
            [['person_id' => $w['driver']->id, 'role' => 'driver']], ['billing_party_id' => $w['trip']->consignments->first()->billing_party_id, 'freight_basis' => 'trip', 'rate' => 40000, 'freight' => 40000, 'payment_type' => 'to_be_billed']);

        return $trip;
    });

    return $w + ['owner' => $owner, 'draft' => $draft];
}

it('lists services due across the fleet with the likely date from the truck’s pace, and warns a trip that runs past one', function () {
    $w = sdWorld();
    expect((int) $w['truck']->fresh()->odometer_km)->toBe(103000);

    // Tyre rotation overdue (due 100,000); greasing by date (31 Oct); engine oil at 105,000 — 2,000 km at 50 km a day, 4 Dec.
    $this->get(route('trucks.service-due', ['days' => 60]))->assertOk()->assertSeeTextInOrder(['Tyre rotation', 'Greasing', 'Engine oil'])
        ->assertSeeText('3,000 km over')->assertSeeText('31 Oct 2026')->assertSeeText('04 Dec 2026')->assertSeeText('at 50 km a day')->assertDontSeeText('General service');
    $this->get(route('trucks.service-due', ['days' => 7]))->assertSeeText('Tyre rotation')->assertSeeText('Greasing')->assertDontSeeText('Engine oil');
    $this->get(route('trucks.show', [$w['truck'], 'tab' => 'service']))->assertOk()->assertSeeText('around 04 Dec at 50 km a day')->assertSeeText('by 31 Oct');
    $this->get(route('dashboard'))->assertSeeText('1 service is overdue');

    // The draft trip runs to about 105,500 km: past the engine oil, and the tyre rotation is overdue — warned, still sendable.
    $this->get(route('trips.show', $w['draft']))->assertOk()->assertSeeText('Tyre rotation on ' . $w['truck']->reg_no . ' was due at 100,000 km — it is overdue.')
        ->assertSeeText('Engine oil on ' . $w['truck']->reg_no . ' is due at 105,000 km; this trip runs to about 105,500 km.')->assertSeeText('Send to crew');
    $this->post(route('trips.assign', $w['draft']))->assertSessionHasNoErrors();
});

it('gives the owner app the services due', function () {
    $w = sdWorld();
    phoneReset();
    $this->withHeaders(['Authorization' => 'Bearer ' . $w['owner']->createToken('owner:android')->plainTextToken, 'X-Company' => $w['company']->uuid, 'Accept' => 'application/json']);

    $this->getJson('/api/v1/owner/service-due?days=60')->assertOk()->assertJsonCount(3, 'services')
        ->assertJsonPath('services.0.name', 'Tyre rotation')->assertJsonPath('services.0.state', 'overdue')->assertJsonPath('services.0.km_left', -3000)
        ->assertJsonPath('services.2.name', 'Engine oil')->assertJsonPath('services.2.expected_on', '2026-12-04')->assertJsonPath('services.2.expected_by', 'km')
        ->assertJsonPath('services.2.km_a_day', 50);
    $this->getJson('/api/v1/owner/service-due?days=5')->assertStatus(422);
});
