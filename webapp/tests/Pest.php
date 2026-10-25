<?php

use App\Models\Account;
use App\Models\CompanyUser;
use App\Platform\Authorization\RoleProvisioner;
use App\Platform\Tenancy\Company;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(Tests\TestCase::class)
    ->beforeEach(function () {
        // Views carry @vite; the tests do not build assets.
        $this->withoutVite();
        // The clock is frozen on the day this suite was written, so "this
        // month", trial days and expiry dates read the same on any later day.
        $this->travelTo('2026-10-25 09:00:00');
        resetDatabase();
    })
    ->afterEach(function () {
        app(CompanyContext::class)->forget();
        $this->travelBack();
    })
    ->in('Feature');

/*
| Helpers shared by every feature test live here and nowhere else: Pest
| loads every file into one process, so a helper declared twice is fatal.
*/

/**
 * Empty every table between tests. Tables are read from the database
 * itself, so a table added by a later migration is covered automatically.
 */
function resetDatabase(): void
{
    $tables = collect(DB::select('SHOW TABLES'))
        ->map(fn ($row) => array_values((array) $row)[0])
        ->reject(fn ($t) => $t === 'migrations');

    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    try {
        foreach ($tables as $table) {
            DB::table($table)->delete();
        }
    } finally {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}

/** A verified, active login. */
function makeAccount(array $attributes = [], string $password = 'Secret-pass-123'): Account
{
    $verified = array_key_exists('email_verified_at', $attributes) ? $attributes['email_verified_at'] : now();
    unset($attributes['email_verified_at']);

    $account = new Account(array_merge([
        'name'  => 'Ravi Kumar',
        'email' => 'ravi' . uniqid() . '@example.com',
    ], $attributes));
    $account->password_hash     = Hash::make($password);
    $account->email_verified_at = $verified;
    $account->save();

    // Read back every column, as a signed-in request would see it.
    return $account->refresh();
}

/** A company with its standard roles and its country's starting set-up. */
function makeCompany(array $attributes = []): Company
{
    $company = Company::create(array_merge([
        'name'          => 'Sharma Roadlines',
        'country'       => 'IN',
        'currency'      => 'INR',
        'timezone'      => 'Asia/Kolkata',
        'status'        => 'trial',
        'trial_ends_at' => now()->addDays(14),
    ], $attributes));

    app(RoleProvisioner::class)->provision($company);
    app(\App\Modules\Settings\Services\CountryDefaults::class)->apply($company);

    return $company;
}

/** Put a person into a company with a role. */
function joinCompany(Account $account, Company $company, string $role = 'owner'): CompanyUser
{
    $member = CompanyUser::create([
        'company_id' => $company->id,
        'account_id' => $account->id,
        'status'     => 'active',
        'joined_at'  => now(),
    ]);

    app(RoleProvisioner::class)->grant($member, $role);

    return $member;
}

/** Signed in, inside a company. */
function signInTo(Company $company, string $role = 'owner', array $attributes = []): Account
{
    $account = makeAccount($attributes);
    joinCompany($account, $company, $role);

    test()->actingAs($account, 'web')->withSession([\App\Http\Middleware\ResolveCompany::SESSION_KEY => $company->id]);

    return $account;
}

/** A party with roles, in the company in context (or the one given). */
function makeParty(array $attributes = [], array $roles = ['client'], ?Company $company = null): \App\Modules\Parties\Models\Party
{
    $make = function () use ($attributes, $roles) {
        $party = \App\Modules\Parties\Models\Party::create(array_merge(['name' => 'ACC Limited'], $attributes));
        foreach ($roles as $role) {
            \App\Modules\Parties\Models\PartyRole::create(['party_id' => $party->id, 'role' => $role]);
        }

        return $party;
    };

    return $company ? app(CompanyContext::class)->runAs($company, $make) : $make();
}

/** A truck in the company in context (or the one given). */
function makeTruck(array $attributes = [], ?Company $company = null): \App\Modules\Fleet\Models\Truck
{
    $make = fn () => \App\Modules\Fleet\Models\Truck::create(array_merge(['reg_no' => 'KL-07-AB-1234', 'rc_owner_name' => 'Sharma Roadlines', 'truck_type' => 'w12'], $attributes));

    return $company ? app(CompanyContext::class)->runAs($company, $make) : $make();
}

/** A driver (or other person) in the company in context (or the one given). */
function makePerson(array $attributes = [], ?Company $company = null): \App\Modules\People\Models\Person
{
    $make = fn () => \App\Modules\People\Models\Person::create(array_merge(['name' => 'Ravi Kumar', 'type' => 'driver', 'mobile' => '+919847011111'], $attributes));

    return $company ? app(CompanyContext::class)->runAs($company, $make) : $make();
}
