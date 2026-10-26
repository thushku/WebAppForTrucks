<?php

use App\Http\Middleware\ResolveCompany;
use App\Modules\Payroll\Services\PayCalculator;
use App\Modules\People\Models\LeaveRequest;
use App\Modules\People\Services\Leave;
use App\Modules\Trips\Services\TripService;
use App\Platform\Notifications\UserNotification;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

// Leave (M40): asked for from the crew app or recorded by the office,
// approved or turned down, counted against the year's days; unpaid leave
// comes off a monthly salary, and a trip is not sent to someone on leave
// without a reason.

/** Back on the web as this office person. */
function lvOffice(\App\Models\Account $account, $company): void
{
    phoneReset();
    test()->actingAs($account, 'web')->withSession([ResolveCompany::SESSION_KEY => $company->id]);
}

it('lets a driver ask for leave from the app and the office approve it', function () {
    $w = phoneWorld();
    $owner = signInTo($w['company']);
    $tokens = phoneCrew($w);
    phoneAs($tokens['driver'], $w['company']);

    $this->getJson('/api/v1/crew/leave')->assertOk()->assertJsonPath('year.from', '2026-04-01')->assertJsonPath('types.0.name', 'Casual leave')
        ->assertJsonPath('types.0.entitled', 12)->assertJsonPath('types.0.left', 12)->assertJsonPath('types.3.code', 'unpaid')->assertJsonPath('types.3.entitled', null);

    $ask = ['uuid' => (string) Str::uuid(), 'name' => 'leave.apply', 'type' => 'casual', 'from' => '2026-11-02', 'to' => '2026-11-04', 'reason' => 'Sister’s wedding'];
    expect(phoneRun([$ask])[0])->toMatchArray(['status' => 200, 'days' => 3, 'leave' => $ask['uuid']])
        ->and(phoneRun([$ask])[0]['replayed'] ?? false)->toBeTrue();
    // Overlapping days, and more sick leave than the year has, are refused.
    $overlap = phoneRun([['uuid' => (string) Str::uuid(), 'name' => 'leave.apply', 'type' => 'sick', 'from' => '2026-11-04', 'to' => '2026-11-05']])[0];
    $tooMany = phoneRun([['uuid' => (string) Str::uuid(), 'name' => 'leave.apply', 'type' => 'sick', 'from' => '2026-12-01', 'to' => '2026-12-13']])[0];
    expect($overlap['status'])->toBe(422)->and($overlap['message'])->toBe('Leave from 02 Nov to 04 Nov 2026 is asked for already.')
        ->and($tooMany['message'])->toBe('Only 12 days of sick leave are left this year; ask for the rest as unpaid leave.');
    $this->getJson('/api/v1/crew/leave')->assertJsonPath('types.0.waiting', 3)->assertJsonPath('types.0.left', 9)->assertJsonPath('requests.0.status', 'waiting')
        ->assertJsonPath('requests.0.can_cancel', true);

    // Those who approve leave hear of it; the owner approves it on the web; the driver hears that.
    app(CompanyContext::class)->set($w['company']);
    expect(UserNotification::where('kind', 'leave.asked')->where('account_id', $owner->id)->value('title'))->toBe('Ravi Kumar asks for 3 days of leave');
    lvOffice($owner, $w['company']);
    $this->get(route('people.leave'))->assertOk()->assertSeeText('Ravi Kumar')->assertSeeText('02 Nov – 04 Nov 2026')->assertSeeText('Sister’s wedding')->assertSee('value="approve"', false);
    $this->get(route('dashboard'))->assertSeeText('1 leave request waits for you');
    app(CompanyContext::class)->set($w['company']);
    $request = LeaveRequest::sole();
    $this->post(route('people.leave.decide', $request), ['decision' => 'approve'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect($request->fresh()->status)->toBe('approved')->and($request->fresh()->decided_by)->toBe($owner->id)
        ->and(UserNotification::where('kind', 'leave.decided')->value('title'))->toBe('Your leave 02 Nov – 04 Nov is approved');
    $this->get(route('people.show', $w['driver']))->assertSeeText('9 of 12 left')->assertSeeText('Approved');

    // Approved, the driver cannot cancel it from the app (the office can).
    phoneAs($tokens['driver'], $w['company']);
    expect(phoneRun([['uuid' => (string) Str::uuid(), 'name' => 'leave.cancel', 'leave' => $request->uuid]])[0]['message'])->toBe('Only a waiting request can be cancelled; ask the office.');
});

it('takes unpaid leave off a monthly salary and stops a trip to someone on leave', function () {
    $company = makeCompany();
    $owner = signInTo($company);
    [$driver, $trip] = app(CompanyContext::class)->runAs($company, function () {
        $driver = makePerson(['name' => 'Ravi Kumar', 'pay_basis' => 'monthly', 'monthly_salary' => 31000, 'joining_date' => '2026-01-01', 'licence_no' => 'KL0720150012345', 'licence_expiry' => '2030-01-01']);
        $from = \App\Modules\Settings\Models\Place::create(['name' => 'Kochi', 'short_code' => 'COK']);
        $to = \App\Modules\Settings\Models\Place::create(['name' => 'Mumbai', 'short_code' => 'BOM']);
        $trip = app(TripService::class)->create(['trip_date' => '2026-10-27', 'truck_id' => makeTruck()->id, 'from_place_id' => $from->id, 'to_place_id' => $to->id],
            [['person_id' => $driver->id, 'role' => 'driver']]);

        return [$driver, $trip];
    });

    // Recorded by the owner: approved at once.
    $this->post(route('people.leave.store'), ['person_id' => $driver->id, 'leave_type' => 'unpaid', 'from_on' => '2026-10-06', 'to_on' => '2026-10-08', 'reason' => 'Village'])->assertSessionHasNoErrors();
    $this->post(route('people.leave.store'), ['person_id' => $driver->id, 'leave_type' => 'casual', 'from_on' => '2026-10-12', 'to_on' => '2026-10-13'])->assertSessionHasNoErrors();
    $this->post(route('people.leave.store'), ['person_id' => $driver->id, 'leave_type' => 'sick', 'from_on' => '2026-10-26', 'to_on' => '2026-10-28'])->assertSessionHasNoErrors();
    $this->post(route('people.leave.store'), ['person_id' => $driver->id, 'leave_type' => 'sick', 'from_on' => '2026-07-01', 'to_on' => '2026-07-02'])->assertSessionHasErrors('from_on');

    app(CompanyContext::class)->set($company);
    expect(LeaveRequest::pluck('status')->unique()->all())->toBe(['approved'])
        // October: 31 days less 3 unpaid — the casual days are paid.
        ->and(app(PayCalculator::class)->base($driver->fresh(), CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31')))
        ->toMatchArray(['units' => 28.0, 'base' => 28000.0, 'note' => 'Less 3 days of unpaid leave'])
        ->and(app(TripService::class)->dispatchCheck($trip->fresh())['busy'])->toContain('Ravi Kumar is on leave from 26 Oct to 28 Oct.');

    // His sick leave is cancelled: the trip can go.
    $this->post(route('people.leave.cancel', LeaveRequest::where('leave_type', 'sick')->sole()))->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($company);
    expect(app(TripService::class)->dispatchCheck($trip->fresh())['busy'])->toBe([])
        ->and(collect(app(Leave::class)->balances($driver->fresh()))->firstWhere('code', 'casual'))->toMatchArray(['entitled' => 12.0, 'taken' => 2, 'left' => 10.0]);
    $this->get(route('people.index'))->assertOk()->assertDontSeeText('On leave till');
});

it('gives the year’s days for the part of it a person works', function () {
    $company = makeCompany();
    app(CompanyContext::class)->set($company);
    // Joined on 1 October: 182 of the year's 365 days → half of 12 casual days.
    $person = makePerson(['joining_date' => '2026-10-01']);
    expect(collect(app(Leave::class)->balances($person))->firstWhere('code', 'casual')['entitled'])->toBe(6.0)
        ->and(collect(app(Leave::class)->balances($person))->firstWhere('code', 'earned')['entitled'])->toBe(7.5);
});

it('lets the owner app decide leave, once per key', function () {
    $w = phoneWorld();
    $owner = signInTo($w['company']);
    app(CompanyContext::class)->runAs($w['company'], fn () => app(Leave::class)->apply($w['driver'], ['leave_type' => 'earned', 'from_on' => '2026-11-10', 'to_on' => '2026-11-11'], null, 'app'));
    phoneReset();
    $this->withHeaders(['Authorization' => 'Bearer ' . $owner->createToken('owner:android')->plainTextToken, 'X-Company' => $w['company']->uuid, 'Accept' => 'application/json']);

    $id = $this->getJson('/api/v1/owner/leave')->assertOk()->assertJsonPath('requests.0.person.name', 'Ravi Kumar')->assertJsonPath('requests.0.type_name', 'Earned leave')->json('requests.0.id');
    $key = (string) Str::uuid();
    $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/owner/leave/' . $id . '/decide', ['decision' => 'reject', 'note' => 'Two drivers off that week'])
        ->assertCreated()->assertJsonPath('leave.status', 'rejected')->assertJsonPath('leave.note', 'Two drivers off that week');
    $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/owner/leave/' . $id . '/decide', ['decision' => 'reject'])->assertJsonPath('replayed', true);
    $this->getJson('/api/v1/owner/crew/' . $w['driver']->id . '/leave')->assertOk()->assertJsonPath('requests.0.status_label', 'Turned down')
        ->assertJsonPath('types.2.left', 15);
});
