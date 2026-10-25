<?php

use App\Http\Middleware\ResolveCompany;
use App\Models\Account;
use App\Modules\Trips\Models\Consignment;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Str;

// The dashboard, the live map and the route replay (M12): real numbers from
// the trips and the book, alerts, and the crew's GPS on a map.

function dashOffice(Account $account, $company): void
{
    phoneReset();
    test()->actingAs($account, 'web')->withSession([ResolveCompany::SESSION_KEY => $company->id]);
}

function dashOwner(array $w): Account
{
    $account = makeAccount(['name' => 'Anil Sharma']);
    joinCompany($account, $w['company'], 'owner');

    return $account;
}

/** A trip on the road: started, fuel and a toll entered, three GPS points sent a minute apart. */
function dashRunning(): array
{
    $w = phoneWorld();
    $phones = phoneCrew($w);
    phoneAs($phones['driver'], $w['company']);
    phoneStart($w);
    phonePush([
        phoneEvent($w, 'fuel', ['amount' => 9500, 'payment_mode' => 'cash', 'odometer' => 100300, 'data' => ['litres' => 100, 'rate' => 95, 'pump' => 'HP Aluva']]),
        phoneEvent($w, 'toll', ['amount' => 455, 'payment_mode' => 'fastag', 'lat' => 10.41, 'lng' => 76.28, 'data' => ['plaza' => 'Paliyekkara']]),
    ]);
    test()->travel(5)->minutes();
    test()->postJson('/api/v1/crew/gps', ['batch' => (string) Str::uuid(), 'points' => [
        ['t' => now()->subMinutes(2)->toIso8601String(), 'lat' => 9.9816, 'lng' => 76.2999, 'acc' => 12, 'spd' => 0],
        ['t' => now()->subMinute()->toIso8601String(), 'lat' => 9.9900, 'lng' => 76.3050, 'acc' => 10, 'spd' => 52],
        ['t' => now()->toIso8601String(), 'lat' => 9.9990, 'lng' => 76.3100, 'acc' => 9, 'spd' => 60],
    ]])->assertOk()->assertJsonPath('kept', 3);

    return $w + ['phones' => $phones];
}

it('shows the owner the day: trucks, trips, entries to check, spending, the map and the alerts', function () {
    $w = dashRunning();
    $owner = dashOwner($w);
    dashOffice($owner, $w['company']);

    $this->get(route('dashboard'))->assertOk()
        ->assertSeeText('Trips running')->assertSeeText('1 on trip · 0 free · 0 in workshop')
        ->assertSeeText('Entries to check')->assertSeeText('Spent on the road today')->assertSeeText('Fuel')->assertSeeText('₹9,500.00')->assertSeeText('₹9,955.00')
        ->assertSee('data-live-map', false)->assertSeeText('Nothing needs you right now.');

    $positions = $this->getJson(route('map.positions'))->assertOk()->json('positions');
    expect($positions)->toHaveCount(1)
        ->and($positions[0])->toMatchArray(['trip' => $w['trip']->trip_number, 'driver' => 'Ravi Kumar', 'lat' => 9.999, 'lng' => 76.31, 'speed' => 60.0, 'minutes' => 0, 'quiet' => false, 'from' => 'Kochi', 'to' => 'Mumbai']);
    $this->get(route('map.index'))->assertOk()->assertSeeText('1 trip running')->assertSeeText('Ravi Kumar')->assertSeeText('Kochi → Mumbai');

    // 330 minutes without GPS (the company allows 25), and 5 h 35 min at the wheel since the start (the limit is 300 minutes).
    $this->travel(330)->minutes();
    $this->get(route('dashboard'))->assertOk()
        ->assertSeeText('No GPS from')->assertSeeText('for 330 min')
        ->assertSeeText('Ravi Kumar has driven 5 h 35 min without a break');
    expect($this->getJson(route('map.positions'))->json('positions.0.quiet'))->toBeTrue();
});

it('gives a trip its route for the replay, the entries with a place on it', function () {
    $w = dashRunning();
    dashOffice(dashOwner($w), $w['company']);

    $route = $this->getJson(route('trips.route', $w['trip']))->assertOk()->json();
    expect($route['total'])->toBe(3)
        ->and(array_map(fn ($p) => [$p[0], $p[1]], $route['points']))->toBe([[9.9816, 76.2999], [9.99, 76.305], [9.999, 76.31]])
        ->and(collect($route['events'])->pluck('label')->all())->toContain('Toll')
        ->and(collect($route['events'])->firstWhere('label', 'Toll'))->toMatchArray(['lat' => 10.41, 'lng' => 76.28, 'amount' => '₹455.00']);
    $this->get(route('trips.show', $w['trip']))->assertOk()->assertSee('data-route-map', false)->assertSee(route('trips.route', $w['trip']), false);

    // Thinned to the limit, the last point kept.
    app(CompanyContext::class)->set($w['company']);
    $thin = app(\App\Modules\Reports\Services\LiveMap::class)->route($w['trip']->fresh(), 2);
    expect($thin['points'])->toHaveCount(3)->and(end($thin['points'])[0])->toBe(9.999);
});

it('shows income and costs by month from the book, and the work queues from LR to cash', function () {
    $w = invWorld(loads: 2);
    $invoice = invDraft($w, [$w['loads'][0]->id]);
    $this->post(route('invoices.finalise', $invoice));
    app(CompanyContext::class)->runAs($w['company'], function () use ($w) {
        Consignment::whereKey($w['loads'][1]->id)->update(['status' => 'pod_received', 'pod_received_at' => now()]);
    });

    $html = $this->get(route('dashboard'))->assertOk()->assertSeeText('Income and costs, last six months')->assertSeeText('Profit this month')
        ->assertSeeText('From LR to cash')->assertSeeText('Ready to bill')->getContent();
    // The table view of the chart: October's income from the bill.
    expect($html)->toContain('October 2026')->toContain('₹64,000.00')
        ->and(substr_count($html, 'data-hit='))->toBe(6);

    app(CompanyContext::class)->set($w['company']);
    $queues = app(\App\Modules\Reports\Services\Dashboard::class)->queues();
    expect($queues)->toMatchArray(['ready_to_bill' => 1, 'not_submitted' => 1, 'overdue' => 0]);

    // Without money permissions the money stays off the screen; the crew have no dashboard.
    signInTo($w['company'], 'workshop');
    $this->get(route('dashboard'))->assertOk()->assertDontSeeText('Profit this month')->assertDontSeeText('From LR to cash');
    signInTo($w['company'], 'driver');
    $this->get(route('dashboard'))->assertForbidden();
    $this->getJson(route('map.positions'))->assertForbidden();
});

it('reports trip profit, road spending, payments without receipt, fuel and mileage, and drivers', function () {
    $w = dashRunning();
    phoneAs($w['phones']['driver'], $w['company']);
    phonePush([phoneEvent($w, 'no_receipt', ['amount' => 300, 'payment_mode' => 'cash', 'data' => ['authority' => 'police', 'reason' => 'Udupi check']])])[0];
    $this->travel(20)->hours();
    phoneRun([phoneCommand($w, 'trip.end', ['event' => ['event_at' => now()->toIso8601String(), 'odometer' => 101630]])]);

    $owner = dashOwner($w);
    dashOffice($owner, $w['company']);
    app(CompanyContext::class)->set($w['company']);
    foreach (\App\Modules\Road\Models\RoadEvent::whereIn('type', ['fuel', 'toll'])->pluck('id') as $id) {
        $this->post(route('approvals.decide', $id), ['decision' => 'approve'])->assertSessionHasNoErrors();
    }

    $this->get(route('reports.index'))->assertOk()->assertSeeText($w['trip']->trip_number)->assertSeeText('Kochi → Mumbai')
        ->assertSeeText('₹9,955.00')->assertSeeText('1,380');
    $this->get(route('reports.index', ['tab' => 'spending']))->assertOk()->assertSeeText('Fuel')->assertSeeText('₹9,500.00')->assertSeeText('FASTag / Salik / Darb')->assertSeeText('₹455.00');
    $this->get(route('reports.index', ['tab' => 'no_receipt']))->assertOk()->assertSeeText('Ravi Kumar')->assertSeeText('Udupi check')->assertSeeText('₹300.00');
    $this->get(route('reports.index', ['tab' => 'fuel']))->assertOk()->assertSeeText($w['truck']->reg_no)->assertSeeText('100.0')->assertSeeText('13.8');
    $this->get(route('reports.index', ['tab' => 'drivers']))->assertOk()->assertSeeText('Ravi Kumar')->assertSeeText('1,380');

    $csv = $this->get(route('reports.index', ['tab' => 'trips', 'format' => 'csv']))->assertOk()->streamedContent();
    expect($csv)->toContain($w['trip']->trip_number)->toContain('9955')->toContain('1380');

    // Read-only people see the reports but do not download them; the crew see none.
    signInTo($w['company'], 'viewer');
    $this->get(route('reports.index'))->assertOk();
    $this->get(route('reports.index', ['format' => 'csv']))->assertForbidden();
    signInTo($w['company'], 'driver');
    $this->get(route('reports.index'))->assertForbidden();
});
