<?php

use App\Http\Middleware\ResolveCompany;
use App\Models\Account;
use App\Modules\Road\Models\EventApproval;
use App\Modules\Road\Models\RoadEvent;
use App\Modules\Trips\Models\Trip;
use App\Modules\Trips\Models\TripOfficeEntry;
use App\Modules\Trips\Models\TripSettlement;
use App\Modules\Trips\Services\TripProfit;
use App\Platform\Audit\AuditLog;
use App\Platform\Tenancy\CompanyContext;

// Approvals, settlement and closing (M7): the office checks the crew's
// entries, settles each crew member's trip cash, closes the trip, and can
// still add its own income and expenses afterwards (D8).

/** The office user's next requests (after phone requests in the same test). */
function apOffice(Account $account, $company): void
{
    phoneReset();
    test()->actingAs($account, 'web')->withSession([ResolveCompany::SESSION_KEY => $company->id]);
}

/** An owner of the world's company. */
function apOwner(array $w, string $role = 'owner'): Account
{
    $account = makeAccount(['name' => $role === 'owner' ? 'Anil Sharma' : 'Office ' . $role]);
    joinCompany($account, $w['company'], $role);

    return $account;
}

function apDecide(RoadEvent|int $event, string $decision, ?string $note = null)
{
    return test()->post(route('approvals.decide', is_int($event) ? $event : $event->id), array_filter(['decision' => $decision, 'note' => $note]));
}

it('approves, asks and rejects crew entries, and the crew answer the question', function () {
    $w = phoneWorld();
    $phones = phoneCrew($w);
    $owner = apOwner($w);

    phoneAs($phones['driver'], $w['company']);
    phoneStart($w);
    [$fuel, $toll, $food, $big] = phonePush([
        phoneEvent($w, 'fuel', ['amount' => 9500, 'payment_mode' => 'cash', 'odometer' => 100300, 'data' => ['litres' => 100, 'rate' => 95, 'pump' => 'HP Aluva']]),
        phoneEvent($w, 'toll', ['amount' => 455, 'payment_mode' => 'cash', 'data' => ['plaza' => 'Kumbalam']]),
        phoneEvent($w, 'food', ['amount' => 240, 'payment_mode' => 'cash', 'data' => ['what' => 'Lunch']]),
        phoneEvent($w, 'repair', ['amount' => 40000, 'payment_mode' => 'cash', 'data' => ['what' => 'Clutch plate', 'workshop' => 'Raju Motors']]),
    ]);
    expect($big['flags'])->toContain('cash_limit');

    apOffice($owner, $w['company']);
    $this->get(route('approvals.index'))->assertOk()->assertSeeText('HP Aluva')->assertSeeText('Cash to one payee in a day above the limit');

    // A question needs words; then it goes to the crew.
    apDecide($food['id'], 'query')->assertSessionHasErrors('note');
    apDecide($food['id'], 'query', 'For how many people?')->assertSessionHasNoErrors();
    apDecide($toll['id'], 'reject', 'FASTag already paid this plaza')->assertSessionHasNoErrors();

    phoneAs($phones['driver'], $w['company']);
    $events = collect($this->getJson('/api/v1/crew/sync')->json('events'))->keyBy('uuid');
    expect($events[$food['uuid']])->toMatchArray(['status' => 'queried', 'office_note' => 'For how many people?'])
        ->and($events[$toll['uuid']])->toMatchArray(['status' => 'rejected', 'office_note' => 'FASTag already paid this plaza']);

    // The answer: the entry corrected, back to the office.
    $this->patchJson("/api/v1/crew/events/{$food['uuid']}", ['amount' => 360, 'note' => 'Three people, the helper too'])->assertOk()->assertJsonPath('status', 'waiting_check');
    // A rejected entry is closed for the crew.
    $this->patchJson("/api/v1/crew/events/{$toll['uuid']}", ['amount' => 1])->assertStatus(422);

    // Ticked together: the flagged repair is left for a closer look.
    apOffice($owner, $w['company']);
    $this->post(route('approvals.many'), ['events' => [$fuel['id'], $food['id'], $big['id']]])->assertSessionHas('status', '2 entries approved. 1 with flags or not waiting was left for a closer look.');
    apDecide($big['id'], 'approve')->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($w['company']);
    expect(RoadEvent::whereIn('id', [$fuel['id'], $food['id'], $big['id']])->pluck('status')->unique()->all())->toBe(['approved'])
        ->and(RoadEvent::find($fuel['id'])->verified_by)->toBe($owner->id)
        ->and(EventApproval::where('road_event_id', $food['id'])->orderBy('id')->pluck('decision')->all())->toBe(['query', 'answer', 'approve'])
        ->and(EventApproval::where('decision', 'answer')->value('note'))->toBe('Three people, the helper too')
        ->and(AuditLog::where('action', 'road.reject')->value('reason'))->toBe('FASTag already paid this plaza');

    // The trip timeline shows who decided.
    apOffice($owner, $w['company']);
    $this->get(route('trips.show', $w['trip']))->assertSeeText('Asked · Anil Sharma')->assertSeeText('Answered · Ravi Kumar');
});

it('gives the manager the second approval on a one-driver trip, and waits for a co-driver otherwise', function () {
    $solo = phoneWorld(coDriver: false);
    $phones = phoneCrew($solo);
    $owner = apOwner($solo);
    phoneAs($phones['driver'], $solo['company']);
    $paid = phonePush([phoneEvent($solo, 'no_receipt', ['amount' => 300, 'payment_mode' => 'cash', 'data' => ['authority' => 'police', 'reason' => 'Night entry']])])[0];

    apOffice($owner, $solo['company']);
    $this->get(route('approvals.index', ['status' => 'waiting_2nd']))->assertSeeText('Approve (2nd + check)');
    apDecide($paid['id'], 'approve')->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($solo['company']);
    $event = RoadEvent::find($paid['id']);
    expect($event->status)->toBe('approved')->and($event->second_by_account)->toBe($owner->id)
        ->and(AuditLog::where('action', 'road.approve')->exists())->toBeTrue();

    // With a co-driver on the trip, the office waits for them (D6).
    $pair = phoneWorld();
    app(CompanyContext::class)->forget();
    $driver = phoneActivate('+919847011111', $pair['codes']['driver'], 'android-pair-01');
    $boss = apOwner($pair);
    phoneAs($driver, $pair['company']);
    $other = phonePush([phoneEvent($pair, 'no_receipt', ['amount' => 300, 'payment_mode' => 'cash', 'data' => ['authority' => 'police', 'reason' => 'Night entry']])])[0];
    apOffice($boss, $pair['company']);
    apDecide($other['id'], 'approve')->assertSessionHasErrors(['decision' => 'This entry is waiting for the other driver’s approval.']);
});

it('settles each crew member’s trip cash, closes the trip and keeps office entries open after close', function () {
    $w = phoneWorld();
    $phones = phoneCrew($w);
    $owner = apOwner($w);

    apOffice($owner, $w['company']);
    $this->post(route('trips.advances.store', $w['trip']), ['person_id' => $w['driver']->id, 'amount' => 20000, 'mode' => 'cash', 'given_on' => '2026-10-25'])->assertSessionHasNoErrors();

    phoneAs($phones['driver'], $w['company']);
    phoneStart($w);
    $driverEntries = phonePush([
        phoneEvent($w, 'fuel', ['amount' => 12400, 'payment_mode' => 'cash', 'data' => ['litres' => 130.5, 'pump' => 'IOC Thrissur']]),
        phoneEvent($w, 'toll', ['amount' => 1850, 'payment_mode' => 'cash', 'data' => ['plaza' => 'Paliyekkara']]),
        phoneEvent($w, 'fuel', ['amount' => 10000, 'payment_mode' => 'fuel_card', 'data' => ['litres' => 105, 'pump' => 'HP Salem']]),
        phoneEvent($w, 'money_received', ['amount' => 10000, 'payment_mode' => 'cash', 'data' => ['from' => 'party', 'reference' => 'Freight balance']]),
    ]);
    $paid = phonePush([phoneEvent($w, 'no_receipt', ['amount' => 300, 'payment_mode' => 'cash', 'data' => ['authority' => 'police', 'reason' => 'Udupi check']])])[0];
    phoneAs($phones['co'], $w['company']);
    $coFood = phonePush([phoneEvent($w, 'food', ['amount' => 850, 'payment_mode' => 'upi', 'data' => ['what' => 'Food, two days']])])[0];
    phoneRun([phoneCommand($w, 'event.second_approve', ['event_uuid' => $paid['uuid']])]);

    // Not before the trip has ended.
    apOffice($owner, $w['company']);
    $this->post(route('trips.settle', [$w['trip'], $w['driver']]), ['mode' => 'cash_returned'])->assertSessionHasErrors(['trip' => 'End the trip first.']);

    $this->travel(20)->hours();
    phoneAs($phones['driver'], $w['company']);
    phoneRun([phoneCommand($w, 'trip.end', ['event' => ['event_at' => now()->toIso8601String(), 'odometer' => 101630]])]);

    // Not while entries wait for the office.
    apOffice($owner, $w['company']);
    $this->post(route('trips.settle', [$w['trip'], $w['driver']]), ['mode' => 'cash_returned'])->assertSessionHasErrors(['trip' => 'Not yet: 6 entries wait for the office’s check.']);
    foreach ([...array_column($driverEntries, 'id'), $paid['id'], $coFood['id']] as $id) {
        apDecide($id, 'approve')->assertSessionHasNoErrors();
    }

    $this->get(route('trips.show', $w['trip']))->assertOk()->assertSeeText('Every entry is decided.')
        ->assertSeeText('Returns to the office')->assertSeeText('₹15,450.00')     // 20,000 + 10,000 − (12,400 + 1,850 + 300)
        ->assertSeeText('The company owes')->assertSeeText('₹850.00');

    // The balance decides which ways are possible.
    $this->post(route('trips.settle', [$w['trip'], $w['driver']]), ['mode' => 'paid_to_crew'])->assertSessionHasErrors('mode');
    $this->post(route('trips.settle', [$w['trip'], $w['driver']]), ['mode' => 'cash_returned', 'reference' => 'Receipt 118'])->assertSessionHasNoErrors();
    $this->post(route('trips.settle', [$w['trip'], $w['driver']]), ['mode' => 'cash_returned'])->assertSessionHasErrors('person');
    $this->post(route('trips.settle', [$w['trip'], $w['co']]), ['mode' => 'cash_returned'])->assertSessionHasErrors('mode');
    $this->post(route('trips.settle', [$w['trip'], $w['co']]), ['mode' => 'paid_to_crew', 'bata_amount' => 1500])->assertSessionHasNoErrors();
    $this->post(route('trips.settle', [$w['trip'], $w['helper']]), ['mode' => 'nil'])->assertSessionHasNoErrors()->assertSessionHas('status', 'Everybody is settled. The trip can be closed.');

    app(CompanyContext::class)->set($w['company']);
    $s = TripSettlement::current()->get()->keyBy('person_id');
    expect((float) $s[$w['driver']->id]->balance)->toBe(15450.0)
        ->and((float) $s[$w['driver']->id]->spent)->toBe(14550.0)
        ->and((float) $s[$w['driver']->id]->collected)->toBe(10000.0)
        // 25 Oct 14:30 to 26 Oct 10:30 in Kochi: two days at 600 for drivers, 400 for helpers.
        ->and([(int) $s[$w['driver']->id]->bata_days, (float) $s[$w['driver']->id]->bata_amount])->toBe([2, 1200.0])
        ->and((float) $s[$w['co']->id]->bata_amount)->toBe(1500.0)
        ->and((float) $s[$w['helper']->id]->bata_amount)->toBe(800.0)
        ->and($s[$w['helper']->id]->mode)->toBe('nil')
        ->and(Trip::find($w['trip']->id)->status)->toBe('settled');

    // The crew see their settlement; their entries are locked.
    phoneAs($phones['driver'], $w['company']);
    $this->getJson('/api/v1/crew/sync')->assertJsonPath('trips.0.settlement.balance', 15450)->assertJsonPath('trips.0.settlement.mode', 'cash_returned');
    $this->postJson("/api/v1/crew/events/{$driverEntries[1]['uuid']}/cancel", ['reason' => 'Late change'])->assertStatus(422);

    // Close, then the office still adds a later workshop bill (D8).
    apOffice($owner, $w['company']);
    $this->post(route('trips.close', $w['trip']))->assertSessionHasNoErrors();
    $this->post(route('trips.office.store', $w['trip']), ['kind' => 'expense', 'category' => 'service', 'amount' => 5000, 'entry_date' => '2026-10-26', 'reference' => 'WS-221', 'note' => 'Brake work after the trip'])
        ->assertSessionHasNoErrors();
    $this->post(route('trips.office.store', $w['trip']), ['kind' => 'income', 'category' => 'service', 'amount' => 100, 'entry_date' => '2026-10-26'])->assertSessionHasErrors('category');

    app(CompanyContext::class)->set($w['company']);
    $trip = Trip::find($w['trip']->id);
    $profit = app(TripProfit::class)->forTrip($trip);
    // Income 62,900. Costs: road 12,400 + 1,850 + 10,000 + 300 + 850, bata 1,200 + 1,500 + 800, office 5,000.
    expect($trip->status)->toBe('closed')
        ->and($profit['road'])->toBe(25400.0)->and($profit['bata'])->toBe(3500.0)->and($profit['office_expense'])->toBe(5000.0)
        ->and($profit['profit'])->toBe(29000.0)
        ->and(AuditLog::where('action', 'trip_office_entry.created')->value('entity_label'))->toBe('Expense service 5000.00');

    apOffice($owner, $w['company']);
    $this->get(route('trips.show', $w['trip']))->assertSeeText('Brake work after the trip')->assertSeeText('Trip profit (direct)')->assertSeeText('₹29,000.00');

    // Only the owner reopens, with a reason; the settlements are voided and kept.
    $this->post(route('trips.reopen', $w['trip']), ['reason' => 'short'])->assertSessionHasErrors('reason');
    $this->post(route('trips.reopen', $w['trip']), ['reason' => 'Fuel bill from IOC was double'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect(Trip::find($w['trip']->id)->status)->toBe('ended')
        ->and(TripSettlement::current()->count())->toBe(0)->and(TripSettlement::whereNotNull('voided_at')->count())->toBe(3)
        ->and(AuditLog::where('action', 'trip.reopened')->value('severity'))->toBe('sensitive');
});

it('keeps settling and reopening to the people allowed to', function () {
    $w = phoneWorld();
    $viewer = apOwner($w, 'viewer');
    $manager = apOwner($w, 'manager');
    app(CompanyContext::class)->runAs($w['company'], function () use ($w) {
        app(\App\Modules\Trips\Services\TripService::class)->start($w['trip']->fresh(), 100100);
        app(\App\Modules\Trips\Services\TripService::class)->end(Trip::find($w['trip']->id), 100900);
    });

    apOffice($viewer, $w['company']);
    $this->get(route('approvals.index'))->assertForbidden();
    $this->post(route('trips.settle', [$w['trip'], $w['driver']]), ['mode' => 'nil'])->assertForbidden();

    apOffice($manager, $w['company']);
    foreach (['driver', 'co', 'helper'] as $who) {
        $this->post(route('trips.settle', [$w['trip'], $w[$who]]), ['mode' => 'nil'])->assertSessionHasNoErrors();
    }
    $this->post(route('trips.close', $w['trip']))->assertSessionHasNoErrors();
    // Reopening is the owner's (trip.reopen).
    $this->post(route('trips.reopen', $w['trip']), ['reason' => 'Wrong settlement entered'])->assertForbidden();
});

it('lets the owner app list and decide the entries waiting', function () {
    Illuminate\Support\Facades\Storage::fake('local');
    $w = phoneWorld();
    $phones = phoneCrew($w);
    $owner = apOwner($w);
    $ownerToken = $owner->createToken('owner:iphone')->plainTextToken;

    phoneAs($phones['driver'], $w['company']);
    $fuel = phonePush([phoneEvent($w, 'fuel', ['amount' => 9500, 'payment_mode' => 'cash', 'photos' => ['receipt'], 'data' => ['litres' => 100, 'rate' => 95]])])[0];
    $this->post("/api/v1/crew/events/{$fuel['uuid']}/photos", ['slot' => 'receipt', 'photo' => Illuminate\Http\UploadedFile::fake()->image('r.jpg', 600, 800)])->assertCreated();
    $this->getJson('/api/v1/approvals')->assertForbidden();

    phoneAs($ownerToken, $w['company']);
    $list = $this->getJson('/api/v1/approvals')->assertOk()
        ->assertJsonPath('counts.waiting_check', 1)
        ->assertJsonPath('events.0.uuid', $fuel['uuid'])
        ->assertJsonPath('events.0.by', 'Ravi Kumar')
        ->assertJsonPath('events.0.actions', ['approve', 'query', 'reject']);
    $this->get($list->json('events.0.photos.0.url'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');

    $this->postJson("/api/v1/approvals/{$fuel['uuid']}", ['decision' => 'reject'])->assertStatus(422);
    $this->postJson("/api/v1/approvals/{$fuel['uuid']}", ['decision' => 'approve'])->assertOk()->assertJsonPath('status', 'approved');
    $this->getJson('/api/v1/approvals')->assertJsonCount(0, 'events');
});
