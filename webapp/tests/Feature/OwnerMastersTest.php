<?php

use App\Modules\Fleet\Models\Truck;
use App\Modules\Parties\Models\Party;
use App\Modules\People\Models\Person;
use App\Modules\Settings\Models\Place;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Str;

// The owner app's masters (M29, 13-parity #9): clients, places, trucks and
// crew — list, open, add once per key, edit only what is sent, deactivate.

/** The owner app signed in for a new person with this role. */
function omPhone($company, string $role = 'owner'): void
{
    phoneReset();
    $account = makeAccount(['name' => 'Office ' . $role]);
    joinCompany($account, $company, $role);
    test()->withHeaders(['Authorization' => 'Bearer ' . $account->createToken('owner:android')->plainTextToken, 'X-Company' => $company->uuid, 'Accept' => 'application/json']);
}

it('lists, adds, edits and deactivates clients and places from the phone', function () {
    $w = invWorld();
    omPhone($w['company']);

    $this->getJson('/api/v1/owner/masters')->assertOk()->assertJsonPath('forms.place_types.customer_site', 'Customer site')->assertJsonPath('forms.regions.32', 'Kerala');
    $this->getJson('/api/v1/owner/clients')->assertOk()->assertJsonPath('clients.0.name', 'ACC Limited')->assertJsonPath('clients.0.due', 0);

    $body = ['name' => 'Kerala Agro Foods', 'mobile' => '98470 12345', 'city' => 'Thrissur', 'state_code' => '32', 'tax_id' => invGstin('32', 'AAKCK4455M'), 'credit_days' => 30];
    $key = (string) Str::uuid();
    $made = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/owner/clients', $body)->assertCreated()->json('client');
    $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/owner/clients', $body)->assertCreated()->assertJsonPath('replayed', true);
    expect($made['short_code'])->not->toBeNull()->and($made['mobile'])->toBe('+919847012345')->and($made['tax_id'])->toBe(invGstin('32', 'AAKCK4455M'))->and($made['roles'])->toBe(['client']);
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/owner/clients', ['name' => 'Copy', 'short_code' => $made['short_code']])->assertStatus(422)->assertJsonValidationErrors('short_code');

    // An edit changes only what is sent; tax numbers stay on the web.
    $this->putJson('/api/v1/owner/clients/' . $made['id'], ['email' => 'accounts@keralaagro.in'])->assertOk()
        ->assertJsonPath('client.email', 'accounts@keralaagro.in')->assertJsonPath('client.city', 'Thrissur')->assertJsonPath('client.credit_days', 30);
    $this->putJson('/api/v1/owner/clients/' . $made['id'], ['tax_id' => invGstin('32', 'AAKCK4455M')])->assertStatus(422)->assertJsonValidationErrors('tax_id');
    $this->putJson('/api/v1/owner/clients/' . $made['id'], ['name' => ''])->assertStatus(422)->assertJsonValidationErrors('name');

    // Deactivated, never deleted: off the active list, on the inactive one.
    $this->postJson('/api/v1/owner/clients/' . $made['id'] . '/active', ['active' => false])->assertOk()->assertJsonPath('client.active', false);
    expect(collect($this->getJson('/api/v1/owner/clients')->json('clients'))->pluck('name')->all())->toBe(['ACC Limited'])
        ->and(collect($this->getJson('/api/v1/owner/clients?status=inactive')->json('clients'))->pluck('name')->all())->toBe(['Kerala Agro Foods']);
    $this->getJson('/api/v1/owner/clients?q=kerala&status=all')->assertJsonCount(1, 'clients');

    // Places: a code is found, an address makes it a customer site.
    $place = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/owner/places', ['name' => 'Aluva yard', 'address' => "Plot 4, Edayar\nAluva 683502"])->assertCreated()->json('place');
    expect($place['type'])->toBe('customer_site')->and($place['short_code'])->not->toBeNull();
    $this->putJson('/api/v1/owner/places/' . $place['id'], ['name' => 'Aluva yard 2', 'lat' => 10.1])->assertStatus(422)->assertJsonValidationErrors('lng');
    $this->putJson('/api/v1/owner/places/' . $place['id'], ['name' => 'Aluva yard 2'])->assertOk()->assertJsonPath('place.name', 'Aluva yard 2')->assertJsonPath('place.type', 'customer_site');
    $this->postJson('/api/v1/owner/places/' . $place['id'] . '/active', ['active' => false])->assertOk();
    expect(collect($this->getJson('/api/v1/owner/places')->json('places'))->pluck('name')->all())->not->toContain('Aluva yard 2');

    // Who may: a viewer reads clients but changes nothing.
    omPhone($w['company'], 'viewer');
    $this->getJson('/api/v1/owner/clients')->assertOk();
    $this->putJson('/api/v1/owner/clients/' . $made['id'], ['email' => 'x@y.in'])->assertForbidden();
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/owner/places', ['name' => 'Somewhere'])->assertForbidden();
    app(CompanyContext::class)->set($w['company']);
    expect(Party::find($made['id'])->email)->toBe('accounts@keralaagro.in')->and(Place::find($place['id'])->active)->toBeFalse();
});

it('adds and edits trucks and crew from the phone, and takes them out of service', function () {
    $w = phoneWorld();
    omPhone($w['company']);
    $key = (string) Str::uuid();

    $truck = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/owner/trucks', ['reg_no' => 'kl 07 cx 4400', 'rc_owner_name' => 'Sharma Roadlines', 'truck_type' => 'w12', 'odometer_km' => 85000])
        ->assertCreated()->json('truck');
    expect([$truck['reg_no'], $truck['status'], $truck['fuel_type'], $truck['ownership'], $truck['odometer_locked']])->toBe(['KL 07 CX 4400', 'idle', 'diesel', 'own', true]);
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/owner/trucks', ['reg_no' => 'KL-07-CX-4400', 'rc_owner_name' => 'X Y'])->assertStatus(422)->assertJsonValidationErrors('reg_no');
    $this->putJson('/api/v1/owner/trucks/' . $truck['id'], ['make' => 'Tata', 'model' => 'Signa 4825', 'odometer_km' => 10])->assertOk()
        ->assertJsonPath('truck.make', 'Tata')->assertJsonPath('truck.rc_owner_name', 'Sharma Roadlines')->assertJsonPath('truck.odometer_km', 85000);
    $this->postJson('/api/v1/owner/trucks/' . $truck['id'] . '/status', ['status' => 'workshop'])->assertOk()->assertJsonPath('truck.status', 'workshop');
    $this->getJson('/api/v1/owner/trucks/' . $truck['id'])->assertOk()->assertJsonPath('truck.status_label', 'In workshop');

    // Crew: the list with the papers chip; add a driver; edits; leaving.
    $this->getJson('/api/v1/owner/crew?type=driver')->assertOk()->assertJsonPath('crew.0.name', 'Ravi Kumar')->assertJsonPath('crew.0.papers.state', 'green');
    $made = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/owner/crew', ['name' => 'Manoj Thomas', 'type' => 'driver', 'mobile' => '98470 44444',
        'licence_no' => 'kl07 2019 0001234', 'licence_expiry' => '2031-03-01', 'pay_basis' => 'per_trip', 'per_trip_rate' => 1500])->assertCreated()->json('person');
    expect([$made['mobile'], $made['licence_no'], $made['pay_basis']])->toBe(['+919847044444', 'KL0720190001234', 'per_trip'])->and($made['per_trip_rate'])->toEqual(1500);
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/owner/crew', ['name' => 'Copy', 'type' => 'driver', 'mobile' => '+919847044444'])->assertStatus(422)->assertJsonValidationErrors('mobile');
    $this->putJson('/api/v1/owner/crew/' . $made['id'], ['default_truck_id' => $truck['id']])->assertOk()->assertJsonPath('person.truck', 'KL 07 CX 4400')->assertJsonPath('person.name', 'Manoj Thomas');

    // Ravi uses the crew app with his number, and is on a trip that has not ended.
    app(CompanyContext::class)->set($w['company']);
    Person::whereKey($w['driver']->id)->update(['company_user_id' => \App\Models\CompanyUser::where('company_id', $w['company']->id)->value('id')]);
    $this->putJson('/api/v1/owner/crew/' . $w['driver']->id, ['mobile' => '+919847099999'])->assertStatus(422)->assertJsonValidationErrors('mobile');
    $this->postJson('/api/v1/owner/crew/' . $w['driver']->id . '/active', ['active' => false])->assertStatus(422)->assertJsonValidationErrors('active');

    $this->postJson('/api/v1/owner/crew/' . $made['id'] . '/active', ['active' => false, 'leaving_date' => '2026-10-24'])->assertOk()->assertJsonPath('person.status', 'left')->assertJsonPath('person.leaving_date', '2026-10-24');
    expect(collect($this->getJson('/api/v1/owner/crew')->json('crew'))->pluck('name')->all())->not->toContain('Manoj Thomas');
    $this->postJson('/api/v1/owner/crew/' . $made['id'] . '/active', ['active' => true])->assertOk()->assertJsonPath('person.status', 'active');

    // A workshop login sees trucks but adds none.
    omPhone($w['company'], 'workshop');
    $this->getJson('/api/v1/owner/trucks/' . $truck['id'])->assertOk();
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/owner/trucks', ['reg_no' => 'KL 07 CX 4401', 'rc_owner_name' => 'X Y'])->assertForbidden();
    $this->getJson('/api/v1/owner/crew')->assertForbidden();
    app(CompanyContext::class)->set($w['company']);
    expect(Truck::where('reg_no', 'KL 07 CX 4400')->value('make'))->toBe('Tata');
});

it('keeps what a person limited to a branch adds in that branch', function () {
    $w = phoneWorld();
    $bom = app(CompanyContext::class)->runAs($w['company'], fn () => \App\Modules\Settings\Models\Branch::create(['name' => 'Mumbai branch', 'code' => 'BOM', 'state_code' => '27', 'city' => 'Bhiwandi']));
    phoneReset();
    $vikram = makeAccount(['name' => 'Vikram Rao']);
    $member = \App\Models\CompanyUser::create(['company_id' => $w['company']->id, 'account_id' => $vikram->id, 'status' => 'active', 'joined_at' => now()]);
    app(\App\Platform\Authorization\RoleProvisioner::class)->grant($member, 'manager', $bom->id);
    $this->withHeaders(['Authorization' => 'Bearer ' . $vikram->createToken('owner:android')->plainTextToken, 'X-Company' => $w['company']->uuid, 'Accept' => 'application/json']);

    $head = app(CompanyContext::class)->runAs($w['company'], fn () => \App\Modules\Settings\Models\Branch::where('code', 'HO')->value('id'));
    $truck = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/owner/trucks', ['reg_no' => 'MH 04 BB 7777', 'rc_owner_name' => 'Sharma Roadlines', 'branch_id' => $head])->assertCreated()->json('truck');
    $person = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/owner/crew', ['name' => 'Sanjay Patil', 'type' => 'driver'])->assertCreated()->json('person');
    expect([$truck['branch_id'], $person['branch_id']])->toBe([$bom->id, $bom->id]);
    $this->putJson('/api/v1/owner/trucks/' . $truck['id'], ['branch_id' => $head])->assertOk()->assertJsonPath('truck.branch_id', $bom->id);
});
