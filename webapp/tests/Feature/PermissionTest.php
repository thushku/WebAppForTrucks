<?php

use App\Platform\Authorization\Models\Role;
use App\Platform\Authorization\PermissionResolver;
use App\Platform\Tenancy\CompanyContext;

// Deny by default; permissions are per company; explicit deny beats a grant.

it('lets the owner open the dashboard and keeps a driver out', function () {
    $company = makeCompany();

    signInTo($company, 'owner');
    $this->get(route('dashboard'))->assertOk();

    auth('web')->logout();
    signInTo($company, 'driver');
    $this->get(route('dashboard'))->assertForbidden();
});

it('gives the same person different powers in different companies', function () {
    $account = makeAccount();
    $a = makeCompany();
    $b = makeCompany();
    $inA = joinCompany($account, $a, 'owner');
    $inB = joinCompany($account, $b, 'driver');

    $resolver = app(PermissionResolver::class);
    expect($resolver->has($inA, 'settings.bank'))->toBeTrue()
        ->and($resolver->has($inB, 'settings.bank'))->toBeFalse()
        ->and($resolver->has($inB, 'event.record'))->toBeTrue();
});

it('lets an explicit deny beat a grant from another role', function () {
    $company = makeCompany();
    $account = makeAccount();
    $member  = joinCompany($account, $company, 'manager');
    app(\App\Platform\Authorization\RoleProvisioner::class)->grant($member, 'viewer');

    app(CompanyContext::class)->set($company);
    $viewer = Role::where('code', 'viewer')->first();
    $viewer->permissions()->where('permission_code', 'trip.view')->update(['allow' => false]);
    app(PermissionResolver::class)->forget();

    // Manager grants trip.view, but the viewer role denies it.
    expect(app(PermissionResolver::class)->has($member, 'trip.view'))->toBeFalse()
        ->and(app(PermissionResolver::class)->has($member, 'trip.create'))->toBeTrue();
});

it('keeps owner-only powers away from the standard non-owner roles', function () {
    foreach (['manager', 'accountant', 'viewer', 'driver', 'helper', 'workshop'] as $role) {
        $codes = \App\Platform\Authorization\PermissionCatalogue::defaultsFor($role);
        expect(array_intersect($codes, \App\Platform\Authorization\PermissionCatalogue::OWNER_ONLY_BY_DEFAULT))->toBe([]);
    }
});

it('ends access the moment a membership ends', function () {
    $company = makeCompany();
    $account = signInTo($company, 'owner');
    $this->get(route('dashboard'))->assertOk();

    $account->memberships()->update(['status' => 'left']);
    $this->get(route('dashboard'))->assertRedirect(route('company.choose'));
});
