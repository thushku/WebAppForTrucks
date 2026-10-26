<?php

use App\Modules\Accounts\Models\LedgerAccount;
use App\Modules\Accounts\Models\Voucher;
use App\Modules\Accounts\Services\Statements;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentRequiredType;
use App\Modules\Trips\Models\TripAdvance;
use App\Modules\Trips\Services\SettlementService;
use App\Platform\Audit\AuditLog;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Str;

// The crew money desk (M27, 13-parity #19, #23): cash given to a crew member
// or spent by them, from the person's page or the owner app, straight into
// their khata; and the people list with each person's papers as one chip.

/** The driver's khata buckets, read in the company. */
function cmBuckets(array $w): array
{
    app(CompanyContext::class)->set($w['company']);

    return app(Statements::class)->crewBuckets(LedgerAccount::where('system_key', 'person:' . $w['driver']->id)->firstOrFail());
}

it('gives a crew member cash for a trip as its advance, or against pay, and records what they spent', function () {
    $w = phoneWorld();
    signInTo($w['company']);
    $tripNo = $w['trip']->trip_number;
    $cash = route('people.cash', $w['driver']);

    $this->get(route('people.show', $w['driver']))->assertOk()->assertSeeText('Khata')->assertSeeText('Record in the khata')->assertSeeText($tripNo)->assertSee('data-show-if="direction=spent"', false);

    // For the trip: the trip's own advance, which its settlement counts.
    $this->post($cash, ['direction' => 'received', 'amount' => 5000, 'remark' => 'Diesel money', 'on' => '2026-10-25', 'trip_id' => $w['trip']->id, 'mode' => 'cash'])
        ->assertRedirect(route('people.show', $w['driver']) . '#cash')->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect(TripAdvance::where('trip_id', $w['trip']->id)->where('person_id', $w['driver']->id)->sum('amount'))->toEqual(5000)
        ->and(app(SettlementService::class)->accounts($w['trip']->fresh())[$w['driver']->id]['advances'])->toEqual(5000.0);

    // Without a trip: an advance against pay, by UPI with its reference.
    $advance = ['direction' => 'received', 'amount' => 2000, 'remark' => 'School fees', 'on' => '2026-10-25', 'trip_id' => '', 'mode' => 'upi', 'purpose' => 'advance'];
    $this->post($cash, $advance)->assertSessionHasErrors('reference');
    $this->post($cash, $advance + ['reference' => 'UPI 9911'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect(Voucher::latest('id')->first()->number)->toStartWith('PV/');

    // Spent from their own pocket: the expense on the trip and truck, owed to them with their pay.
    $this->post($cash, ['direction' => 'spent', 'amount' => 350, 'remark' => 'Toll at Kasara', 'on' => '2026-10-25', 'trip_id' => $w['trip']->id])->assertSessionHasErrors('category');
    $this->post($cash, ['direction' => 'spent', 'amount' => 350, 'remark' => 'Toll at Kasara', 'on' => '2026-10-25', 'trip_id' => $w['trip']->id, 'category' => 'toll'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $toll = Voucher::with('lines')->latest('id')->first();
    expect($toll->voucher_type)->toBe('journal')->and($toll->lines->where('debit', '>', 0)->first()->only(['trip_id', 'truck_id']))->toBe(['trip_id' => $w['trip']->id, 'truck_id' => $w['truck']->id])
        ->and(cmBuckets($w))->toBe(['trip_cash' => 5000.0, 'earnings' => -350.0, 'loan' => 2000.0, 'net' => 6650.0]);

    // Paying back what we owe them clears their earnings, not the advance.
    $this->post($cash, ['direction' => 'received', 'amount' => 350, 'remark' => 'Toll paid back', 'on' => '2026-10-25', 'mode' => 'cash', 'purpose' => 'dues'])->assertSessionHasNoErrors();
    expect(cmBuckets($w))->toBe(['trip_cash' => 5000.0, 'earnings' => 0.0, 'loan' => 2000.0, 'net' => 7000.0])
        ->and(AuditLog::where('action', 'crew.cash_received')->count())->toBe(3)->and(AuditLog::where('action', 'crew.cash_spent')->count())->toBe(1);
    $ledgerId = LedgerAccount::where('system_key', 'person:' . $w['driver']->id)->value('id');
    $this->get(route('people.show', $w['driver']))->assertSeeText('Ravi Kumar will give you ₹7,000.00')->assertSeeText('Advance against pay');
    $this->get(route('ledgers.show', $ledgerId))->assertOk()->assertSeeText('Advance against pay')->assertSeeText('₹2,000.00');

    // Only a trip of this person that is not settled; no date ahead; a remark always.
    $other = makePerson(['name' => 'Manoj Thomas', 'mobile' => '+919847044444'], $w['company']);
    $this->post(route('people.cash', $other), ['direction' => 'received', 'amount' => 100, 'remark' => 'Tea', 'on' => '2026-10-25', 'trip_id' => $w['trip']->id, 'mode' => 'cash'])->assertSessionHasErrors('trip_id');
    $this->post($cash, ['direction' => 'received', 'amount' => 100, 'remark' => 'Tea', 'on' => '2026-11-02', 'mode' => 'cash'])->assertSessionHasErrors('on');
    $this->post($cash, ['direction' => 'received', 'amount' => 100, 'remark' => '', 'on' => '2026-10-25', 'mode' => 'cash'])->assertSessionHasErrors('remark');

    // Giving cash needs the right to settle crew cash.
    signInTo($w['company'], 'viewer');
    $this->get(route('people.show', $w['driver']))->assertOk()->assertDontSeeText('Record in the khata');
    $this->post($cash, ['direction' => 'received', 'amount' => 100, 'remark' => 'Tea', 'on' => '2026-10-25', 'mode' => 'cash'])->assertForbidden();
});

it('lets the owner app read a crew member’s khata and record cash once per key', function () {
    $w = phoneWorld();
    phoneReset();
    $owner = makeAccount(['name' => 'Anil Sharma']);
    joinCompany($owner, $w['company'], 'owner');
    $this->withHeaders(['Authorization' => 'Bearer ' . $owner->createToken('owner:android')->plainTextToken, 'X-Company' => $w['company']->uuid, 'Accept' => 'application/json']);
    $base = '/api/v1/owner/crew/' . $w['driver']->id;

    $this->getJson('/api/v1/owner/masters')->assertOk()->assertJsonPath('cash_modes.upi', 'UPI')->assertJsonFragment(['code' => 'toll', 'name' => 'Toll']);
    $this->getJson($base . '/khata')->assertOk()->assertJsonPath('person.name', 'Ravi Kumar')->assertJsonPath('trips.0.number', $w['trip']->trip_number)->assertJsonPath('net', 0);

    $body = ['direction' => 'received', 'amount' => 3000, 'remark' => 'Trip money', 'on' => '2026-10-25', 'trip_id' => $w['trip']->id, 'mode' => 'cash'];
    $this->postJson($base . '/cash', $body)->assertStatus(428);
    $key = (string) Str::uuid();
    $first = $this->withHeader('Idempotency-Key', $key)->postJson($base . '/cash', $body)->assertCreated()->json();
    $again = $this->withHeader('Idempotency-Key', $key)->postJson($base . '/cash', $body)->assertCreated()->json();
    expect($again['voucher']['number'])->toBe($first['voucher']['number'])->and($again['replayed'])->toBeTrue();
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($base . '/cash', ['direction' => 'spent', 'amount' => 200, 'remark' => 'Food', 'on' => '2026-10-25'])
        ->assertStatus(422)->assertJsonValidationErrors('category');

    app(CompanyContext::class)->set($w['company']);
    expect(TripAdvance::where('trip_id', $w['trip']->id)->count())->toBe(1);
    $this->getJson($base . '/khata')->assertJsonPath('trip_cash', 3000)->assertJsonPath('words', 'Ravi Kumar will give you ₹3,000.00');
});

it('shows each person’s papers as one chip on the people list, with a contact to share', function () {
    $w = phoneWorld();
    signInTo($w['company']);
    app(CompanyContext::class)->set($w['company']);
    DocumentRequiredType::create(['applies_to' => 'person', 'subtype' => 'driver', 'document_type' => 'badge']);
    // Ravi: no badge (his licence is on his record). Suresh: badge due in 10 days. Binu (helper): nothing needed.
    Document::create(['owner_type' => 'person', 'owner_id' => $w['co']->id, 'document_type' => 'badge', 'number' => 'B-2231', 'expires_on' => now()->addDays(10)->toDateString()]);
    Document::create(['owner_type' => 'person', 'owner_id' => $w['helper']->id, 'document_type' => 'aadhaar', 'number' => 'XXXX-1234']);

    $this->get(route('people.index'))->assertOk()->assertSeeText('Papers')
        ->assertSeeInOrder(['Binu Joseph', 'All in order', 'Ravi Kumar', '1 missing', 'Suresh Nair', '1 due soon'])
        ->assertSee('https://wa.me/?text=' . rawurlencode('Ravi Kumar — Driver — +919847011111'), false)
        ->assertSee(route('documents.owner', ['person', $w['driver']->id]), false);
    $this->get(route('people.show', $w['co']))->assertOk()->assertSeeText('1 due soon')->assertSeeText('Share contact')
        ->assertSee('https://wa.me/?text=' . rawurlencode('Suresh Nair — Driver — +919847033333'), false);
});
