<?php

use App\Modules\Accounts\Services\Books;
use App\Modules\Accounts\Services\ChartOfAccounts;
use App\Modules\Trips\Models\Trip;
use App\Modules\Trips\Services\TripService;
use App\Platform\Tenancy\CompanyContext;

// Reports → Routes (M33): what a trip on each lane earns, costs and makes —
// the freight on its LRs, billed or not, against the book's costs.

it('groups the ended trips by route with income, cost, profit, km and the main costs', function () {
    $w = invWorld(loads: 2);
    [$one, $two] = $w['loads'];
    app(CompanyContext::class)->runAs($w['company'], function () use ($one) {
        // A toll of ₹2,000 on the first trip, in the book.
        app(Books::class)->post(['type' => 'payment', 'date' => '2026-10-25', 'narration' => 'Toll at Kasara'], [
            ['ledger' => app(ChartOfAccounts::class)->expense('toll'), 'debit' => 2000, 'trip_id' => $one->trip_id],
            ['ledger' => app(ChartOfAccounts::class)->ledger('cash'), 'credit' => 2000],
        ]);
        // An empty run back, Mumbai → Kochi, is its own line.
        $trips = app(TripService::class);
        $back = $trips->create(['trip_date' => '2026-10-25', 'truck_id' => Trip::find($one->trip_id)->truck_id, 'from_place_id' => Trip::find($one->trip_id)->to_place_id,
            'to_place_id' => Trip::find($one->trip_id)->from_place_id, 'trip_kind' => 'empty'], [['person_id' => Trip::find($one->trip_id)->crew()->value('person_id'), 'role' => 'driver']]);
        $trips->assign($back->fresh());
        $trips->start($back->fresh(), 101500);
        $trips->end($back->fresh(), 102900);
    });

    $this->get(route('reports.index', ['tab' => 'lanes', 'from' => '2026-10-01', 'to' => '2026-10-31']))->assertOk()->assertSeeText('Kochi → Mumbai')->assertSeeText('Mumbai → Kochi (empty)')
        ->assertSeeText('₹64,000.00')->assertSeeText('₹63,000.00')->assertSeeText('Toll')->assertSeeText('₹1,000.00')->assertSeeText('₹45.71');

    $csv = $this->get(route('reports.index', ['tab' => 'lanes', 'from' => '2026-10-01', 'to' => '2026-10-31', 'format' => 'csv']))->assertOk()->streamedContent();
    $rows = array_map('str_getcsv', explode("\n", trim(str_replace("\xEF\xBB\xBF", '', $csv))));
    expect($rows[0][0])->toBe('Route')->and($rows[1])->toBe(['Kochi → Mumbai', '2', '64000', '1000', '63000', '98.4', '1400', '45.71', '0', '64000', '62000', 'Toll 1000'])
        ->and($rows[2][0])->toBe('Mumbai → Kochi (empty)')->and($rows[2][2])->toBe('0');

    // The owner app has it too.
    phoneReset();
    $this->withHeaders(['Authorization' => 'Bearer ' . $w['owner']->createToken('owner:android')->plainTextToken, 'X-Company' => $w['company']->uuid, 'Accept' => 'application/json']);
    $this->getJson('/api/v1/owner/reports/routes')->assertOk()->assertJsonPath('routes.0.route', 'Kochi → Mumbai')->assertJsonPath('routes.0.trips', 2)
        ->assertJsonPath('routes.0.profit', 63000)->assertJsonPath('routes.0.main_costs.Toll', 1000)->assertJsonPath('routes.1.empty', true);
});
