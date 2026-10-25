<?php

use App\Models\Account;
use App\Models\CompanyUser;
use App\Modules\People\Models\ActivationCode;
use App\Modules\People\Models\Person;
use App\Modules\People\Services\ActivationService;
use App\Platform\Audit\AuditLog;
use App\Platform\Authorization\PermissionResolver;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Facades\DB;

// People (M4): crew and staff, sensitive fields, and crew-app activation codes.

it('adds a driver with a checked mobile and licence', function () {
    $company = makeCompany();
    signInTo($company);

    $this->post(route('people.store'), ['name' => 'Ravi Kumar', 'type' => 'driver', 'mobile' => '12345', 'pay_basis' => 'monthly'])->assertSessionHasErrors('mobile');
    $this->post(route('people.store'), ['name' => 'Ravi Kumar', 'type' => 'driver', 'mobile' => '98470 11111', 'pay_basis' => 'per_trip', 'per_trip_rate' => 2500,
        'licence_no' => 'kl07 20150012345', 'licence_expiry' => '2026-11-10', 'date_of_birth' => '1988-02-01'])->assertSessionHasNoErrors();
    $this->post(route('people.store'), ['name' => 'Someone Else', 'type' => 'helper', 'mobile' => '9847011111', 'pay_basis' => 'monthly'])->assertSessionHasErrors('mobile');
    $this->post(route('people.store'), ['name' => 'Too Young', 'type' => 'helper', 'pay_basis' => 'monthly', 'date_of_birth' => '2015-01-01'])->assertSessionHasErrors('date_of_birth');

    app(CompanyContext::class)->set($company);
    $ravi = Person::first();
    expect($ravi->mobile)->toBe('+919847011111')->and($ravi->licence_no)->toBe('KL0720150012345')->and($ravi->licenceState())->toBe('expiring');

    $this->get(route('people.index'))->assertOk()->assertSee('Ravi Kumar')->assertSee('Expires in 16 days');
});

it('keeps ID numbers and bank details for people allowed to see them', function () {
    $company = makeCompany();
    $person  = makePerson([], $company);

    // A manager may change the person but not the ID or bank fields.
    signInTo($company, 'manager');
    $this->put(route('people.update', $person), ['name' => 'Ravi Kumar', 'type' => 'driver', 'mobile' => '+919847011111', 'pay_basis' => 'monthly',
        'id_type' => 'aadhaar', 'id_number' => '234567891234'])->assertSessionHasNoErrors();
    expect($person->fresh()->id_number)->toBeNull();
    $this->post(route('people.reveal-id', $person))->assertForbidden();

    signInTo($company);
    $this->put(route('people.update', $person), ['name' => 'Ravi Kumar', 'type' => 'driver', 'mobile' => '+919847011111', 'pay_basis' => 'monthly', 'id_type' => 'aadhaar', 'id_number' => '1234'])->assertSessionHasErrors('id_number');
    $this->put(route('people.update', $person), ['name' => 'Ravi Kumar', 'type' => 'driver', 'mobile' => '+919847011111', 'pay_basis' => 'monthly',
        'id_type' => 'aadhaar', 'id_number' => '2345 6789 1234', 'bank_account_number' => '50100123454321', 'bank_ifsc' => 'HDFC0001234'])->assertSessionHasNoErrors();

    $raw = DB::table('people')->where('id', $person->id)->first();
    expect($raw->id_number)->not->toContain('234567891234')->and($raw->id_last4)->toBe('1234')->and($raw->bank_last4)->toBe('4321');

    // Saving again with the boxes empty keeps what is stored.
    $this->put(route('people.update', $person), ['name' => 'Ravi K', 'type' => 'driver', 'mobile' => '+919847011111', 'pay_basis' => 'monthly', 'id_type' => 'aadhaar', 'id_number' => '', 'bank_account_number' => ''])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($company);
    expect($person->fresh()->id_number)->toBe('234567891234')->and($person->fresh()->bank_account_number)->toBe('50100123454321');

    $this->get(route('people.show', $person))->assertSee('XXXX XXXX 1234')->assertDontSee('234567891234');
    $this->post(route('people.reveal-id', $person))->assertOk()->assertSee('234567891234');
    app(CompanyContext::class)->set($company);
    expect(AuditLog::where('action', 'person.id_viewed')->value('severity'))->toBe('sensitive')
        ->and(json_encode(AuditLog::pluck('change_set')))->not->toContain('234567891234')->not->toContain('50100123454321');
});

it('makes an activation code that signs the crew app in once', function () {
    $company = makeCompany();
    signInTo($company);
    $person = makePerson([], $company);

    $this->post(route('people.activate', $person))->assertRedirect(route('people.show', $person));
    $code = session('activation_code');
    expect($code)->toMatch('/^\d{8}$/');
    $this->get(route('people.show', $person))->assertSee(substr($code, 0, 4) . ' ' . substr($code, 4));

    app(CompanyContext::class)->set($company);
    $person->refresh();
    $member = CompanyUser::find($person->company_user_id);
    expect($member->status)->toBe('active')
        ->and(Account::find($member->account_id)->mobile)->toBe('+919847011111')
        ->and(app(PermissionResolver::class)->has($member, 'event.record'))->toBeTrue()
        ->and(app(PermissionResolver::class)->has($member, 'dashboard.view'))->toBeFalse()
        ->and(DB::table('activation_codes')->value('code_hash'))->not->toBe($code);

    $service = app(ActivationService::class);
    expect($service->redeem('+919847011111', '00000000'))->toBeNull()
        ->and($service->redeem('+919847011111', $code, 'Pixel 7')?->id)->toBe($person->id)
        ->and($service->redeem('+919847011111', $code))->toBeNull();
});

it('replaces an older code and expires codes after 72 hours', function () {
    $company = makeCompany();
    signInTo($company);
    $person = makePerson([], $company);
    app(CompanyContext::class)->set($company);
    $service = app(ActivationService::class);

    $old = $service->issue($person);
    $new = $service->issue($person);
    expect($service->redeem($person->mobile, $old))->toBeNull();

    $this->travel(73)->hours();
    expect($service->redeem($person->mobile, $new))->toBeNull();
});

it('joins a driver who already works for another company to the same login', function () {
    $first  = makeCompany();
    $second = makeCompany(['name' => 'Second Transport']);
    $a = makePerson(['name' => 'Ravi Kumar'], $first);
    $b = makePerson(['name' => 'Ravi K.'], $second);

    app(CompanyContext::class)->runAs($first, fn () => app(ActivationService::class)->issue($a));
    app(CompanyContext::class)->runAs($second, fn () => app(ActivationService::class)->issue($b));

    expect(Account::where('mobile', '+919847011111')->count())->toBe(1)
        ->and(CompanyUser::where('account_id', Account::where('mobile', '+919847011111')->value('id'))->count())->toBe(2);
});

it('refuses a code without a mobile, or for someone who left', function () {
    $company = makeCompany();
    signInTo($company);
    $noMobile = makePerson(['name' => 'No Phone', 'mobile' => null], $company);
    $left     = makePerson(['name' => 'Gone', 'mobile' => '+919847022222', 'status' => 'left'], $company);

    $this->post(route('people.activate', $noMobile))->assertSessionHasErrors('mobile');
    $this->post(route('people.activate', $left))->assertSessionHasErrors('status');
});

it('ends the old crew-app login when the mobile number changes', function () {
    $company = makeCompany();
    signInTo($company);
    $person = makePerson([], $company);
    app(CompanyContext::class)->runAs($company, fn () => app(ActivationService::class)->issue($person));
    $member = CompanyUser::find($person->fresh()->company_user_id);

    $this->put(route('people.update', $person), ['name' => 'Ravi Kumar', 'type' => 'driver', 'mobile' => '9847099999', 'pay_basis' => 'monthly'])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($company);
    expect($person->fresh()->company_user_id)->toBeNull()
        ->and($member->fresh()->status)->toBe('left')
        ->and(AuditLog::where('action', 'person.app_login_ended')->exists())->toBeTrue();
});
