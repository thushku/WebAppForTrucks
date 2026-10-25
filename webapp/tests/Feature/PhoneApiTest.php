<?php

use App\Models\Account;
use App\Modules\Fleet\Models\Complaint;
use App\Modules\Road\Models\ConsignmentDelivery;
use App\Modules\Road\Models\DrivingShift;
use App\Modules\Road\Models\EventPhoto;
use App\Modules\Road\Models\GpsPoint;
use App\Modules\Road\Models\RoadEvent;
use App\Modules\Road\Models\RoadEventEdit;
use App\Modules\People\Services\ActivationService;
use App\Modules\Settings\Models\Place;
use App\Modules\Settings\Services\CompanySettings;
use App\Modules\Trips\Models\Consignment;
use App\Modules\Trips\Models\Trip;
use App\Modules\Trips\Services\TripService;
use App\Platform\Audit\AuditLog;
use App\Platform\Auth\CodeSender;
use App\Platform\Devices\Device;
use App\Platform\Settings\PlatformSettings;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// The phone API (M6), tested the way the crew app uses it: sign in with the
// office's code, sync, then push entries, photos, GPS and commands.

/** A company with a trip sent to its crew, and an activation code for each of them. */
function phoneWorld(array $settings = [], bool $coDriver = true): array
{
    $company = makeCompany();

    return app(CompanyContext::class)->runAs($company, function () use ($company, $settings, $coDriver) {
        if ($settings) {
            app(CompanySettings::class)->save($settings);
        }
        $cok    = Place::create(['name' => 'Kochi', 'short_code' => 'COK']);
        $bom    = Place::create(['name' => 'Mumbai', 'short_code' => 'BOM']);
        $acc    = makeParty(['name' => 'ACC Limited', 'short_code' => 'ACC'], ['client', 'consignor']);
        $truck  = makeTruck(['odometer_km' => 100000, 'fuel_tank_l' => 400, 'mileage_loaded_kmpl' => 3.5, 'mileage_empty_kmpl' => 4.5]);
        $driver = makePerson(['name' => 'Ravi Kumar', 'licence_no' => 'KL0720150012345', 'licence_expiry' => '2030-01-01']);
        $co     = $coDriver ? makePerson(['name' => 'Suresh Nair', 'mobile' => '+919847033333', 'licence_no' => 'KL0720160054321', 'licence_expiry' => '2030-01-01']) : null;
        $helper = makePerson(['name' => 'Binu Joseph', 'type' => 'helper', 'mobile' => '+919847022222']);

        $crew = [['person_id' => $driver->id, 'role' => 'driver'], ['person_id' => $helper->id, 'role' => 'helper']];
        if ($co) {
            $crew[] = ['person_id' => $co->id, 'role' => 'co_driver'];
        }

        $trips = app(TripService::class);
        $trip  = $trips->create(
            ['trip_date' => '2026-10-25', 'truck_id' => $truck->id, 'from_place_id' => $cok->id, 'to_place_id' => $bom->id, 'planned_km' => 1380,
                'instructions' => 'Call the office at Pune', 'office_note' => 'Client pays late'],
            $crew,
            ['billing_party_id' => $acc->id, 'consignor_party_id' => $acc->id, 'material' => 'cement', 'actual_weight_mt' => 18.5,
                'freight_basis' => 'tonne', 'rate' => 3400, 'freight' => 62900, 'payment_type' => 'to_be_billed'],
        );
        $trips->assign($trip->fresh());

        $activation = app(ActivationService::class);

        return [
            'company' => $company, 'truck' => $truck, 'trip' => $trip->fresh(['stops', 'consignments']),
            'driver' => $driver, 'co' => $co, 'helper' => $helper,
            'codes' => array_filter([
                'driver' => $activation->issue($driver),
                'co'     => $co ? $activation->issue($co) : null,
                'helper' => $activation->issue($helper),
            ]),
        ];
    });
}

/** Forget whoever made the last request: each phone call starts clean. */
function phoneReset(): void
{
    test()->flushHeaders();
    config(['auth.defaults.guard' => 'web']);
    Auth::forgetGuards();
    app(CompanyContext::class)->forget();
}

/** Sign a phone in with the office's code; its token. */
function phoneActivate(string $mobile, string $code, string $device = 'android-0000-0001'): string
{
    phoneReset();

    return test()->postJson('/api/v1/auth/activate', ['mobile' => $mobile, 'code' => $code, 'device_id' => $device, 'platform' => 'android', 'model' => 'SM-A105'])
        ->assertOk()->json('token');
}

/** The next requests come from this phone. */
function phoneAs(string $token, $company): void
{
    phoneReset();
    test()->withHeaders(['Authorization' => "Bearer {$token}", 'X-Company' => $company->uuid, 'Accept' => 'application/json']);
}

/** The world's crew, each signed in on their own phone. */
function phoneCrew(array $w): array
{
    return [
        'driver' => phoneActivate('+919847011111', $w['codes']['driver'], 'android-driver-01'),
        'co'     => isset($w['codes']['co']) ? phoneActivate('+919847033333', $w['codes']['co'], 'android-codrv-01') : null,
        'helper' => phoneActivate('+919847022222', $w['codes']['helper'], 'android-helper-01'),
    ];
}

function phoneEvent(array $w, string $type, array $fields = []): array
{
    return array_replace_recursive(['uuid' => (string) Str::uuid(), 'trip' => $w['trip']->uuid, 'type' => $type,
        'event_at' => now()->toIso8601String(), 'lat' => 9.9816, 'lng' => 76.2999], $fields);
}

function phoneCommand(array $w, string $name, array $fields = []): array
{
    return array_replace_recursive(['uuid' => (string) Str::uuid(), 'name' => $name, 'trip' => $w['trip']->uuid], $fields);
}

/** Push entries; the per-entry answers. */
function phonePush(array $events, ?int $sentUptime = null): array
{
    return test()->postJson('/api/v1/crew/events', ['sent_uptime_ms' => $sentUptime, 'events' => $events])->assertOk()->json('results');
}

/** Run commands; the per-command answers. */
function phoneRun(array $commands): array
{
    return test()->postJson('/api/v1/crew/commands', ['commands' => $commands])->assertOk()->json('results');
}

function phoneStart(array $w, int $odometer = 100250): array
{
    return phoneRun([phoneCommand($w, 'trip.start', ['event' => ['event_at' => now()->toIso8601String(), 'odometer' => $odometer, 'photos' => ['odometer']]])])[0];
}

it('signs a crew phone in once with the office’s code, and lists its companies', function () {
    $w = phoneWorld();

    phoneReset();
    $res = $this->postJson('/api/v1/auth/activate', ['mobile' => '98470 11111', 'code' => $w['codes']['driver'], 'device_id' => 'android-driver-01', 'platform' => 'android', 'model' => 'SM-A105'])
        ->assertOk()
        ->assertJsonPath('company', $w['company']->uuid)
        ->assertJsonPath('companies.0.crew', true)
        ->assertJsonPath('account.has_pin', false);
    expect($res->json('token'))->toBeString()->not->toBeEmpty();

    // A code works once; a wrong one gets the same answer as a used one.
    phoneReset();
    $this->postJson('/api/v1/auth/activate', ['mobile' => '+919847011111', 'code' => $w['codes']['driver'], 'device_id' => 'android-driver-02', 'platform' => 'android'])
        ->assertStatus(422)->assertJsonPath('code', 'bad_code');
    $this->postJson('/api/v1/auth/activate', ['mobile' => '+919847033333', 'code' => '12345678', 'device_id' => 'android-codrv-01', 'platform' => 'android'])
        ->assertStatus(422)->assertJsonPath('code', 'bad_code');

    $device = Device::first();
    expect($device->device_id)->toBe('android-driver-01')->and($device->app_kind)->toBe('crew')->and($device->token_id)->not->toBeNull();
    app(CompanyContext::class)->set($w['company']);
    expect(AuditLog::where('action', 'device.activated')->value('summary'))->toBe('Ravi Kumar signed in on android SM-A105');

    // The token works without X-Company for a person in one company.
    phoneReset();
    $this->withHeaders(['Authorization' => 'Bearer ' . $res->json('token')])->getJson('/api/v1/crew/sync')->assertOk()->assertJsonPath('person.name', 'Ravi Kumar');
});

it('syncs the trip to its crew without the office’s own notes or the freight', function () {
    $w = phoneWorld();
    $phones = phoneCrew($w);

    phoneAs($phones['driver'], $w['company']);
    $sync = $this->getJson('/api/v1/crew/sync')->assertOk()
        ->assertJsonPath('trips.0.number', '261025-ACC-COK-BOM-01')
        ->assertJsonPath('trips.0.status', 'assigned')
        ->assertJsonPath('trips.0.my_role', 'driver')
        ->assertJsonPath('trips.0.instructions', 'Call the office at Pune')
        ->assertJsonPath('trips.0.loads.0.freight', null)
        ->assertJsonPath('settings.dual_approval_enabled', true)
        ->assertJsonPath('person.can_record', true)
        ->assertJsonCount(2, 'trips.0.stops')
        ->assertJsonCount(3, 'trips.0.crew');
    expect($sync->json('trips.0.loads.0.lr_number'))->toStartWith('LR/26-27/')
        ->and($sync->json('lists.event_types'))->not->toBeEmpty()
        ->and($sync->getContent())->not->toContain('Client pays late');

    // The helper may look, not record (until the owner allows it).
    phoneAs($phones['helper'], $w['company']);
    $this->getJson('/api/v1/crew/sync')->assertJsonPath('person.can_record', false);
    $this->postJson('/api/v1/crew/events', ['events' => [phoneEvent($w, 'note', ['note' => 'Hello'])]])->assertForbidden();
});

it('runs a trip from the phone: accept, start, fuel with photos, GPS, stop, delivery with POD, end', function () {
    Storage::fake('local');
    $w = phoneWorld();
    $phones = phoneCrew($w);
    phoneAs($phones['driver'], $w['company']);

    expect(phoneRun([phoneCommand($w, 'trip.accept')])[0])->toMatchArray(['status' => 200, 'trip_status' => 'accepted']);

    $start = phoneCommand($w, 'trip.start', ['event' => ['event_at' => now()->toIso8601String(), 'odometer' => 100250, 'photos' => ['odometer', 'fuel_gauge']]]);
    expect(phoneRun([$start])[0])->toMatchArray(['status' => 200, 'trip_status' => 'running']);
    // The same command again (the answer was lost): the same answer, nothing done twice.
    expect(phoneRun([$start])[0])->toMatchArray(['status' => 200, 'replayed' => true]);

    app(CompanyContext::class)->set($w['company']);
    $trip = Trip::with('truck')->find($w['trip']->id);
    expect($trip->status)->toBe('running')->and($trip->start_odometer)->toBe(100250)
        ->and($trip->truck->status)->toBe('on_trip')->and($trip->truck->odometer_km)->toBe(100250)
        ->and(DrivingShift::count())->toBe(1)
        ->and(RoadEvent::where('type', 'start_trip')->value('photos_expected'))->toBe(2);

    // Fuel, sent twice.
    phoneAs($phones['driver'], $w['company']);
    $fuel = phoneEvent($w, 'fuel', ['amount' => 19000, 'payment_mode' => 'cash', 'odometer' => 100400, 'photos' => ['odometer', 'pump_meter', 'receipt'],
        'data' => ['litres' => 200, 'rate' => 95, 'full_tank' => true, 'pump' => 'HP Aluva', 'anything' => str_repeat('x', 50)]]);
    $first = phonePush([$fuel])[0];
    expect($first)->toMatchArray(['result' => 'saved', 'status' => 'waiting_check', 'flags' => []]);
    expect(phonePush([$fuel])[0])->toMatchArray(['result' => 'duplicate', 'id' => $first['id']]);

    $receipt = UploadedFile::fake()->image('receipt.jpg', 900, 1200);
    $this->post("/api/v1/crew/events/{$fuel['uuid']}/photos", ['slot' => 'receipt', 'photo' => $receipt])->assertCreated()->assertJsonPath('photos_received', 1);
    $this->post("/api/v1/crew/events/{$fuel['uuid']}/photos", ['slot' => 'receipt', 'photo' => $receipt])->assertOk()->assertJsonPath('result', 'duplicate');

    // GPS: a batch, then the same batch again.
    $this->travel(10)->minutes();
    $batch = ['batch' => (string) Str::uuid(), 'points' => [
        ['t' => now()->subMinutes(2)->toIso8601String(), 'lat' => 9.9816, 'lng' => 76.2999, 'acc' => 12, 'spd' => 0],
        ['t' => now()->subMinute()->toIso8601String(), 'lat' => 9.9900, 'lng' => 76.3050, 'acc' => 10, 'spd' => 52],
        ['t' => now()->toIso8601String(), 'lat' => 9.9990, 'lng' => 76.3100, 'acc' => 9, 'spd' => 60],
    ]];
    $this->postJson('/api/v1/crew/gps', $batch)->assertOk()->assertJsonPath('kept', 3);
    $this->postJson('/api/v1/crew/gps', $batch)->assertOk()->assertJsonPath('replayed', true);

    // Mumbai reached, the load handed over, its POD photo.
    $drop = $w['trip']->stops->last();
    $load = $w['trip']->consignments->first();
    $this->travel(20)->hours();
    expect(phoneRun([phoneCommand($w, 'stop.reached', ['event' => ['event_at' => now()->toIso8601String(), 'data' => ['stop_id' => $drop->id]]])])[0]['status'])->toBe(200);
    $delivered = phoneCommand($w, 'load.delivered', ['event' => ['event_at' => now()->toIso8601String(), 'photos' => ['pod'],
        'data' => ['consignment_id' => $load->id, 'stop_id' => $drop->id, 'packages' => 370, 'short_packages' => 2, 'receiver_name' => 'Store keeper Patil']]]);
    expect(phoneRun([$delivered])[0])->toMatchArray(['status' => 200, 'consignment_status' => 'delivered']);
    $this->post("/api/v1/crew/events/{$delivered['uuid']}/photos", ['slot' => 'pod', 'photo' => UploadedFile::fake()->image('pod.jpg', 1000, 1400)])->assertCreated();

    expect(phoneRun([phoneCommand($w, 'trip.end', ['event' => ['event_at' => now()->toIso8601String(), 'odometer' => 101700]])])[0])->toMatchArray(['status' => 200, 'trip_status' => 'ended']);

    app(CompanyContext::class)->set($w['company']);
    $trip = Trip::with('truck', 'stops')->find($w['trip']->id);
    $load = Consignment::find($load->id);
    $pod  = ConsignmentDelivery::first();
    expect($trip->status)->toBe('ended')->and($trip->end_odometer)->toBe(101700)->and($trip->truck->status)->toBe('idle')
        ->and($trip->stops->last()->arrived_at)->not->toBeNull()
        ->and($load->status)->toBe('delivered')->and($load->pod_status)->toBe('received')
        ->and($pod->receiver_name)->toBe('Store keeper Patil')->and($pod->short_packages)->toBe(2)
        ->and(DrivingShift::whereNull('ended_at')->count())->toBe(0)
        ->and(GpsPoint::where('trip_id', $trip->id)->count())->toBe(3)
        ->and(EventPhoto::count())->toBe(2)
        ->and(RoadEvent::where('uuid', $fuel['uuid'])->first()->data)->not->toHaveKey('anything');

    // The office sees it all on the trip page.
    signInTo($w['company']);
    $this->get(route('trips.show', $trip))->assertOk()
        ->assertSeeText('From the road')->assertSeeText('Fuel')->assertSeeText('HP Aluva')->assertSeeText('received by Store keeper Patil')
        ->assertSeeText('Waiting for check')->assertSeeText('Ravi Kumar');
});

it('sends a payment without receipt to the other driver, who agrees or disputes it', function () {
    $w = phoneWorld();
    $phones = phoneCrew($w);
    phoneAs($phones['driver'], $w['company']);
    phoneStart($w);

    $paid = phoneEvent($w, 'no_receipt', ['amount' => 500, 'payment_mode' => 'cash', 'data' => ['authority' => 'police', 'reason' => 'Overload check at Walayar']]);
    expect(phonePush([$paid])[0]['status'])->toBe('waiting_2nd');

    // Not by the person who paid.
    expect(phoneRun([phoneCommand($w, 'event.second_approve', ['event_uuid' => $paid['uuid']])])[0])->toMatchArray(['status' => 422]);

    // Not by the helper either.
    phoneAs($phones['helper'], $w['company']);
    expect(phoneRun([phoneCommand($w, 'event.second_approve', ['event_uuid' => $paid['uuid']])])[0]['message'])->toBe('You are not allowed to do this.');

    // The co-driver sees it waiting and agrees.
    phoneAs($phones['co'], $w['company']);
    $this->getJson('/api/v1/crew/sync')->assertJsonPath('waiting_for_me.0.uuid', $paid['uuid'])->assertJsonPath('waiting_for_me.0.reason', 'Overload check at Walayar');
    expect(phoneRun([phoneCommand($w, 'event.second_approve', ['event_uuid' => $paid['uuid']])])[0]['event']['status'])->toBe('waiting_check');

    // A second one, disputed with a reason.
    phoneAs($phones['driver'], $w['company']);
    $other = phoneEvent($w, 'no_receipt', ['amount' => 2000, 'payment_mode' => 'cash', 'data' => ['authority' => 'rto', 'reason' => 'Checkpost']]);
    phonePush([$other]);
    phoneAs($phones['co'], $w['company']);
    expect(phoneRun([phoneCommand($w, 'event.second_reject', ['event_uuid' => $other['uuid']])])[0]['status'])->toBe(422);
    expect(phoneRun([phoneCommand($w, 'event.second_reject', ['event_uuid' => $other['uuid'], 'reason' => 'We paid 500 there, not 2000'])])[0]['event']['status'])->toBe('disputed');

    app(CompanyContext::class)->set($w['company']);
    expect(RoadEvent::where('uuid', $paid['uuid'])->value('second_by'))->toBe($w['co']->id)
        ->and(RoadEvent::where('uuid', $other['uuid'])->value('second_reject_reason'))->toBe('We paid 500 there, not 2000')
        ->and(AuditLog::where('action', 'road.second_rejected')->exists())->toBeTrue();
});

it('follows the owner’s rules for one-driver trips and small tolls', function () {
    $w = phoneWorld(['auto_approve_toll_below' => 200], coDriver: false);
    $phones = phoneCrew($w);
    phoneAs($phones['driver'], $w['company']);

    $noReceipt = fn () => phoneEvent($w, 'no_receipt', ['amount' => 300, 'payment_mode' => 'cash', 'data' => ['authority' => 'police', 'reason' => 'Night entry']]);

    // One driver: the manager gives the second approval (A4).
    expect(phonePush([$noReceipt()])[0]['status'])->toBe('waiting_2nd');

    app(CompanyContext::class)->runAs($w['company'], fn () => app(CompanySettings::class)->save(['single_driver_approver' => 'none']));
    phoneAs($phones['driver'], $w['company']);
    expect(phonePush([$noReceipt()])[0]['status'])->toBe('waiting_check');

    // The rule switched off (D7).
    app(CompanyContext::class)->runAs($w['company'], fn () => app(CompanySettings::class)->save(['dual_approval_enabled' => false, 'single_driver_approver' => 'manager']));
    phoneAs($phones['driver'], $w['company']);
    expect(phonePush([$noReceipt()])[0]['status'])->toBe('waiting_check');

    $tolls = phonePush([
        phoneEvent($w, 'toll', ['amount' => 150, 'payment_mode' => 'fastag', 'data' => ['plaza' => 'Paliyekkara']]),
        phoneEvent($w, 'toll', ['amount' => 250, 'payment_mode' => 'fastag', 'data' => ['plaza' => 'Kumbalam']]),
    ]);
    expect(array_column($tolls, 'status'))->toBe(['approved', 'waiting_check']);
});

it('flags a changed clock, a fake location, too much fuel and an odometer going back', function () {
    $w = phoneWorld();
    $phones = phoneCrew($w);
    phoneAs($phones['driver'], $w['company']);
    phoneStart($w, 100250);

    // The phone says two hours ago; its uptime says 1000 seconds ago.
    $results = phonePush([phoneEvent($w, 'fuel', [
        'event_at' => now()->subHours(2)->toIso8601String(), 'uptime_ms' => 9_000_000, 'mock' => true,
        'amount' => 47500, 'payment_mode' => 'cash', 'odometer' => 100100, 'data' => ['litres' => 500, 'rate' => 95],
    ])], sentUptime: 10_000_000);

    // ₹47,500 in cash to one payee in a day is also above India's ₹35,000 limit.
    expect($results[0]['flags'])->toEqualCanonicalizing(['clock_changed', 'mock_location', 'tank_size', 'odometer_order', 'cash_limit']);
    app(CompanyContext::class)->set($w['company']);
    expect(RoadEvent::where('type', 'fuel')->first()->trusted_at->equalTo(now()->subSeconds(1000)))->toBeTrue();
});

it('refuses entries with missing details, one by one, keeping the rest', function () {
    $w = phoneWorld();
    $phones = phoneCrew($w);
    phoneAs($phones['driver'], $w['company']);

    $results = phonePush([
        phoneEvent($w, 'fuel', ['amount' => 1000, 'payment_mode' => 'cash']),       // no litres
        phoneEvent($w, 'teleport', []),                                              // no such type
        phoneEvent($w, 'start_trip', []),                                            // a command, not an entry
        phoneEvent($w, 'toll', ['amount' => 90, 'payment_mode' => 'fastag']),
    ]);

    expect(array_column($results, 'result'))->toBe(['rejected', 'rejected', 'rejected', 'saved'])
        ->and(array_keys($results[0]['errors']))->toContain('data.litres');
});

it('opens a truck complaint for a repair on the road', function () {
    $w = phoneWorld();
    $phones = phoneCrew($w);
    phoneAs($phones['driver'], $w['company']);
    phoneStart($w);

    phonePush([phoneEvent($w, 'repair', ['amount' => 350, 'payment_mode' => 'cash', 'odometer' => 100320, 'data' => ['what' => 'Puncture, rear left', 'workshop' => 'Raju Tyres']])]);
    phoneRun([phoneCommand($w, 'complaint.raise', ['title' => 'Brake pedal feels soft', 'priority' => 'high'])]);

    app(CompanyContext::class)->set($w['company']);
    $complaints = Complaint::orderBy('id')->get();
    expect($complaints->pluck('title')->all())->toBe(['Repair on the road: Puncture, rear left', 'Brake pedal feels soft'])
        ->and($complaints->pluck('trip_id')->unique()->all())->toBe([$w['trip']->id])
        ->and(RoadEvent::where('type', 'repair')->value('complaint_id'))->toBe($complaints->first()->id)
        ->and($complaints->last()->priority)->toBe('high');
});

it('changes the driver with the PIN of the person taking the wheel', function () {
    $w = phoneWorld(['driver_change_photo' => false]);
    $phones = phoneCrew($w);

    phoneAs($phones['co'], $w['company']);
    $this->postJson('/api/v1/auth/pin', ['pin' => '1234'])->assertStatus(422);
    $this->postJson('/api/v1/auth/pin', ['pin' => '4826'])->assertOk();

    phoneAs($phones['driver'], $w['company']);
    phoneStart($w);
    $this->travel(4)->hours();

    $change = fn (string $pin) => phoneCommand($w, 'driver.change', ['pin' => $pin, 'event' => ['event_at' => now()->toIso8601String(), 'odometer' => 100500, 'data' => ['incoming_person_id' => $w['co']->id]]]);
    expect(phoneRun([$change('1111')])[0]['status'])->toBe(422);
    expect(phoneRun([$change('4826')])[0])->toMatchArray(['status' => 200, 'driving' => $w['co']->id]);

    // On his own phone he needs no PIN — but he is already driving.
    phoneAs($phones['co'], $w['company']);
    expect(phoneRun([phoneCommand($w, 'driver.change', ['event' => ['event_at' => now()->toIso8601String(), 'data' => ['incoming_person_id' => $w['co']->id]]])])[0]['message'])->toBe('Suresh Nair is already driving.');
    $this->getJson('/api/v1/crew/sync')->assertJsonPath('driving.person_id', $w['co']->id);

    // An hour's rest on his watch does not count as driving.
    phonePush([phoneEvent($w, 'rest_start', ['event_at' => now()->toIso8601String()])]);
    $this->travel(1)->hours();
    phonePush([phoneEvent($w, 'rest_end', ['event_at' => now()->toIso8601String()])]);
    $this->travel(2)->hours();

    app(CompanyContext::class)->set($w['company']);
    $hours = app(\App\Modules\Road\Services\DrivingHours::class)->forTrip($w['trip']);
    expect($hours[$w['driver']->id]['minutes'])->toBe(240)
        ->and($hours[$w['co']->id])->toMatchArray(['minutes' => 120, 'now' => true]);

    $shifts = DrivingShift::orderBy('id')->get();
    expect($shifts)->toHaveCount(2)->and($shifts[0]->minutes())->toBe(240)->and($shifts[0]->end_odometer)->toBe(100500)
        ->and($shifts[1]->person_id)->toBe($w['co']->id)->and($shifts[1]->ended_at)->toBeNull()
        ->and(json_encode(RoadEvent::where('type', 'driver_change')->first()->data))->not->toContain('4826');
});

it('lets the crew edit or cancel their own entry until it is checked', function () {
    $w = phoneWorld();
    $phones = phoneCrew($w);
    phoneAs($phones['driver'], $w['company']);

    $toll = phoneEvent($w, 'toll', ['amount' => 190, 'payment_mode' => 'cash', 'data' => ['plaza' => 'Paliyekkara']]);
    phonePush([$toll]);
    $this->patchJson("/api/v1/crew/events/{$toll['uuid']}", ['amount' => 195, 'note' => 'Typed wrong'])->assertOk();

    // Not by somebody else.
    phoneAs($phones['co'], $w['company']);
    $this->patchJson("/api/v1/crew/events/{$toll['uuid']}", ['amount' => 1])->assertStatus(422);

    phoneAs($phones['driver'], $w['company']);
    $this->postJson("/api/v1/crew/events/{$toll['uuid']}/cancel", ['reason' => 'Entered twice'])->assertOk();
    $this->patchJson("/api/v1/crew/events/{$toll['uuid']}", ['amount' => 200])->assertStatus(422);

    app(CompanyContext::class)->set($w['company']);
    $event = RoadEvent::where('uuid', $toll['uuid'])->first();
    expect($event->status)->toBe('cancelled')->and((float) $event->amount)->toBe(195.0)
        ->and((float) RoadEventEdit::first()->before['amount'])->toBe(190.0);
});

it('keeps a GPS track, dropping points that cannot be true', function () {
    $w = phoneWorld();
    $phones = phoneCrew($w);
    phoneAs($phones['driver'], $w['company']);
    phoneStart($w);

    $this->postJson('/api/v1/crew/gps', ['batch' => (string) Str::uuid(), 'points' => [
        ['t' => now()->toIso8601String(), 'lat' => 9.9816, 'lng' => 76.2999],
        ['t' => now()->addMinute()->toIso8601String(), 'lat' => 13.0827, 'lng' => 80.2707],   // Chennai a minute later
        ['t' => now()->addMinutes(2)->toIso8601String(), 'lat' => 0, 'lng' => 0],              // no fix
        ['t' => now()->addDay()->toIso8601String(), 'lat' => 9.99, 'lng' => 76.31],              // in the future
        ['t' => now()->addMinutes(3)->toIso8601String(), 'lat' => 9.9850, 'lng' => 76.3010],
        ['lat' => 9.98],                                                                         // no time
    ]])->assertOk()->assertJsonPath('kept', 2)->assertJsonPath('dropped', 4);
});

it('keeps crew inside their own company and their own trips', function () {
    $w = phoneWorld();
    $phones = phoneCrew($w);
    $other = makeCompany(['name' => 'Other Roadlines']);

    phoneReset();
    $this->withHeaders(['Authorization' => "Bearer {$phones['driver']}", 'X-Company' => $other->uuid])->getJson('/api/v1/crew/sync')
        ->assertForbidden()->assertJsonPath('code', 'company_forbidden');

    // A trip they are not on.
    $foreign = app(CompanyContext::class)->runAs($w['company'], function () use ($w) {
        $t = Trip::create(['trip_date' => '2026-10-25', 'truck_id' => null, 'truck_source' => 'hired', 'from_place_id' => $w['trip']->from_place_id, 'to_place_id' => $w['trip']->to_place_id, 'status' => 'running']);

        return $t;
    });
    phoneAs($phones['driver'], $w['company']);
    expect(phonePush([phoneEvent($w, 'note', ['trip' => $foreign->uuid, 'note' => 'x'])])[0]['errors']['trip'][0])->toBe('You are not on this trip.');

    // Somebody else's entry id cannot be reused.
    $mine = phoneEvent($w, 'note', ['note' => 'Mine']);
    phonePush([$mine]);
    phoneAs($phones['co'], $w['company']);
    expect(phonePush([phoneEvent($w, 'note', ['uuid' => $mine['uuid'], 'note' => 'Theirs'])])[0]['result'])->toBe('rejected');
});

it('signs the owner app in with a code by SMS, refreshes and signs out', function () {
    $company = makeCompany();
    $owner = makeAccount(['name' => 'Anil Sharma', 'mobile' => '+919847099999']);
    joinCompany($owner, $company, 'owner');

    $sender = new class(app(PlatformSettings::class)) extends CodeSender {
        public ?string $code = null;

        public function send(\App\Models\Account|\App\Platform\Console\Admin $subject, \App\Platform\Auth\Models\OtpChallenge $challenge, string $code): void
        {
            $this->code = $code;
        }
    };
    app()->instance(CodeSender::class, $sender);

    phoneReset();
    $this->postJson('/api/v1/auth/otp', ['mobile' => '9847099999'])->assertOk();
    $this->postJson('/api/v1/auth/otp', ['mobile' => '9847000000'])->assertOk()->assertJsonPath('message', 'If this number has a login, a code is on its way.');
    expect($sender->code)->toHaveLength(6);

    $device = ['device_id' => 'iphone-owner-0001', 'platform' => 'ios'];
    $this->postJson('/api/v1/auth/otp/verify', ['mobile' => '9847099999', 'code' => '000000'] + $device)->assertStatus(422);
    $token = $this->postJson('/api/v1/auth/otp/verify', ['mobile' => '9847099999', 'code' => $sender->code] + $device)
        ->assertOk()->assertJsonPath('companies.0.crew', false)->json('token');
    expect(Device::where('device_id', 'iphone-owner-0001')->value('app_kind'))->toBe('owner');

    phoneAs($token, $company);
    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('account.name', 'Anil Sharma');
    $this->getJson('/api/v1/crew/sync')->assertForbidden()->assertJsonPath('code', 'not_crew');

    $fresh = $this->postJson('/api/v1/auth/refresh')->assertOk()->json('token');
    phoneAs($token, $company);
    $this->getJson('/api/v1/me')->assertUnauthorized();

    phoneAs($fresh, $company);
    $this->postJson('/api/v1/auth/logout')->assertOk();
    phoneAs($fresh, $company);
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

it('serves the truck’s papers to its crew, and no other papers', function () {
    Storage::fake('local');
    $w = phoneWorld();
    $phones = phoneCrew($w);
    [$rc, $other] = app(CompanyContext::class)->runAs($w['company'], function () use ($w) {
        $docs = app(\App\Modules\Documents\Services\DocumentService::class);
        $rc = $docs->create('truck', $w['truck']->id, ['document_type' => 'rc', 'number' => 'KL07AB1234'], [UploadedFile::fake()->image('rc.jpg')]);
        $otherTruck = makeTruck(['reg_no' => 'KL-07-CD-5678']);
        $other = $docs->create('truck', $otherTruck->id, ['document_type' => 'rc', 'number' => 'KL07CD5678'], [UploadedFile::fake()->image('rc2.jpg')]);

        return [$rc->load('files'), $other->load('files')];
    });

    phoneAs($phones['driver'], $w['company']);
    $this->getJson('/api/v1/crew/sync')->assertJsonPath('papers.0.number', 'KL07AB1234')->assertJsonCount(1, 'papers');
    $this->get("/api/v1/crew/papers/{$rc->id}/files/{$rc->files->first()->id}")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->get("/api/v1/crew/papers/{$other->id}/files/{$other->files->first()->id}")->assertNotFound();
});
