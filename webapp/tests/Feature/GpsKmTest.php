<?php

use App\Modules\Road\Models\GpsPoint;
use App\Modules\Road\Services\GpsDistance;
use App\Modules\Trips\Models\Trip;
use App\Modules\Trips\Services\TripService;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;

// Odometer against GPS (M34): a trip's km by the crew phone's points beside
// the odometer's; far apart with good coverage is flagged.

/** A run north from Kochi at 60 km/h: one point a minute, about 1 km apart. */
function gkDrive(array $w, int $minutes, CarbonImmutable $from, array $extra = []): void
{
    for ($i = 0; $i <= $minutes; $i++) {
        GpsPoint::create(['company_id' => $w['company']->id, 'person_id' => $w['driver']->id, 'trip_id' => $w['trip']->id, 'recorded_at' => $from->addMinutes($i),
            'lat' => 9.9312 + $i * 0.008993, 'lng' => 76.2673, 'accuracy_m' => 8, 'mock' => false]);
    }
    foreach ($extra as $p) {
        GpsPoint::create(['company_id' => $w['company']->id, 'person_id' => $w['driver']->id, 'trip_id' => $w['trip']->id, 'accuracy_m' => 8, 'mock' => false] + $p);
    }
}

it('measures a trip by GPS, ignoring fakes and jumps, and flags an odometer that is far off', function () {
    $w = phoneWorld();
    $start = CarbonImmutable::parse('2026-10-25 07:00', 'UTC');
    app(CompanyContext::class)->runAs($w['company'], function () use ($w, $start) {
        $trips = app(TripService::class);
        $trips->start($w['trip']->fresh(), 100250, $start);
        gkDrive($w, 100, $start, [
            // A fake location and a jump no truck can make are left out.
            ['recorded_at' => $start->addSeconds(90), 'lat' => 12.97, 'lng' => 77.59, 'mock' => true],
            ['recorded_at' => $start->addSeconds(150), 'lat' => 13.08, 'lng' => 80.27],
        ]);
        // The odometer says 250 km; the GPS about 100.
        $trips->end($w['trip']->fresh(), 100500, false, $start->addMinutes(100));
    });

    app(CompanyContext::class)->set($w['company']);
    $trip = Trip::find($w['trip']->id);
    expect((float) $trip->gps_km)->toBeGreaterThan(99.0)->toBeLessThan(101.0)
        ->and($trip->gps_coverage)->toBe(100)->and($trip->km_check)->toBe('differs')->and(GpsDistance::gap($trip))->toBeGreaterThan(140.0);

    // The office sees it on the trip, on the dashboard and in the trip list; the owner app too.
    signInTo($w['company']);
    $this->get(route('trips.show', $trip))->assertOk()->assertSeeText('Odometer 250 km')->assertSee('data-km-check="differs"', false)->assertSeeText('The odometer and the GPS differ by');
    $this->get(route('dashboard'))->assertSeeText('1 trip of the last 30 days has the odometer and GPS apart');
    $this->get(route('trips.index', ['status' => 'all', 'km' => 'differs']))->assertOk()->assertSee(route('trips.show', $trip), false)->assertSeeText('Odometer and GPS apart');
    phoneReset();
    $owner = makeAccount(['name' => 'Anil Sharma']);
    joinCompany($owner, $w['company'], 'owner');
    $this->withHeaders(['Authorization' => 'Bearer ' . $owner->createToken('owner:android')->plainTextToken, 'X-Company' => $w['company']->uuid, 'Accept' => 'application/json']);
    $this->getJson('/api/v1/owner/trips/' . $trip->uuid)->assertOk()->assertJsonPath('km_check', 'differs')->assertJsonPath('gps_coverage', 100);

    // The odometer corrected to 100 km: the nightly run finds them agreeing.
    app(CompanyContext::class)->set($w['company']);
    Trip::whereKey($trip->id)->update(['end_odometer' => 100350]);
    $this->artisan('fleet:gps-check')->expectsOutput('1 trip(s) checked, 0 with the odometer and GPS apart.');
    app(CompanyContext::class)->set($w['company']);
    expect(Trip::find($trip->id)->km_check)->toBe('ok');
});

it('does not compare when the GPS covered too little of the trip', function () {
    $w = phoneWorld();
    $start = CarbonImmutable::parse('2026-10-25 03:00', 'UTC');
    app(CompanyContext::class)->runAs($w['company'], function () use ($w, $start) {
        $trips = app(TripService::class);
        $trips->start($w['trip']->fresh(), 100250, $start);
        gkDrive($w, 20, $start); // 20 minutes of a five-hour trip
        $trips->end($w['trip']->fresh(), 100600, false, $start->addHours(5));
    });
    app(CompanyContext::class)->set($w['company']);
    $trip = Trip::find($w['trip']->id);
    expect($trip->km_check)->toBe('thin')->and($trip->gps_coverage)->toBeLessThan(10);

    signInTo($w['company']);
    $this->get(route('trips.show', $trip))->assertSeeText('Too little GPS (or no odometer) to compare the two.')->assertDontSeeText('differ by');
});
