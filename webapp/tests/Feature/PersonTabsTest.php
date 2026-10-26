<?php

use App\Modules\Road\Models\DrivingShift;
use App\Modules\Road\Services\RoadEventService;
use App\Modules\Trips\Models\Trip;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

// The person page's Trips and Driving hours tabs (M41): the trips they were
// crew on with what they spent, and their time at the wheel — less the
// rests they recorded — shift by shift and day by day, against the limit.

/** invWorld's driver with two shifts (5 h 30 min after a half-hour rest, then 2 h) and a ₹2,000 entry. */
function ptWorld(): array
{
    $w = invWorld();
    app(CompanyContext::class)->runAs($w['company'], function () use (&$w) {
        $trip = Trip::with('crew.person')->findOrFail($w['loads'][0]->trip_id);
        $driver = $trip->crew->firstWhere('role', 'driver')->person;
        $at = fn (string $t) => CarbonImmutable::parse($t, 'Asia/Kolkata')->utc();
        DrivingShift::create(['trip_id' => $trip->id, 'person_id' => $driver->id, 'started_at' => $at('2026-10-24 06:00'), 'ended_at' => $at('2026-10-24 12:00'), 'start_odometer' => 100100, 'end_odometer' => 100400]);
        DrivingShift::create(['trip_id' => $trip->id, 'person_id' => $driver->id, 'started_at' => $at('2026-10-25 08:00'), 'ended_at' => $at('2026-10-25 10:00'), 'start_odometer' => 100400, 'end_odometer' => 100500]);
        $events = app(RoadEventService::class);
        foreach ([['rest_start', '2026-10-24 09:00', []], ['rest_end', '2026-10-24 09:30', []], ['fuel', '2026-10-24 10:00', ['amount' => 2000, 'payment_mode' => 'cash', 'data' => ['litres' => 21]]]] as [$type, $time, $fields]) {
            $events->build($driver, $trip, ['uuid' => (string) Str::uuid(), 'trip' => $trip->uuid, 'type' => $type, 'event_at' => CarbonImmutable::parse($time, 'Asia/Kolkata')->toIso8601String()] + $fields)->save();
        }
        $w['trip'] = $trip;
        $w['driver'] = $driver;
    });

    return $w;
}

it('shows a driver’s trips and hours at the wheel', function () {
    $w = ptWorld();

    $this->get(route('people.show', [$w['driver'], 'tab' => 'trips']))->assertOk()->assertSeeText($w['trip']->trip_number)->assertSeeText('Kochi → Mumbai')
        ->assertSeeText('1 trip · 1,400 km · spent ₹2,000.00')->assertSeeText('Driver');
    $this->get(route('people.show', [$w['driver'], 'tab' => 'driving']))->assertOk()->assertSeeText('7 h 30 min')->assertSeeText('5 h 30 min')
        ->assertSeeText('Over the limit')->assertSeeText('limit 5 h 0 min without a break')->assertSeeText('Sat 24 Oct');
    $this->get(route('people.show', $w['driver']))->assertOk()->assertSeeText('Details')->assertSee('tab=driving', false);

    phoneReset();
    $this->withHeaders(['Authorization' => 'Bearer ' . $w['owner']->createToken('owner:android')->plainTextToken, 'X-Company' => $w['company']->uuid, 'Accept' => 'application/json']);
    $this->getJson('/api/v1/owner/crew/' . $w['driver']->id . '/trips')->assertOk()->assertJsonPath('trips.0.role', 'driver')->assertJsonPath('trips.0.spent', 2000)
        ->assertJsonPath('trips.0.km', 1400);
    $this->getJson('/api/v1/owner/crew/' . $w['driver']->id . '/driving')->assertOk()->assertJsonPath('minutes', 450)->assertJsonPath('over', 1)->assertJsonPath('km', 400)
        ->assertJsonPath('days.0.date', '2026-10-25')->assertJsonPath('days.0.minutes', 120)->assertJsonPath('shifts.1.over', true);
});
