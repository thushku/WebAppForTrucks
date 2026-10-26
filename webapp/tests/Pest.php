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

/*
| The phone API's helpers (PhoneApiTest, ApprovalTest): a company with a trip
| sent to its crew, phones signed in with activation codes, entries and
| commands pushed the way the crew app sends them.
*/

/** A company with a trip sent to its crew, and an activation code for each of them. */
function phoneWorld(array $settings = [], bool $coDriver = true): array
{
    $company = makeCompany();

    return app(CompanyContext::class)->runAs($company, function () use ($company, $settings, $coDriver) {
        if ($settings) {
            app(\App\Modules\Settings\Services\CompanySettings::class)->save($settings);
        }
        $cok    = \App\Modules\Settings\Models\Place::create(['name' => 'Kochi', 'short_code' => 'COK']);
        $bom    = \App\Modules\Settings\Models\Place::create(['name' => 'Mumbai', 'short_code' => 'BOM']);
        $acc    = makeParty(['name' => 'ACC Limited', 'short_code' => 'ACC'], ['client', 'consignor']);
        $truck  = makeTruck(['odometer_km' => 100000, 'fuel_tank_l' => 400, 'mileage_loaded_kmpl' => 3.5, 'mileage_empty_kmpl' => 4.5]);
        $driver = makePerson(['name' => 'Ravi Kumar', 'licence_no' => 'KL0720150012345', 'licence_expiry' => '2030-01-01']);
        $co     = $coDriver ? makePerson(['name' => 'Suresh Nair', 'mobile' => '+919847033333', 'licence_no' => 'KL0720160054321', 'licence_expiry' => '2030-01-01']) : null;
        $helper = makePerson(['name' => 'Binu Joseph', 'type' => 'helper', 'mobile' => '+919847022222']);

        $crew = [['person_id' => $driver->id, 'role' => 'driver'], ['person_id' => $helper->id, 'role' => 'helper']];
        if ($co) {
            $crew[] = ['person_id' => $co->id, 'role' => 'co_driver'];
        }

        $trips = app(\App\Modules\Trips\Services\TripService::class);
        $trip  = $trips->create(
            ['trip_date' => '2026-10-25', 'truck_id' => $truck->id, 'from_place_id' => $cok->id, 'to_place_id' => $bom->id, 'planned_km' => 1380,
                'instructions' => 'Call the office at Pune', 'office_note' => 'Client pays late'],
            $crew,
            ['billing_party_id' => $acc->id, 'consignor_party_id' => $acc->id, 'material' => 'cement', 'actual_weight_mt' => 18.5,
                'freight_basis' => 'tonne', 'rate' => 3400, 'freight' => 62900, 'payment_type' => 'to_be_billed'],
        );
        $trips->assign($trip->fresh());

        $activation = app(\App\Modules\People\Services\ActivationService::class);

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
    \Illuminate\Support\Facades\Auth::forgetGuards();
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
    return array_replace_recursive(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'trip' => $w['trip']->uuid, 'type' => $type,
        'event_at' => now()->toIso8601String(), 'lat' => 9.9816, 'lng' => 76.2999], $fields);
}

function phoneCommand(array $w, string $name, array $fields = []): array
{
    return array_replace_recursive(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'name' => $name, 'trip' => $w['trip']->uuid], $fields);
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

// ---- Quotations and LR books (QuotationTest, LrBookTest) -------------------

/** A company, signed in as its owner, with Kochi, Mumbai, Pune, ACC Limited, a truck and its crew. */
function qtWorld(): array
{
    $company = makeCompany();
    $owner = signInTo($company);

    return app(CompanyContext::class)->runAs($company, fn () => [
        'company' => $company, 'owner' => $owner,
        'cok' => \App\Modules\Settings\Models\Place::create(['name' => 'Kochi', 'short_code' => 'COK']),
        'bom' => \App\Modules\Settings\Models\Place::create(['name' => 'Mumbai', 'short_code' => 'BOM']),
        'pnq' => \App\Modules\Settings\Models\Place::create(['name' => 'Pune', 'short_code' => 'PNQ']),
        'acc' => makeParty(['name' => 'ACC Limited', 'short_code' => 'ACC', 'email' => 'logistics@acc.test', 'mobile' => '+919820000101', 'address_line1' => 'Cement House', 'city' => 'Mumbai'], ['client', 'consignor']),
        'truck' => makeTruck(['payload_mt' => 25, 'odometer_km' => 100000]),
        'driver' => makePerson(['name' => 'Ravi Kumar', 'licence_no' => 'KL0720150012345', 'licence_expiry' => '2030-01-01']),
    ]);
}

// ---- Invoicing (InvoiceTest, TemplateTest) ---------------------------------------

function invGstin(string $state, string $pan): string
{
    return $state . $pan . '1Z' . \App\Modules\Parties\Services\TaxIds::gstinCheckCharacter($state . $pan . '1Z');
}

/**
 * A Kerala company with its GSTIN (and GTA option), a client registered in
 * $clientState, and $loads delivered LRs to bill, each on its own trip.
 */
function invWorld(?string $gtaOption = 'fcm_18', ?string $clientState = '32', int $loads = 1, array $client = []): array
{
    $company = makeCompany();
    $owner   = signInTo($company);

    return app(CompanyContext::class)->runAs($company, function () use ($company, $owner, $gtaOption, $clientState, $loads, $client) {
        $reg = \App\Modules\Tax\Models\TaxRegistration::create(['country' => 'IN', 'regime' => 'in_gst', 'tax_id' => invGstin('32', 'AABCS1234F'), 'state_code' => '32', 'is_default' => true, 'legal_name' => 'Sharma Roadlines Pvt Ltd']);
        if ($gtaOption) {
            \App\Modules\Tax\Models\TaxRegistrationYear::create(['tax_registration_id' => $reg->id, 'fy_key' => '2026-27', 'gta_option' => $gtaOption]);
        }
        $acc = makeParty($client + ['name' => 'ACC Limited', 'short_code' => 'ACC', 'state_code' => $clientState, 'credit_days' => 30], ['client', 'consignor']);
        if ($clientState) {
            \App\Modules\Parties\Models\PartyTaxId::create(['party_id' => $acc->id, 'country' => 'IN', 'tax_id' => invGstin($clientState, 'AAACA1234B'), 'state_code' => $clientState, 'is_default' => true]);
        }
        $cok = \App\Modules\Settings\Models\Place::create(['name' => 'Kochi', 'short_code' => 'COK', 'state_code' => '32']);
        $bom = \App\Modules\Settings\Models\Place::create(['name' => 'Mumbai', 'short_code' => 'BOM', 'state_code' => '27']);
        $trips = app(\App\Modules\Trips\Services\TripService::class);

        $made = [];
        for ($n = 1; $n <= $loads; $n++) {
            $truck  = makeTruck(['reg_no' => "KL-07-AB-12{$n}0", 'odometer_km' => 100000]);
            $driver = makePerson(['name' => "Driver {$n}", 'mobile' => '+91984701111' . $n, 'licence_no' => "KL07201500{$n}2345", 'licence_expiry' => '2030-01-01']);
            $trip = $trips->create(['trip_date' => '2026-10-25', 'truck_id' => $truck->id, 'from_place_id' => $cok->id, 'to_place_id' => $bom->id, 'planned_km' => 1380],
                [['person_id' => $driver->id, 'role' => 'driver']],
                ['billing_party_id' => $acc->id, 'consignor_party_id' => $acc->id, 'material' => 'cement', 'actual_weight_mt' => 18.5,
                    'freight_basis' => 'tonne', 'rate' => 3400, 'freight' => 62900, 'loading_charge' => 1100, 'payment_type' => 'to_be_billed']);
            $trips->assign($trip->fresh());
            $trips->start($trip->fresh(), 100100);
            $trips->end($trip->fresh(), 101500);
            $made[] = \App\Modules\Trips\Models\Consignment::where('trip_id', $trip->id)->first();
        }

        return ['company' => $company, 'owner' => $owner, 'registration' => $reg, 'client' => $acc->fresh('taxIds'), 'loads' => $made];
    });
}

/** Make the draft from LRs through the screens, and give it back. */
function invDraft(array $w, ?array $loads = null): \App\Modules\Invoicing\Models\Invoice
{
    test()->post(route('invoices.store'), ['party_id' => $w['client']->id, 'loads' => $loads ?? collect($w['loads'])->pluck('id')->all()])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);

    return \App\Modules\Invoicing\Models\Invoice::latest('id')->first();
}
