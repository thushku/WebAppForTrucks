<?php

use App\Modules\Fleet\Models\Complaint;
use App\Modules\Fleet\Models\ServiceJob;
use App\Modules\Fleet\Models\ServiceSchedule;
use App\Modules\Fleet\Models\Truck;
use App\Platform\Tenancy\CompanyContext;

// Trucks (M4): profile, status, complaints with history, service schedules and jobs.

it('adds a truck once, however its number is typed', function () {
    $company = makeCompany();
    signInTo($company);

    $this->post(route('trucks.store'), ['reg_no' => 'kl 07  ab 1234', 'rc_owner_name' => 'Sharma Roadlines', 'fuel_type' => 'diesel', 'ownership' => 'own',
        'truck_type' => 'w12', 'body_type' => 'open', 'registration_date' => '2020-07-15', 'odometer_km' => 245000, 'gvw_kg' => 25000, 'unladen_kg' => 8000])
        ->assertSessionHasNoErrors();
    $this->post(route('trucks.store'), ['reg_no' => 'KL-07-AB-1234', 'rc_owner_name' => 'X Y', 'fuel_type' => 'diesel', 'ownership' => 'own'])->assertSessionHasErrors('reg_no');
    $this->post(route('trucks.store'), ['reg_no' => 'KL-07-ZZ-9999', 'fuel_type' => 'diesel', 'ownership' => 'own'])->assertSessionHasErrors('rc_owner_name');
    $this->post(route('trucks.store'), ['reg_no' => 'KL-07-ZZ-9999', 'rc_owner_name' => 'X Y', 'fuel_type' => 'diesel', 'ownership' => 'attached'])->assertSessionHasErrors('owner_party_id');
    $this->post(route('trucks.store'), ['reg_no' => 'KL-07-ZZ-9999', 'rc_owner_name' => 'X Y', 'fuel_type' => 'diesel', 'ownership' => 'own', 'gvw_kg' => 5000, 'unladen_kg' => 8000])->assertSessionHasErrors('unladen_kg');

    app(CompanyContext::class)->set($company);
    $truck = Truck::first();
    expect($truck->reg_no)->toBe('KL 07 AB 1234')->and($truck->reg_key)->toBe('KL07AB1234')
        ->and($truck->odometer_km)->toBe(245000)->and($truck->age())->toBe('6 y 3 m');

    $this->get(route('trucks.show', $truck))->assertOk()->assertSee('KL 07 AB 1234')->assertSee('245,000 km');
    $this->get(route('trucks.index'))->assertOk()->assertSee('KL 07 AB 1234');
});

it('keeps the odometer moving forward only', function () {
    $company = makeCompany();
    signInTo($company);
    $truck = makeTruck(['odometer_km' => 100000], $company);

    // Typed once on the form; after that only records move it.
    $this->put(route('trucks.update', $truck), ['reg_no' => $truck->reg_no, 'rc_owner_name' => 'Sharma Roadlines', 'fuel_type' => 'diesel', 'ownership' => 'own', 'odometer_km' => 5])->assertSessionHasNoErrors();
    expect($truck->fresh()->odometer_km)->toBe(100000);

    $this->post(route('trucks.status', $truck), ['status' => 'workshop', 'odometer_km' => 99000])->assertSessionHasNoErrors();
    expect($truck->fresh()->odometer_km)->toBe(100000)->and($truck->fresh()->status)->toBe('workshop');
    $this->post(route('trucks.status', $truck), ['status' => 'idle', 'odometer_km' => 100500]);
    expect($truck->fresh()->odometer_km)->toBe(100500);

    $truck->forceFill(['status' => 'on_trip'])->save();
    $this->post(route('trucks.status', $truck), ['status' => 'idle'])->assertSessionHasErrors('status');
    $this->post(route('trucks.status', $truck), ['status' => 'on_trip'])->assertSessionHasErrors('status');
});

it('keeps trucks within their company and their permissions', function () {
    $company = makeCompany();
    $other   = makeCompany(['name' => 'Other Transport']);
    $theirs  = makeTruck(['reg_no' => 'MH-04-XX-0001'], $other);
    // The same number can be in two companies (a truck sold from one to the other).
    $mine    = makeTruck(['reg_no' => 'MH-04-XX-0001'], $company);

    signInTo($company, 'viewer');
    $this->get(route('trucks.show', $mine))->assertOk();
    $this->get(route('trucks.show', $theirs))->assertNotFound();
    $this->get(route('trucks.create'))->assertForbidden();
    $this->post(route('trucks.complaints.store', $mine), ['title' => 'Noise', 'priority' => 'normal'])->assertForbidden();
});

it('raises a complaint with a number, and keeps its history', function () {
    $company = makeCompany();
    $owner   = signInTo($company);
    $truck   = makeTruck(['odometer_km' => 120000], $company);

    $this->post(route('trucks.complaints.store', $truck), ['title' => 'Brake noise, front left', 'priority' => 'high', 'category' => 'brakes', 'odometer_km' => 120450, 'description' => 'Squeals when braking'])
        ->assertRedirect();
    $this->post(route('trucks.complaints.store', $truck), ['title' => 'AC not cooling', 'priority' => 'normal']);

    app(CompanyContext::class)->set($company);
    [$first, $second] = Complaint::orderBy('id')->get();
    expect($first->number)->toBe('C-1')->and($second->number)->toBe('C-2')
        ->and($truck->fresh()->odometer_km)->toBe(120450)
        ->and($first->updates()->count())->toBe(1);

    $this->put(route('complaints.update', $first), ['status' => 'closed', 'note' => 'skip'])->assertSessionHasNoErrors();
    $this->put(route('complaints.update', $second), ['status' => 'open'])->assertSessionHasErrors('note');
    $this->put(route('complaints.update', $second), ['status' => 'in_progress', 'note' => 'Sent to Malabar Truck Works', 'assigned_to' => $owner->id])->assertSessionHasNoErrors();
    $this->put(route('complaints.update', $second), ['status' => 'closed'])->assertSessionHasErrors('status');
    $this->put(route('complaints.update', $second), ['status' => 'solved', 'assigned_to' => $owner->id])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($company);
    $second->refresh();
    expect($second->status)->toBe('solved')->and($second->solved_at)->not->toBeNull()
        ->and($second->assigned_to)->toBe($owner->id)
        ->and($second->updates()->pluck('status_to')->all())->toBe(['open', 'in_progress', 'solved']);

    $this->get(route('complaints.show', $second))->assertOk()->assertSee('Sent to Malabar Truck Works');
    $this->get(route('complaints.index'))->assertOk()->assertSee('AC not cooling');
});

it('lets the crew raise a complaint but not work on it', function () {
    $company = makeCompany();
    $truck   = makeTruck([], $company);
    signInTo($company, 'driver');

    $this->post(route('trucks.complaints.store', $truck), ['title' => 'Tyre bulge rear right', 'priority' => 'urgent'])->assertRedirect();
    app(CompanyContext::class)->set($company);
    $complaint = Complaint::first();
    $this->put(route('complaints.update', $complaint), ['status' => 'solved', 'note' => 'done'])->assertForbidden();
});

it('records a service job with its own totals and resets the schedule when done', function () {
    $company = makeCompany();
    signInTo($company);
    $truck  = makeTruck(['odometer_km' => 148000, 'status' => 'workshop'], $company);
    $vendor = makeParty(['name' => 'Malabar Truck Works'], ['vendor'], $company);

    $this->post(route('trucks.schedules.store', $truck), ['name' => 'Engine oil'])->assertSessionHasErrors('every_km');
    $this->post(route('trucks.schedules.store', $truck), ['name' => 'Engine oil', 'every_km' => 30000, 'every_days' => 180, 'last_done_km' => 120000, 'last_done_on' => '2026-05-01'])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($company);
    $schedule = ServiceSchedule::first();
    expect($schedule->dueKm())->toBe(150000)->and($schedule->state(148000))->toBe('soon')->and($schedule->state(151000))->toBe('overdue');

    $this->post(route('trucks.service.store', $truck), [
        'job_date' => '2026-10-24', 'kind' => 'scheduled', 'odometer_km' => 149200, 'vendor_party_id' => $vendor->id, 'service_schedule_id' => $schedule->id,
        'items' => [
            ['kind' => 'part', 'description' => 'Engine oil 15W40', 'qty' => '28', 'rate' => '310'],
            ['kind' => 'part', 'description' => 'Oil filter', 'qty' => '2', 'rate' => '650.50'],
            ['kind' => 'labour', 'description' => 'Labour', 'qty' => '1', 'rate' => '1200'],
            ['kind' => 'part', 'description' => '', 'qty' => '', 'rate' => ''],
        ],
        // A total typed into the form is ignored: the lines decide.
        'total' => '1',
    ])->assertRedirect();

    app(CompanyContext::class)->set($company);
    $job = ServiceJob::first();
    expect($job->number)->toBe('SJ/26-27/0001')
        ->and((float) $job->parts_total)->toBe(9981.0)
        ->and((float) $job->labour_total)->toBe(1200.0)
        ->and((float) $job->total)->toBe(11181.0)
        ->and($job->items()->count())->toBe(3);

    $this->post(route('trucks.service.complete', [$truck, $job]))->assertRedirect();

    app(CompanyContext::class)->set($company);
    expect($job->fresh()->status)->toBe('done')
        ->and($schedule->fresh()->last_done_km)->toBe(149200)
        ->and($schedule->fresh()->last_done_on->toDateString())->toBe('2026-10-24')
        ->and($truck->fresh()->odometer_km)->toBe(149200)
        ->and($truck->fresh()->status)->toBe('idle');

    // Done is final.
    $this->put(route('trucks.service.update', [$truck, $job]), ['job_date' => '2026-10-24', 'kind' => 'scheduled', 'items' => []])->assertSessionHasErrors('job');
    $this->post(route('trucks.service.complete', [$truck, $job]))->assertSessionHasErrors('job');
});

it('does not open another truck\'s job through this truck\'s address', function () {
    $company = makeCompany();
    signInTo($company);
    $a = makeTruck(['reg_no' => 'KL-01-A-1'], $company);
    $b = makeTruck(['reg_no' => 'KL-01-B-2'], $company);
    $this->post(route('trucks.service.store', $b), ['job_date' => '2026-10-20', 'kind' => 'breakdown', 'items' => [['description' => 'Tow', 'qty' => 1, 'rate' => 3000]]]);
    app(CompanyContext::class)->set($company);

    $this->get(route('trucks.service.edit', [$a, ServiceJob::first()]))->assertNotFound();
});
