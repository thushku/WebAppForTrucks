<?php

use App\Http\Middleware\ResolveCompany;
use App\Models\Account;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Services\DocumentReminders;
use App\Modules\Payroll\Models\PayRun;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Trips\Models\Trip;
use App\Platform\Devices\Device;
use App\Platform\Notifications\Notifier;
use App\Platform\Notifications\Push\PushGateway;
use App\Platform\Notifications\UserNotification;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Facades\Http;

// Notifications (M20): the crew hear of a trip sent or called off, a question
// on their entry and their pay; the co-driver is asked to approve; those who
// approve get one notice that keeps count; pushes go through Firebase with
// the service-account key; the inbox is the same on the web and the phones.

/** Pushes are kept here instead of sent; what was sent, to which token. */
function notePushes(): object
{
    $fake = new class implements PushGateway
    {
        public array $sent = [];

        public function send(string $token, array $message): string
        {
            $this->sent[] = ['token' => $token] + $message;

            return str_starts_with($token, 'gone-') ? self::INVALID_TOKEN : self::SENT;
        }
    };
    app()->instance(PushGateway::class, $fake);

    return $fake;
}

/** This phone tells the server its Firebase token. */
function notePhoneToken(string $phone, $company, string $pushToken): void
{
    phoneAs($phone, $company);
    test()->postJson('/api/v1/device', ['push_token' => $pushToken, 'app_version' => '1.0.0'])->assertOk();
}

/** An office member's owner app, signed in with a push token. */
function noteOwnerPhone(Account $account, string $pushToken): Device
{
    return Device::create(['account_id' => $account->id, 'device_id' => 'owner-' . $account->id, 'platform' => 'android', 'app_kind' => 'owner', 'push_token' => $pushToken, 'activated_at' => now()]);
}

function noteOffice(Account $account, $company): void
{
    phoneReset();
    test()->actingAs($account, 'web')->withSession([ResolveCompany::SESSION_KEY => $company->id]);
}

function noteMember(array $w, string $role, string $name): Account
{
    $account = makeAccount(['name' => $name]);
    joinCompany($account, $w['company'], $role);

    return $account;
}

it('tells the crew of a trip called off and a trip sent, on their phones and in their inbox', function () {
    $push = notePushes();
    $w = phoneWorld();
    $phones = phoneCrew($w);
    notePhoneToken($phones['driver'], $w['company'], 'fcm-ravi');
    notePhoneToken($phones['helper'], $w['company'], 'fcm-binu');

    // The office calls the first trip off and sends another with the same crew.
    app(CompanyContext::class)->set($w['company']);
    $trips = app(\App\Modules\Trips\Services\TripService::class);
    $trips->cancel($w['trip']->fresh(), 'Client postponed the load');
    $next = $trips->create(['trip_date' => '2026-10-26', 'truck_id' => $w['truck']->id, 'from_place_id' => $w['trip']->from_place_id, 'to_place_id' => $w['trip']->to_place_id, 'planned_km' => 1380],
        [['person_id' => $w['driver']->id, 'role' => 'driver'], ['person_id' => $w['helper']->id, 'role' => 'helper'], ['person_id' => $w['co']->id, 'role' => 'co_driver']],
        ['billing_party_id' => $w['trip']->consignments->first()->billing_party_id, 'consignor_party_id' => $w['trip']->consignments->first()->billing_party_id, 'material' => 'cement',
            'actual_weight_mt' => 18, 'freight_basis' => 'tonne', 'rate' => 3400, 'freight' => 61200, 'payment_type' => 'to_be_billed']);
    $trips->assign($next->fresh());
    $next = Trip::find($next->id);

    // Ravi and Binu got both on their phones; Suresh's phone has no push token yet, the notices wait in his inbox.
    $toRavi = collect($push->sent)->where('token', 'fcm-ravi')->values();
    expect(collect($push->sent)->pluck('token')->countBy()->all())->toBe(['fcm-ravi' => 2, 'fcm-binu' => 2])
        ->and($toRavi[1]['title'])->toBe("New trip {$next->trip_number}")
        ->and($toRavi[1]['body'])->toBe('Kochi → Mumbai · ' . $w['truck']->reg_no . ' · 26 Oct')
        ->and($toRavi[1]['data'])->toMatchArray(['kind' => 'trip.sent', 'type' => 'trip', 'trip' => $next->uuid, 'company' => $w['company']->uuid])
        ->and($toRavi[1]['badge'])->toBe(2)
        ->and($toRavi[0]['title'])->toBe("Trip {$w['trip']->trip_number} is cancelled")
        ->and($toRavi[0]['body'])->toBe('Client postponed the load')
        ->and(UserNotification::where('kind', 'trip.sent')->pluck('push_result')->sort()->values()->all())->toBe(['no_device', 'sent', 'sent']);

    // The phone's inbox: newest first; reading clears the badge count.
    phoneAs($phones['driver'], $w['company']);
    $inbox = $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('unread', 2)->assertJsonPath('more', false);
    expect($inbox->json('items.0'))->toMatchArray(['kind' => 'trip.sent', 'read' => false, 'data' => ['type' => 'trip', 'trip' => $next->uuid]])
        ->and($inbox->json('items.1.kind'))->toBe('trip.cancelled');
    $this->getJson('/api/v1/notifications?limit=1')->assertJsonCount(1, 'items')->assertJsonPath('more', true);
    $this->postJson('/api/v1/notifications/read', ['ids' => [$inbox->json('items.0.id')]])->assertOk()->assertJsonPath('unread', 1);
    $this->getJson('/api/v1/me')->assertJsonPath('unread_notifications', 1);
    $this->postJson('/api/v1/notifications/read', ['all' => true])->assertJsonPath('unread', 0);

    // Nobody reads another person's notices.
    phoneAs($phones['helper'], $w['company']);
    $this->postJson('/api/v1/notifications/read', ['ids' => [$inbox->json('items.1.id')]])->assertJsonPath('unread', 2);
});

it('asks the co-driver to approve, keeps one counting notice for those who approve, and tells the crew of a question', function () {
    $push = notePushes();
    $w = phoneWorld();
    $phones = phoneCrew($w);
    $owner = noteMember($w, 'owner', 'Anil Sharma');
    $manager = noteMember($w, 'manager', 'Vikram Rao');
    $workshop = noteMember($w, 'workshop', 'Joy Mechanic');
    noteOwnerPhone($manager, 'fcm-vikram');
    notePhoneToken($phones['co'], $w['company'], 'fcm-suresh');
    notePhoneToken($phones['driver'], $w['company'], 'fcm-ravi');

    // A payment without receipt: Suresh, the other driver, is asked.
    phoneAs($phones['driver'], $w['company']);
    phoneStart($w);
    $paid = phonePush([phoneEvent($w, 'no_receipt', ['amount' => 300, 'payment_mode' => 'cash', 'data' => ['authority' => 'police', 'reason' => 'Night entry']])])[0];
    $asked = collect($push->sent)->firstWhere('token', 'fcm-suresh');
    expect($asked['title'])->toBe('Ravi Kumar asks you to approve')
        ->and($asked['body'])->toBe('Payment without receipt ₹300.00 · ' . $w['trip']->trip_number)
        ->and($asked['data'])->toMatchArray(['type' => 'entry', 'entry' => $paid['uuid'], 'trip' => $w['trip']->uuid]);

    // He approves: the office has one entry to check.
    phoneAs($phones['co'], $w['company']);
    phoneRun([phoneCommand($w, 'event.second_approve', ['event_uuid' => $paid['uuid']])]);
    app(CompanyContext::class)->set($w['company']);
    expect(UserNotification::where('kind', 'approvals.waiting')->pluck('account_id')->sort()->values()->all())->toBe(collect([$owner->id, $manager->id])->sort()->values()->all())
        ->and(UserNotification::for($manager)->where('kind', 'approvals.waiting')->value('title'))->toBe('1 entry waits for your approval')
        ->and(collect($push->sent)->where('token', 'fcm-vikram'))->toHaveCount(1);

    // More entries update the same notice; the phone is not pushed again within half an hour…
    phoneAs($phones['driver'], $w['company']);
    [$fuel] = phonePush([phoneEvent($w, 'fuel', ['amount' => 9500, 'payment_mode' => 'cash', 'odometer' => 100300, 'data' => ['litres' => 100, 'rate' => 95, 'pump' => 'HP Aluva']])]);
    app(CompanyContext::class)->set($w['company']);
    $notice = UserNotification::for($manager)->where('kind', 'approvals.waiting')->sole();
    expect([$notice->title, $notice->times, $notice->push_result])->toBe(['2 entries wait for your approval', 2, 'held'])
        ->and($notice->body)->toBe('Ravi Kumar: Fuel ₹9,500.00 · ' . $w['trip']->trip_number)
        ->and(collect($push->sent)->where('token', 'fcm-vikram'))->toHaveCount(1);
    // … but is after a quiet half hour.
    $this->travel(31)->minutes();
    phoneAs($phones['driver'], $w['company']);
    [$food] = phonePush([phoneEvent($w, 'food', ['amount' => 240, 'payment_mode' => 'cash', 'data' => ['what' => 'Lunch']])]);
    expect(collect($push->sent)->where('token', 'fcm-vikram'))->toHaveCount(2)
        ->and(collect($push->sent)->where('token', 'fcm-vikram')->last()['title'])->toBe('3 entries wait for your approval');

    // The bell on the web: the count, the page, opening goes to the approvals and marks it read.
    noteOffice($owner, $w['company']);
    $this->get(route('dashboard'))->assertOk()->assertSee('data-bell', false)->assertSee('Notifications, 1 unread');
    $this->get(route('notifications.index'))->assertOk()->assertSeeText('3 entries wait for your approval')->assertSeeText('updated 2 times');
    app(CompanyContext::class)->set($w['company']);
    $mine = UserNotification::for($owner)->sole();
    $this->get(route('notifications.open', $mine->id))->assertRedirect('/approvals');
    app(CompanyContext::class)->set($w['company']);
    expect($mine->fresh()->read_at)->not->toBeNull();
    $this->get(route('notifications.open', UserNotification::withoutGlobalScopes()->where('account_id', $manager->id)->value('id')))->assertNotFound();

    // A question reaches the driver; once nothing waits, the manager's notice is done with.
    $this->post(route('approvals.decide', $fuel['id']), ['decision' => 'query', 'note' => 'Which pump, and the bill?'])->assertSessionHasNoErrors();
    $question = collect($push->sent)->where('token', 'fcm-ravi')->last();
    expect($question['title'])->toBe('Question on your Fuel ₹9,500.00')
        ->and($question['body'])->toBe('Which pump, and the bill? · ' . $w['trip']->trip_number)
        ->and($question['data']['kind'])->toBe('entry.queried');
    app(CompanyContext::class)->set($w['company']);
    expect(UserNotification::for($manager)->whereNull('read_at')->pluck('title')->all())->toBe(['2 entries wait for your approval']);
    noteOffice($owner, $w['company']);
    $this->post(route('approvals.decide', $paid['id']), ['decision' => 'approve'])->assertSessionHasNoErrors();
    $this->post(route('approvals.decide', $food['id']), ['decision' => 'approve'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect(UserNotification::for($manager)->whereNull('read_at')->count())->toBe(0)
        ->and(UserNotification::for($workshop)->count())->toBe(0);

    $this->post(route('notifications.read-all'))->assertRedirect();
});

it('tells the office of an incident at once, a person of their pay, and those who keep the papers what runs out', function () {
    $push = notePushes();
    $w = phoneWorld();
    $phones = phoneCrew($w);
    $owner = noteMember($w, 'owner', 'Anil Sharma');
    noteOwnerPhone($owner, 'fcm-anil');
    notePhoneToken($phones['driver'], $w['company'], 'fcm-ravi');

    phoneAs($phones['driver'], $w['company']);
    phoneStart($w);
    phonePush([phoneEvent($w, 'incident', ['data' => ['kind' => 'accident', 'description' => 'Rear bumper hit at Walayar']])]);
    $incident = collect($push->sent)->firstWhere('token', 'fcm-anil');
    expect($incident['title'])->toBe('Incident on ' . $w['trip']->trip_number)
        ->and($incident['body'])->toContain('Rear bumper hit at Walayar')
        ->and($incident['data']['kind'])->toBe('road.incident');

    // Ravi's pay for October, paid by bank.
    app(CompanyContext::class)->set($w['company']);
    $w['driver']->forceFill(['pay_basis' => 'monthly', 'monthly_salary' => 31000, 'joining_date' => '2026-01-01'])->save();
    noteOffice($owner, $w['company']);
    $this->post(route('payroll.store'), ['period_from' => '2026-10-01', 'period_to' => '2026-10-31', 'scope' => 'crew'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $run = PayRun::firstOrFail();
    $slip = Payslip::where('person_id', $w['driver']->id)->firstOrFail();
    $this->post(route('payroll.approve', $run))->assertSessionHasNoErrors();
    $this->post(route('payroll.pay', [$run, $slip->id]), ['mode' => 'bank', 'paid_on' => '2026-10-25', 'reference' => 'UTR 77120'])->assertSessionHasNoErrors();
    $pay = collect($push->sent)->where('token', 'fcm-ravi')->last();
    expect($pay['title'])->toBe('Your pay for 1–31 Oct 2026 is paid')
        ->and($pay['body'])->toBe('₹31,000.00 · Bank transfer · UTR 77120')
        ->and($pay['data'])->toMatchArray(['kind' => 'pay.paid', 'type' => 'money']);

    // The truck's insurance runs out in 5 days: the email and a notice for the owner.
    app(CompanyContext::class)->set($w['company']);
    Document::create(['owner_type' => 'truck', 'owner_id' => $w['truck']->id, 'document_type' => 'insurance', 'number' => 'POL-778', 'expires_on' => now()->addDays(5)->toDateString()]);
    app(DocumentReminders::class)->runFor($w['company']);
    app(CompanyContext::class)->set($w['company']);
    $papers = UserNotification::for($owner)->where('kind', 'papers.due')->sole();
    expect($papers->title)->toBe('1 paper needs attention')
        ->and($papers->body)->toContain($w['truck']->reg_no)->toContain('POL-778')
        ->and($papers->url)->toBe('/documents');
});

it('pushes through Firebase with the service-account key, and forgets a phone Firebase no longer knows', function () {
    $w = invWorld();
    app(CompanyContext::class)->set($w['company']);
    $owner = $w['owner'];
    $good = noteOwnerPhone($owner, 'tok-good');
    $gone = Device::create(['account_id' => $owner->id, 'device_id' => 'old-phone', 'platform' => 'android', 'app_kind' => 'owner', 'push_token' => 'tok-gone', 'activated_at' => now()]);

    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $pem);
    $public = openssl_pkey_get_details($key)['key'];
    $path = tempnam(sys_get_temp_dir(), 'fcm');
    file_put_contents($path, json_encode(['type' => 'service_account', 'project_id' => 'fleet-test', 'client_email' => 'push@fleet-test.iam.gserviceaccount.com',
        'private_key' => $pem, 'token_uri' => 'https://oauth2.googleapis.com/token']));
    config(['services.fcm.driver' => 'fcm', 'services.fcm.credentials' => $path]);

    $down = false;
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test-token', 'expires_in' => 3599, 'token_type' => 'Bearer']),
        'fcm.googleapis.com/*' => function ($request) use (&$down) {
            if ($down) {
                return Http::response(['error' => ['code' => 503, 'status' => 'UNAVAILABLE']], 503);
            }

            return $request['message']['token'] === 'tok-gone'
                ? Http::response(['error' => ['code' => 404, 'status' => 'NOT_FOUND', 'message' => 'Requested entity was not found.',
                    'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED']]]], 404)
                : Http::response(['name' => 'projects/fleet-test/messages/0:1']);
        },
    ]);

    app(Notifier::class)->tell([$owner->id], 'test', 'Hello from the office', 'Is push working?', null, ['type' => 'test']);

    // The sign-in: a JWT for the messaging scope, signed with the key.
    Http::assertSent(function ($request) use ($public) {
        if (! str_starts_with($request->url(), 'https://oauth2.googleapis.com/token')) {
            return false;
        }
        [$head, $claims, $signature] = explode('.', $request['assertion']);
        $decode = fn ($s) => base64_decode(strtr($s, '-_', '+/'));
        $c = json_decode($decode($claims), true);

        return $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
            && $c['iss'] === 'push@fleet-test.iam.gserviceaccount.com' && $c['scope'] === 'https://www.googleapis.com/auth/firebase.messaging'
            && openssl_verify("{$head}.{$claims}", $decode($signature), $public, OPENSSL_ALGO_SHA256) === 1;
    });
    // The message, as FCM HTTP v1 wants it.
    Http::assertSent(fn ($request) => $request->url() === 'https://fcm.googleapis.com/v1/projects/fleet-test/messages:send'
        && $request->hasHeader('Authorization', 'Bearer ya29.test-token')
        && $request['message']['token'] === 'tok-good'
        && $request['message']['notification'] === ['title' => 'Hello from the office', 'body' => 'Is push working?']
        && $request['message']['data']['company'] === $w['company']->uuid && $request['message']['data']['type'] === 'test'
        && $request['message']['android']['priority'] === 'high' && $request['message']['apns']['payload']['aps']['badge'] === 1);
    expect($gone->fresh()->push_token)->toBeNull()
        ->and($good->fresh()->push_token)->toBe('tok-good')
        ->and(UserNotification::for($owner)->value('push_result'))->toBe('sent');

    // The access token is kept for the hour: the next push signs in no more.
    app(Notifier::class)->tell([$owner->id], 'test', 'Second', null);
    Http::assertSentCount(4);

    // Firebase down: the push is marked failed (a queue worker tries again later); the inbox has it all the same.
    $down = true;
    app(Notifier::class)->tell([$owner->id], 'test', 'Third', null);
    expect(UserNotification::for($owner)->latest('id')->value('push_result'))->toBe('failed')
        ->and(UserNotification::for($owner)->count())->toBe(3);

    // No readable key: pushes go to the log instead.
    config(['services.fcm.credentials' => '/nowhere/key.json']);
    expect(app(PushGateway::class))->toBeInstanceOf(\App\Platform\Notifications\Push\LogGateway::class);
    @unlink($path);
});
