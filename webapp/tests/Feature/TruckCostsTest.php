<?php

use App\Modules\Accounts\Models\Voucher;
use App\Modules\Accounts\Services\Books;
use App\Modules\Accounts\Services\ChartOfAccounts;
use App\Modules\Documents\Models\Document;
use App\Modules\Fleet\Services\TruckCosts;
use App\Modules\Trips\Models\Trip;
use App\Platform\Tenancy\CompanyContext;

// A truck's fixed costs a day (M37) — its papers over the days they are
// valid, its usual driver's salary, its own bills of the last 90 days — and a
// trip's share of them for its days on the road, an estimate shown under the
// trip's direct profit.

/**
 * invWorld's Kochi → Mumbai trip on a truck with insurance of ₹36,500 for the
 * year (₹100 a day), a driver on ₹30,000 a month (₹1,000 a day), on the road
 * from 22 to 24 October (three days).
 */
function tcWorld(): array
{
    $w = invWorld();
    app(CompanyContext::class)->runAs($w['company'], function () use (&$w) {
        $trip = Trip::findOrFail($w['loads'][0]->trip_id);
        $trip->crew()->firstWhere('role', 'driver')->person->update(['default_truck_id' => $trip->truck_id, 'pay_basis' => 'monthly', 'monthly_salary' => 30000]);
        Document::create(['owner_type' => 'truck', 'owner_id' => $trip->truck_id, 'document_type' => 'insurance', 'number' => 'POL-4410', 'amount' => 36500,
            'valid_from' => '2026-04-01', 'expires_on' => '2027-03-31']);
        // A paper without its cost, and one run out, count nothing.
        Document::create(['owner_type' => 'truck', 'owner_id' => $trip->truck_id, 'document_type' => 'permit', 'number' => 'P-1', 'expires_on' => '2027-06-30']);
        Document::create(['owner_type' => 'truck', 'owner_id' => $trip->truck_id, 'document_type' => 'fitness', 'number' => 'F-0', 'amount' => 5000, 'expires_on' => '2026-09-30']);
        $trip->forceFill(['started_at' => '2026-10-22 05:00:00', 'ended_at' => '2026-10-24 12:00:00'])->saveQuietly();
        $w['trip'] = $trip->fresh();
    });

    return $w;
}

it('works out a truck’s fixed costs a day and takes a trip’s share off its profit', function () {
    $w = tcWorld();
    [$truck, $repairs] = app(CompanyContext::class)->runAs($w['company'], fn () => [$w['trip']->truck, app(ChartOfAccounts::class)->ledger('accident_repairs')->id]);

    // A repair paid by voucher for this truck: ₹9,000 in the last 90 days is ₹100 a day.
    $this->post(route('accounts.payments.store'), ['ledger_account_id' => $repairs, 'amount' => 9000, 'paid_on' => '2026-10-10', 'mode' => 'cash',
        'narration' => 'Bumper and lights', 'truck_id' => $truck->id])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $voucher = Voucher::with('lines')->latest('id')->firstOrFail();
    expect($voucher->lines->firstWhere('ledger_account_id', $repairs)->truck_id)->toBe($truck->id);

    // Neither one older than 90 days nor one booked to a trip counts.
    $books = app(Books::class);
    $books->post(['type' => 'payment', 'date' => '2026-06-01', 'narration' => 'Old repair'], [
        ['ledger' => app(ChartOfAccounts::class)->ledger('accident_repairs'), 'debit' => 50000, 'truck_id' => $truck->id],
        ['ledger' => app(ChartOfAccounts::class)->ledger('cash'), 'credit' => 50000],
    ]);
    $books->post(['type' => 'payment', 'date' => '2026-10-23', 'narration' => 'Toll'], [
        ['ledger' => app(ChartOfAccounts::class)->expense('toll'), 'debit' => 1500, 'truck_id' => $truck->id, 'trip_id' => $w['trip']->id],
        ['ledger' => app(ChartOfAccounts::class)->ledger('cash'), 'credit' => 1500],
    ]);

    $costs = app(TruckCosts::class);
    expect($costs->perDay($truck))->toBe(['per_day' => 1200.0, 'parts' => ['book' => 100.0, 'papers' => 100.0, 'salary' => 1000.0],
        'papers' => [['name' => 'Insurance', 'per_day' => 100.0]]])
        ->and($costs->tripShare($w['trip']))->toBe(['days' => 3, 'per_day' => 1200.0, 'share' => 3600.0]);

    $this->get(route('trips.show', $w['trip']))->assertOk()->assertSeeText('Trip profit (direct)')->assertSeeText('Truck’s fixed costs, 3 days (estimate)')
        ->assertSeeText('₹3,600.00')->assertSeeText('Profit after fixed costs')->assertSeeText('₹60,400.00');
    $this->get(route('trucks.show', $truck))->assertOk()->assertSeeText('Fixed costs a day')->assertSeeText('₹1,200.00')->assertSeeText('Usual driver’s salary')
        ->assertSeeText('₹1,000.00');

    // The routes report takes each trip's share off too (its profit there is after the ₹1,500 toll in the book).
    $this->get(route('reports.index', ['tab' => 'lanes', 'from' => '2026-10-01', 'to' => '2026-10-31']))->assertOk()->assertSeeText('after fixed ₹58,900.00');

    // And the owner app.
    phoneReset();
    $this->withHeaders(['Authorization' => 'Bearer ' . $w['owner']->createToken('owner:android')->plainTextToken, 'X-Company' => $w['company']->uuid, 'Accept' => 'application/json']);
    $this->getJson('/api/v1/owner/trips/' . $w['trip']->uuid)->assertOk()->assertJsonPath('fixed_costs.days', 3)->assertJsonPath('fixed_costs.share', 3600);
    $this->getJson('/api/v1/owner/trucks/' . $truck->id)->assertOk()->assertJsonPath('fixed_costs.per_day', 1200)->assertJsonPath('fixed_costs.papers.0.name', 'Insurance');
    $this->getJson('/api/v1/owner/reports/routes')->assertOk()->assertJsonPath('routes.0.fixed', 3600)->assertJsonPath('routes.0.net', 58900);
});

it('leaves an attached truck’s costs to its owner', function () {
    $w = tcWorld();
    app(CompanyContext::class)->runAs($w['company'], fn () => $w['trip']->truck->update(['ownership' => 'attached']));

    expect(app(CompanyContext::class)->runAs($w['company'], fn () => app(TruckCosts::class)->tripShare($w['trip']->fresh())))->toBeNull();
    $this->get(route('trips.show', $w['trip']))->assertOk()->assertSeeText('Trip profit (direct)')->assertDontSeeText('Profit after fixed costs');
    $this->get(route('trucks.show', $w['trip']->truck_id))->assertOk()->assertDontSeeText('Fixed costs a day');
});

it('counts a trip that starts and ends on one day as one day', function () {
    $w = tcWorld();
    $trip = $w['trip'];
    $trip->forceFill(['started_at' => '2026-10-24 19:00:00', 'ended_at' => '2026-10-24 20:00:00'])->saveQuietly();

    // 00:30 and 01:30 on the 25th in Kochi: one day, though the UTC dates are the 24th.
    expect(app(CompanyContext::class)->runAs($w['company'], fn () => app(TruckCosts::class)->tripDays($trip->fresh())))->toBe(1);
    $trip->forceFill(['started_at' => '2026-10-23 19:00:00'])->saveQuietly();
    expect(app(CompanyContext::class)->runAs($w['company'], fn () => app(TruckCosts::class)->tripDays($trip->fresh())))->toBe(2);
});
