<?php

use App\Models\Account;
use App\Models\CompanyUser;
use App\Platform\Auth\PasswordTokens;
use App\Platform\Authorization\Models\Role;
use App\Platform\Authorization\Models\RoleAssignment;
use App\Platform\Tenancy\CompanyContext;
use App\Platform\Team\TeamService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

// The team: invitations, roles, suspending and removing, and the rules that
// stop anyone handing out more power than they hold.

function memberOf(Account $account, $company): CompanyUser
{
    return CompanyUser::where('account_id', $account->id)->where('company_id', $company->id)->firstOrFail();
}

function teamRoles(CompanyUser $member): array
{
    return RoleAssignment::withoutGlobalScopes()->where('company_user_id', $member->id)->with(['role' => fn ($q) => $q->withoutGlobalScopes()])->get()->pluck('role.code')->sort()->values()->all();
}

it('invites a new person, who sets a password and lands in the company', function () {
    Mail::fake();
    $company = makeCompany();
    signInTo($company);

    $this->post(route('settings.team.invite'), ['name' => 'Anita Menon', 'email' => 'Anita@Example.com', 'roles' => ['accountant'], 'title' => 'Accounts'])
        ->assertSessionHasNoErrors();

    $account = Account::where('email', 'anita@example.com')->first();
    $member  = memberOf($account, $company);
    expect($account->password_hash)->toBeNull()->and($member->status)->toBe('invited')->and(teamRoles($member))->toBe(['accountant']);

    // An invited person cannot open the company yet.
    Auth::logout();
    $token = app(PasswordTokens::class)->issue('company_user', $member->id, 'invite');

    $this->get(route('invite.show', $token))->assertOk()->assertSee('Choose a password');
    $this->post(route('invite.accept', $token), ['name' => 'Anita Menon', 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
    $this->post(route('invite.accept', $token), ['name' => 'Anita Menon', 'password' => 'Ledger-pass-2026', 'password_confirmation' => 'Ledger-pass-2026'])
        ->assertRedirect(route('dashboard'));

    expect(Auth::id())->toBe($account->id)
        ->and($member->fresh()->status)->toBe('active')
        ->and($account->fresh()->email_verified_at)->not->toBeNull();

    // The link works once.
    Auth::logout();
    $this->get(route('invite.show', $token))->assertRedirect(route('login'));
});

it('asks someone who already has a login to sign in, and never joins a different login', function () {
    Mail::fake();
    $company  = makeCompany();
    $existing = makeAccount(['email' => 'ravi.driver@example.com']);
    $owner    = signInTo($company);

    $this->post(route('settings.team.invite'), ['name' => 'Ravi', 'email' => 'ravi.driver@example.com', 'roles' => ['viewer']])->assertSessionHasNoErrors();
    $member = memberOf($existing, $company);
    $token  = app(PasswordTokens::class)->issue('company_user', $member->id, 'invite');

    // Signed in as the owner, not as Ravi.
    $this->get(route('invite.show', $token))->assertOk()->assertSee('another login');
    $this->post(route('invite.accept', $token))->assertRedirect(route('invite.show', $token));
    expect($member->fresh()->status)->toBe('invited');

    Auth::logout();
    $this->get(route('invite.show', $token))->assertOk()->assertSee('Sign in first');

    $this->actingAs($existing, 'web');
    $this->post(route('invite.accept', $token))->assertRedirect(route('dashboard'));
    expect($member->fresh()->status)->toBe('active');
});

it('does not invite someone already in the team', function () {
    Mail::fake();
    $company = makeCompany();
    $owner   = signInTo($company);

    $this->post(route('settings.team.invite'), ['name' => 'Me', 'email' => $owner->email, 'roles' => ['viewer']])->assertSessionHasErrors('email');
});

it('stops a manager from giving the Owner role or changing an owner', function () {
    Mail::fake();
    $company = makeCompany();
    $owner   = makeAccount();
    joinCompany($owner, $company, 'owner');
    signInTo($company, 'manager');

    $this->post(route('settings.team.invite'), ['name' => 'Boss', 'email' => 'boss@example.com', 'roles' => ['owner']])->assertForbidden();
});

it('lets the owner change roles but never removes the last active owner', function () {
    $company = makeCompany();
    $owner   = signInTo($company);
    $me      = memberOf($owner, $company);
    $driver  = makeAccount();
    $crew    = joinCompany($driver, $company, 'driver');

    $this->put(route('settings.team.update', $crew), ['roles' => ['driver', 'viewer'], 'title' => 'Senior driver'])->assertSessionHasNoErrors();
    expect(teamRoles($crew))->toBe(['driver', 'viewer'])->and($crew->fresh()->title)->toBe('Senior driver');

    $this->put(route('settings.team.update', $me), ['roles' => ['manager']])->assertSessionHasErrors('roles');
    expect(teamRoles($me))->toBe(['owner']);

    $this->delete(route('settings.team.remove', $me))->assertSessionHasErrors('member');
});

it('limits role changes to roles at or below your own level', function () {
    $company = makeCompany();
    $managerAccount = makeAccount();
    $manager = joinCompany($managerAccount, $company, 'manager');
    $ownerAccount = makeAccount();
    $owner = joinCompany($ownerAccount, $company, 'owner');
    $viewer = joinCompany(makeAccount(), $company, 'viewer');

    app(CompanyContext::class)->set($company, $manager);
    $team = app(TeamService::class);

    expect($team->grantable($manager)->pluck('code')->all())->not->toContain('owner')
        ->and($team->canManage($manager, $owner))->toBeFalse()
        ->and($team->canManage($manager, $viewer))->toBeTrue();

    expect(fn () => $team->setRoles($manager, $owner, ['viewer']))->toThrow(\Illuminate\Validation\ValidationException::class);
    $team->setRoles($manager, $viewer, ['workshop']);
    expect(teamRoles($viewer))->toBe(['workshop']);
});

it('suspends a member, who then cannot open the company, and lets them back', function () {
    $company = makeCompany();
    signInTo($company);
    $other  = makeAccount();
    $member = joinCompany($other, $company, 'viewer');

    $this->post(route('settings.team.suspend', $member))->assertSessionHasNoErrors();
    expect($member->fresh()->status)->toBe('suspended');

    $this->actingAs($other, 'web')->withSession([\App\Http\Middleware\ResolveCompany::SESSION_KEY => $company->id])
        ->get(route('dashboard'))->assertRedirect(route('company.choose'));

    signInTo($company);
    $this->post(route('settings.team.reactivate', $member));
    expect($member->fresh()->status)->toBe('active');
});

it('removes a member and their roles but keeps the row for history', function () {
    $company = makeCompany();
    signInTo($company);
    $member = joinCompany(makeAccount(['name' => 'Suresh Pillai']), $company, 'accountant');

    $this->delete(route('settings.team.remove', $member))->assertRedirect(route('settings.team'));

    expect($member->fresh()->status)->toBe('left')->and(teamRoles($member))->toBe([]);
    $this->get(route('settings.team'))->assertSee(__(':name has left the team. Their past work keeps their name.', ['name' => 'Suresh Pillai']));
    $this->get(route('settings.team'))->assertDontSee('Suresh Pillai');
    $this->get(route('settings.team', ['show' => 'left']))->assertSee($member->account->name);
});

it('unlocks a locked member', function () {
    $company = makeCompany();
    signInTo($company);
    $locked = makeAccount();
    $locked->forceFill(['locked_hard_at' => now(), 'lock_level' => 3, 'failed_attempts' => 15])->save();
    $member = joinCompany($locked, $company, 'viewer');

    $this->get(route('settings.security'))->assertSee($locked->name);
    $this->post(route('settings.team.unlock', $member))->assertSessionHasNoErrors();

    expect($locked->fresh()->isLocked())->toBeFalse();
});

it('never touches a member of another company', function () {
    $company = makeCompany();
    $other   = makeCompany(['name' => 'Other Transport']);
    $stranger = joinCompany(makeAccount(), $other, 'viewer');
    signInTo($company);

    $this->get(route('settings.team.edit', $stranger))->assertNotFound();
    $this->post(route('settings.team.suspend', $stranger))->assertNotFound();
    expect($stranger->fresh()->status)->toBe('active');
});

// ---- Roles --------------------------------------------------------------------

it('lets the owner change what a role may do, and records it', function () {
    $company = makeCompany();
    signInTo($company);
    app(CompanyContext::class)->set($company);
    $viewer = Role::where('code', 'viewer')->first();

    $this->put(route('settings.roles.update', $viewer), ['name' => 'Read only', 'permissions' => ['dashboard.view', 'trip.view']])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($company);
    expect($viewer->fresh()->name)->toBe('Read only')
        ->and($viewer->permissions()->pluck('permission_code')->sort()->values()->all())->toBe(['dashboard.view', 'trip.view'])
        ->and(\App\Platform\Audit\AuditLog::where('action', 'role.changed')->exists())->toBeTrue();

    $owner = Role::where('code', 'owner')->first();
    $this->put(route('settings.roles.update', $owner), ['name' => 'Boss', 'permissions' => []])->assertForbidden();
});

it('stops anyone from putting a permission they lack into a role', function () {
    $company = makeCompany();
    app(CompanyContext::class)->set($company);
    // A custom role that may define roles but cannot see bank details.
    $definer = Role::create(['code' => 'definer', 'name' => 'Definer', 'level' => 1, 'is_system' => false]);
    foreach (['role.define', 'role.grant', 'dashboard.view'] as $code) {
        $definer->permissions()->create(['permission_code' => $code, 'allow' => true]);
    }
    signInTo($company, 'definer');
    app(CompanyContext::class)->set($company);
    $manager = Role::where('code', 'manager')->first();

    $this->put(route('settings.roles.update', $manager), ['name' => 'Manager', 'permissions' => ['settings.bank']])->assertSessionHasErrors('permissions');
});

it('creates a role as a copy of another', function () {
    $company = makeCompany();
    signInTo($company);

    $this->post(route('settings.roles.store'), ['name' => 'Dispatcher', 'copy' => app(CompanyContext::class)->runAs($company, fn () => Role::where('code', 'manager')->value('id'))])
        ->assertRedirect();

    app(CompanyContext::class)->set($company);
    $role = Role::where('code', 'dispatcher')->first();
    expect($role->is_system)->toBeFalse()->and($role->permissions()->count())->toBeGreaterThan(10);
});

it('keeps roles away from a manager without role permissions to define', function () {
    $company = makeCompany();
    signInTo($company, 'manager');
    app(CompanyContext::class)->set($company);
    $viewer = Role::where('code', 'viewer')->first();

    $this->get(route('settings.roles'))->assertForbidden();
    $this->put(route('settings.roles.update', $viewer), ['name' => 'X', 'permissions' => []])->assertForbidden();
});
