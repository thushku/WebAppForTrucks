<?php

use App\Modules\Accounts\Services\Books;
use App\Modules\Accounts\Services\ChartOfAccounts;
use App\Modules\Documents\Models\Document;
use App\Modules\Fleet\Services\Tyres;
use App\Modules\Road\Services\RoadEventService;
use App\Modules\Trips\Models\Trip;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

// The truck page's Trips, Fuel and Costs tabs (M39): the trips it ran, its
// fills with the km per litre between full tanks, and what it earned and
// cost — the books, then the estimates the books do not hold by truck.

/**
 * invWorld's Kochi → Mumbai trip (1,00,100 → 1,01,500 km) on a truck with
 * three fills, ₹64,000 freight and a ₹9,000 repair in the books, insurance
 * of ₹100 a day, a ₹30,000-a-month driver and one tyre at ₹0.25 a km.
 */
function ttWorld(): array
{
    $w = invWorld();
    app(CompanyContext::class)->runAs($w['company'], function () use (&$w) {
        $trip = Trip::with(['truck', 'crew.person'])->findOrFail($w['loads'][0]->trip_id);
        $trip->truck->update(['mileage_loaded_kmpl' => 4]);
        $driver = $trip->crew->firstWhere('role', 'driver')->person;
        $driver->update(['default_truck_id' => $trip->truck_id, 'pay_basis' => 'monthly', 'monthly_salary' => 30000]);
        $events = app(RoadEventService::class);
        foreach ([['2026-10-24 10:00', 100100, 200, 19000, true], ['2026-10-25 08:00', 100800, 50, 4750, false], ['2026-10-25 12:00', 101500, 150, 14250, true]] as [$at, $odo, $litres, $amount, $full]) {
            $events->build($driver, $trip, ['uuid' => (string) Str::uuid(), 'trip' => $trip->uuid, 'type' => 'fuel', 'event_at' => CarbonImmutable::parse($at, 'Asia/Kolkata')->toIso8601String(),
                'amount' => $amount, 'payment_mode' => 'cash', 'odometer' => $odo, 'data' => ['litres' => $litres, 'rate' => 95, 'full_tank' => $full, 'pump' => 'HP Aluva']])->save();
        }
        $chart = app(ChartOfAccounts::class);
        $books = app(Books::class);
        $books->post(['type' => 'journal', 'date' => '2026-10-25', 'narration' => 'Freight'], [
            ['ledger' => $chart->ledger('cash'), 'debit' => 64000],
            ['ledger' => $chart->income('FREIGHT_ROAD'), 'credit' => 64000, 'trip_id' => $trip->id, 'truck_id' => $trip->truck_id],
        ]);
        $books->post(['type' => 'payment', 'date' => '2026-10-22', 'narration' => 'Bumper'], [
            ['ledger' => $chart->ledger('accident_repairs'), 'debit' => 9000, 'truck_id' => $trip->truck_id],
            ['ledger' => $chart->ledger('cash'), 'credit' => 9000],
        ]);
        Document::create(['owner_type' => 'truck', 'owner_id' => $trip->truck_id, 'document_type' => 'insurance', 'number' => 'POL-1', 'amount' => 36500, 'valid_from' => '2026-04-01', 'expires_on' => '2027-03-31']);
        $tyres = app(Tyres::class);
        $tyres->fit($tyres->add(['serials' => 'T-1', 'brand' => 'MRF', 'size' => '10.00 R20', 'kind' => 'new', 'cost' => 20000, 'expected_km' => 80000], $w['owner'])->first(), $trip->truck, 'F1-L', '2026-10-20', 100000, $w['owner']);
        $w['trip'] = $trip;
    });

    return $w;
}

it('shows the truck’s trips, its fuel with the km per litre and its money', function () {
    $w = ttWorld();
    $truck = $w['trip']->truck;

    $this->get(route('trucks.show', [$truck, 'tab' => 'trips']))->assertOk()->assertSeeText($w['trip']->trip_number)->assertSeeText('Kochi → Mumbai')
        ->assertSeeText('1 trip · 1,400 km')->assertSeeText('₹64,000.00');

    // 1,400 km from the first full tank to the second, on the 200 litres bought since: 7 km a litre.
    $this->get(route('trucks.show', [$truck, 'tab' => 'fuel']))->assertOk()->assertSeeText('400.0')->assertSeeText('₹38,000.00')->assertSeeText('7')
        ->assertSeeText('normal 4')->assertSeeText('₹27.14')->assertSeeText('HP Aluva');

    // 20–25 October: ₹55,000 in the books, less 6 days of insurance (₹600) and salary (₹6,000) and ₹350 of tyre wear.
    $this->get(route('trucks.show', [$truck, 'tab' => 'costs', 'from' => '2026-10-20', 'to' => '2026-10-25']))->assertOk()
        ->assertSeeText('Profit in the books')->assertSeeText('₹55,000.00')->assertSeeText('₹600.00')->assertSeeText('₹6,000.00')->assertSeeText('₹350.00')
        ->assertSeeText('Profit after the truck’s costs')->assertSeeText('₹48,050.00')->assertSeeText('₹34.32')->assertSeeText('Accident repairs')->assertSeeText('Oct 2026');

    phoneReset();
    $this->withHeaders(['Authorization' => 'Bearer ' . $w['owner']->createToken('owner:android')->plainTextToken, 'X-Company' => $w['company']->uuid, 'Accept' => 'application/json']);
    $this->getJson('/api/v1/owner/trucks/' . $truck->id . '/trips')->assertOk()->assertJsonPath('from', '2026-04-01')->assertJsonPath('trips.0.km', 1400)->assertJsonPath('trips.0.freight', 64000);
    $this->getJson('/api/v1/owner/trucks/' . $truck->id . '/fuel')->assertOk()->assertJsonPath('kmpl', 7)->assertJsonPath('fills.0.kmpl', 7)->assertJsonPath('fills.0.km', 1400)
        ->assertJsonPath('fills.2.kmpl', null)->assertJsonPath('litres', 400);
    $this->getJson('/api/v1/owner/trucks/' . $truck->id . '/costs?from=2026-10-20&to=2026-10-25')->assertOk()->assertJsonPath('profit', 55000)
        ->assertJsonPath('estimates.tyres', 350)->assertJsonPath('net', 48050)->assertJsonPath('cost_ledgers.0.name', 'Accident repairs')->assertJsonPath('months.0.maintenance', 9000);
});

it('shows each tab only to those who may see it', function () {
    $w = ttWorld();
    signInTo($w['company'], 'workshop', ['email' => 'shop@fleet.test']);

    $this->get(route('trucks.show', [$w['trip']->truck_id, 'tab' => 'costs']))->assertOk()->assertDontSeeText('What the truck earned and cost')->assertDontSee('tab=costs', false)
        ->assertSee('tab=tyres', false);
    $this->get(route('trucks.show', [$w['trip']->truck_id, 'tab' => 'trips']))->assertOk()->assertDontSeeText('Trips of this truck');
});
