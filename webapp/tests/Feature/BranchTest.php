<?php

use App\Http\Middleware\ResolveCompany;
use App\Models\CompanyUser;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Services\InvoiceService;
use App\Modules\Reports\Services\Dashboard;
use App\Modules\Settings\Models\Branch;
use App\Modules\Trips\Models\Trip;
use App\Platform\Authorization\Models\RoleAssignment;
use App\Platform\Tenancy\CompanyContext;

// Branches at work (M22): the branch filter in the header narrows the lists,
// the dashboard and the map; a person limited to a branch sees and opens
// only that branch's trips and bills, and its trucks and crew (plus those
// with no branch), on the web and in the owner app.

/** qtWorld() with a Mumbai branch: a truck in each branch and one shared, and a draft trip and a bill in each branch. */
function brWorld(): array
{
    $w = qtWorld();

    return app(CompanyContext::class)->runAs($w['company'], function () use ($w) {
        $ho = Branch::where('code', 'HO')->firstOrFail();
        $bom = Branch::create(['name' => 'Mumbai branch', 'code' => 'BOM', 'state_code' => '27', 'city' => 'Bhiwandi']);
        $w['truck']->forceFill(['branch_id' => $ho->id])->save();
        $mh = makeTruck(['reg_no' => 'MH-04-BB-2222', 'branch_id' => $bom->id]);
        $shared = makeTruck(['reg_no' => 'KL-07-CC-3333']);
        $trips = app(\App\Modules\Trips\Services\TripService::class);
        $load = ['billing_party_id' => $w['acc']->id, 'material' => 'cement', 'actual_weight_mt' => 18, 'freight_basis' => 'tonne', 'rate' => 3400, 'freight' => 61200, 'payment_type' => 'to_be_billed'];
        $hoTrip = $trips->create(['trip_date' => '2026-10-25', 'truck_id' => $w['truck']->id, 'from_place_id' => $w['cok']->id, 'to_place_id' => $w['bom']->id, 'branch_id' => $ho->id], [['person_id' => $w['driver']->id, 'role' => 'driver']], $load);
        $bomTrip = $trips->create(['trip_date' => '2026-10-25', 'truck_id' => $mh->id, 'from_place_id' => $w['bom']->id, 'to_place_id' => $w['pnq']->id, 'branch_id' => $bom->id], [['person_id' => makePerson(['name' => 'Sanjay Patil', 'mobile' => '+919890011111', 'licence_no' => 'MH0420150012345', 'licence_expiry' => '2030-01-01'])->id, 'role' => 'driver']], $load);
        $bills = app(InvoiceService::class);
        $hoBill = tap($bills->draftBlank($w['acc']), fn ($b) => $b->forceFill(['branch_id' => $ho->id])->save());
        $bomBill = tap($bills->draftBlank($w['acc']), fn ($b) => $b->forceFill(['branch_id' => $bom->id])->save());

        return $w + ['hoBranch' => $ho, 'bomBranch' => $bom] + compact('mh', 'shared', 'hoTrip', 'bomTrip', 'hoBill', 'bomBill');
    });
}

function brAs($account, $company): void
{
    test()->actingAs($account, 'web')->withSession([ResolveCompany::SESSION_KEY => $company->id]);
}

it('narrows the lists, the dashboard and new trips to the branch picked in the header', function () {
    $w = brWorld();

    $this->get(route('trucks.index'))->assertOk()->assertSee('data-branch-filter', false)->assertSeeText('All branches')
        ->assertSeeText($w['truck']->reg_no)->assertSeeText('MH-04-BB-2222')->assertSeeText('KL-07-CC-3333');

    // Mumbai: its truck and the shared one; its trip; its bill.
    $this->post(route('branch.select'), ['branch' => $w['bomBranch']->id])->assertRedirect();
    $this->get(route('trucks.index'))->assertSeeText('MH-04-BB-2222')->assertSeeText('KL-07-CC-3333')->assertDontSeeText($w['truck']->reg_no);
    $this->get(route('trips.index', ['status' => 'all']))->assertSee(route('trips.show', $w['bomTrip']), false)->assertDontSee(route('trips.show', $w['hoTrip']), false);
    $this->get(route('invoices.index', ['tab' => 'all']))->assertSee(route('invoices.show', $w['bomBill']), false)->assertDontSee(route('invoices.show', $w['hoBill']), false);
    // The filter is a view, not a permission: the owner still opens Kochi's trip.
    $this->get(route('trips.show', $w['hoTrip']))->assertOk();
    // A new trip starts in the branch shown; so does a bill.
    $this->get(route('trips.create'))->assertSee('<option value="' . $w['bomBranch']->id . '" selected>', false);

    // The dashboard counts the branch's trucks and trips.
    session()->put('branch_filter.' . $w['company']->id, $w['bomBranch']->id);
    app(CompanyContext::class)->set($w['company']);
    expect(app(Dashboard::class)->counts()['trucks']['total'])->toBe(2);

    // Back to all.
    $this->post(route('branch.select'), ['branch' => ''])->assertRedirect();
    $this->get(route('trucks.index'))->assertSeeText($w['truck']->reg_no)->assertSeeText('MH-04-BB-2222');
    // A branch of another company cannot be picked.
    $this->post(route('branch.select'), ['branch' => 999999])->assertRedirect();
    $this->get(route('trucks.index'))->assertSeeText($w['truck']->reg_no);

    // One branch only: no filter at all.
    app(CompanyContext::class)->set($w['company']);
    $w['bomBranch']->forceFill(['active' => false])->save();
    $this->get(route('trucks.index'))->assertDontSee('data-branch-filter', false);
});

it('keeps a person limited to a branch to that branch, on the web and in the owner app', function () {
    $w = brWorld();
    $vikram = makeAccount(['name' => 'Vikram Rao', 'mobile' => '+919847055555']);
    $member = joinCompany($vikram, $w['company'], 'manager');

    // The owner limits Vikram to Mumbai on the team page; an owner cannot be limited.
    $this->get(route('settings.team.edit', $member))->assertOk()->assertSeeText('Only the Mumbai branch branch');
    $this->put(route('settings.team.update', $member), ['roles' => ['owner'], 'branch_id' => $w['bomBranch']->id])->assertSessionHasErrors('branch_id');
    $this->put(route('settings.team.update', $member), ['roles' => ['manager'], 'branch_id' => $w['bomBranch']->id])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect(RoleAssignment::where('company_user_id', $member->id)->pluck('branch_id')->all())->toBe([$w['bomBranch']->id])
        ->and(\App\Platform\Audit\AuditLog::where('action', 'team.branch_set')->value('summary'))->toBe('Limited Vikram Rao to the Mumbai branch branch');

    brAs($vikram, $w['company']);
    // One branch: no filter, the lists are Mumbai's (and the shared truck).
    $this->get(route('trucks.index'))->assertOk()->assertDontSee('data-branch-filter', false)
        ->assertSeeText('MH-04-BB-2222')->assertSeeText('KL-07-CC-3333')->assertDontSeeText($w['truck']->reg_no);
    $this->get(route('trips.index', ['status' => 'all']))->assertSee(route('trips.show', $w['bomTrip']), false)->assertDontSee(route('trips.show', $w['hoTrip']), false);
    // Kochi's records do not open for him.
    $this->get(route('trips.show', $w['hoTrip']))->assertNotFound();
    $this->get(route('trucks.show', $w['truck']))->assertNotFound();
    $this->get(route('invoices.show', $w['hoBill']))->assertNotFound();
    $this->get(route('trips.show', $w['bomTrip']))->assertOk();
    $this->get(route('trucks.show', $w['shared']))->assertOk();
    // Nor their papers.
    $this->get(route('documents.owner', ['truck', $w['truck']->id]))->assertNotFound();
    $this->get(route('documents.owner', ['truck', $w['mh']->id]))->assertOk();

    // He plans trips for Mumbai only.
    $trip = ['trip_date' => '2026-10-26', 'load_type' => 'ftl', 'trip_kind' => 'empty', 'truck_source' => 'own', 'truck_id' => $w['shared']->id, 'driver_id' => $w['driver']->id,
        'from_place_id' => $w['bom']->id, 'to_place_id' => $w['pnq']->id, 'planned_km' => 150];
    $this->post(route('trips.store'), $trip + ['branch_id' => $w['hoBranch']->id])->assertSessionHasErrors('branch_id');
    $this->post(route('trips.store'), $trip)->assertSessionHasErrors('branch_id');
    $this->post(route('trips.store'), $trip + ['branch_id' => $w['bomBranch']->id])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect(Trip::latest('id')->value('branch_id'))->toBe($w['bomBranch']->id);

    // The owner app, signed in as Vikram: the same limits.
    \Laravel\Sanctum\Sanctum::actingAs($vikram, ['*']);
    phoneReset();
    \Laravel\Sanctum\Sanctum::actingAs($vikram, ['*']);
    $this->withHeaders(['X-Company' => $w['company']->uuid, 'Accept' => 'application/json']);
    $trips = collect($this->getJson('/api/v1/owner/trips?status=planned')->assertOk()->json('trips'))->pluck('uuid');
    $trucks = collect($this->getJson('/api/v1/owner/trucks')->assertOk()->json('trucks'))->pluck('reg_no');
    expect($trips)->not->toContain($w['hoTrip']->uuid)
        ->and($trucks->all())->toContain('MH-04-BB-2222', 'KL-07-CC-3333')->not->toContain($w['truck']->reg_no);
    $this->getJson('/api/v1/owner/trips/' . $w['hoTrip']->uuid)->assertNotFound();
    $this->getJson('/api/v1/approvals')->assertOk();

    // Back to the whole company.
    phoneReset();
    brAs($w['owner'], $w['company']);
    $this->put(route('settings.team.update', $member), ['roles' => ['manager'], 'branch_id' => ''])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect(RoleAssignment::where('company_user_id', $member->id)->pluck('branch_id')->all())->toBe([null]);
});
