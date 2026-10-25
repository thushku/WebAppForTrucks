<?php

use App\Platform\Billing\Coupon;
use App\Platform\Billing\Plan;
use App\Platform\Billing\Subscription;
use App\Platform\Console\Admin;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

// The SaaS side (M13): plans and coupons set in the console, a company put on
// a plan, the nightly sweep turning an ended trial read-only (web and phone),
// the plan's limits, suspension, storage clean-up, the database export and
// the announcement.

function billAdmin(): Admin
{
    $admin = new Admin(['name' => 'Ops', 'email' => 'ops' . uniqid() . '@example.com']);
    $admin->forceFill(['password_hash' => Hash::make('Console-pass-12345'), 'status' => 'active'])->save();
    test()->actingAs($admin, 'platform');

    return $admin;
}

function billPlan(array $attributes = []): Plan
{
    return Plan::create(array_merge(['code' => 'fleet-' . uniqid(), 'name' => 'Fleet', 'country' => 'IN', 'currency' => 'INR', 'pricing' => 'per_truck',
        'price_monthly' => 300, 'price_annual' => 3000, 'max_trucks' => null, 'max_users' => null, 'is_public' => true, 'active' => true], $attributes));
}

it('creates plans and coupons in the console and puts a company on a plan, the coupon taken off', function () {
    $company = makeCompany();
    makeTruck(['reg_no' => 'KL-07-AB-0001'], $company);
    makeTruck(['reg_no' => 'KL-07-AB-0002'], $company);
    billAdmin();

    $this->post(route('platform.plans.store'), ['_plan' => 'new', 'code' => 'fleet', 'name' => 'Fleet', 'country' => 'IN', 'currency' => 'INR', 'pricing' => 'per_truck',
        'price_monthly' => '300', 'price_annual' => '3000', 'max_trucks' => '25', 'modules' => [], 'active' => '1', 'is_public' => '1'])->assertSessionHasNoErrors();
    $this->post(route('platform.plans.store'), ['code' => 'nothing', 'name' => 'Nothing', 'currency' => 'INR', 'pricing' => 'flat', 'active' => '1'])->assertSessionHasErrors('price_monthly');
    $plan = Plan::where('code', 'fleet')->firstOrFail();
    expect($plan->max_trucks)->toBe(25)->and($plan->modules)->toBeNull()->and($plan->price('annual', 2))->toBe(6000.0);

    $this->post(route('platform.coupons.store'), ['code' => 'diwali20', 'kind' => 'percent', 'value' => '20', 'max_uses' => '1'])->assertSessionHasNoErrors();
    $this->post(route('platform.coupons.store'), ['code' => 'TOO-MUCH', 'kind' => 'percent', 'value' => '120'])->assertSessionHasErrors('value');
    expect(Coupon::where('code', 'DIWALI20')->exists())->toBeTrue();

    $this->get(route('platform.plans'))->assertOk()->assertSeeText('Fleet')->assertSeeText('INR 300.00 a month')->assertSeeText('DIWALI20');
    $this->get(route('platform.companies'))->assertOk()->assertSeeText('Sharma Roadlines')->assertSeeText('On trial');

    $this->post(route('platform.companies.subscribe', $company), ['plan_id' => $plan->id, 'period' => 'annual', 'starts_on' => '2026-10-25', 'trucks' => 2, 'coupon' => 'diwali20', 'reference' => 'UTR 4455'])
        ->assertSessionHasNoErrors();
    $subscription = Subscription::where('company_id', $company->id)->firstOrFail();
    expect((float) $subscription->amount)->toBe(4800.0)->and((float) $subscription->discount)->toBe(1200.0)
        ->and($subscription->ends_on->toDateString())->toBe('2027-10-24')->and($subscription->trucks)->toBe(2)
        ->and($company->fresh()->status)->toBe('active')->and($company->fresh()->plan_id)->toBe($plan->id)
        ->and(Coupon::where('code', 'DIWALI20')->value('used_count'))->toBe(1);

    // The coupon is used up; a monthly period on a lifetime plan is refused.
    $this->post(route('platform.companies.subscribe', $company), ['plan_id' => $plan->id, 'period' => 'monthly', 'starts_on' => '2026-11-01', 'coupon' => 'DIWALI20'])
        ->assertSessionHasErrors(['coupon' => 'This coupon has been used up.']);
    $life = billPlan(['code' => 'life', 'name' => 'Lifetime', 'pricing' => 'lifetime', 'price_lifetime' => 50000, 'price_monthly' => null, 'price_annual' => null]);
    $this->post(route('platform.companies.subscribe', $company), ['plan_id' => $life->id, 'period' => 'monthly', 'starts_on' => '2026-11-01'])->assertSessionHasErrors('period');

    // Moving to another plan ends the running one the day before.
    $this->post(route('platform.companies.subscribe', $company), ['plan_id' => $life->id, 'period' => 'lifetime', 'starts_on' => '2026-12-01'])->assertSessionHasNoErrors();
    expect($subscription->fresh()->status)->toBe('ended')->and($subscription->fresh()->ends_on->toDateString())->toBe('2026-11-30')
        ->and(Subscription::where('company_id', $company->id)->where('status', 'active')->first()->ends_on)->toBeNull();

    $this->get(route('platform.companies.show', $company))->assertOk()->assertSeeText('Lifetime')->assertSeeText('UTR 4455')->assertSeeText('INR 4,800.00')
        ->assertSeeText('Put Sharma Roadlines on Fleet (annual) from 2026-10-25');
});

it('turns an ended trial read-only every night, on the web and on the phones, until a plan is recorded', function () {
    $w = phoneWorld();
    $phones = phoneCrew($w);
    $owner = makeAccount(['name' => 'Anil Sharma']);
    joinCompany($owner, $w['company'], 'owner');

    $this->travel(15)->days();
    $this->artisan('fleet:subscriptions')->expectsOutputToContain('1 company(ies) turned read-only.')->assertSuccessful();
    expect($w['company']->fresh()->status)->toBe('lapsed')->and($w['company']->fresh()->isReadOnly())->toBeTrue();

    // The web: everything reads, nothing writes; the billing page is open.
    phoneReset();
    $this->actingAs($owner, 'web')->withSession([\App\Http\Middleware\ResolveCompany::SESSION_KEY => $w['company']->id]);
    $this->get(route('dashboard'))->assertOk()->assertSeeText('This company is read-only because its plan has ended.')->assertSee(route('billing.index'), false);
    $this->from(route('trucks.index'))->post(route('trucks.store'), ['reg_no' => 'KL-07-ZZ-9999', 'rc_owner_name' => 'X', 'fuel_type' => 'diesel', 'ownership' => 'own'])
        ->assertRedirect(route('trucks.index'))->assertSessionHas('error');
    $this->get(route('billing.index'))->assertOk()->assertSeeText('Ended — the company is read-only.');

    // The phone: reads work, entries wait on the phone (423 read_only).
    phoneAs($phones['driver'], $w['company']);
    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('company.read_only', true)->assertJsonPath('company.status', 'lapsed');
    $this->postJson('/api/v1/crew/events', ['events' => [phoneEvent($w, 'toll', ['amount' => 100, 'payment_mode' => 'cash'])]])
        ->assertStatus(423)->assertJsonPath('code', 'read_only');

    // The console records a payment: writable again, on the phones too.
    phoneReset();
    billAdmin();
    $plan = billPlan();
    $this->post(route('platform.companies.subscribe', $w['company']), ['plan_id' => $plan->id, 'period' => 'monthly', 'starts_on' => now()->toDateString(), 'trucks' => 1])->assertSessionHasNoErrors();
    expect($w['company']->fresh()->isReadOnly())->toBeFalse();
    phoneAs($phones['driver'], $w['company']);
    $this->getJson('/api/v1/me')->assertJsonPath('company.read_only', false);

    // A month later the subscription has ended: read-only again.
    $this->travel(32)->days();
    $this->artisan('fleet:subscriptions')->assertSuccessful();
    expect($w['company']->fresh()->status)->toBe('lapsed')
        ->and(Subscription::where('company_id', $w['company']->id)->value('status'))->toBe('ended');
});

it('stops adding trucks and team members beyond the plan, never taking away what is there', function () {
    $company = makeCompany();
    $plan = billPlan(['name' => 'Starter', 'max_trucks' => 1, 'max_users' => 2]);
    $company->forceFill(['plan_id' => $plan->id, 'status' => 'active'])->save();
    signInTo($company);

    $this->post(route('trucks.store'), ['reg_no' => 'KL-07-AB-0001', 'rc_owner_name' => 'Sharma Roadlines', 'fuel_type' => 'diesel', 'ownership' => 'own'])->assertSessionHasNoErrors();
    $this->post(route('trucks.store'), ['reg_no' => 'KL-07-AB-0002', 'rc_owner_name' => 'Sharma Roadlines', 'fuel_type' => 'diesel', 'ownership' => 'own'])
        ->assertSessionHasErrors(['reg_no' => 'The Starter plan has room for 1 trucks. Move to a bigger plan to add more.']);

    $this->post(route('settings.team.invite'), ['name' => 'Anita Menon', 'email' => 'anita@example.com', 'roles' => ['accountant']])->assertSessionHasNoErrors();
    $this->post(route('settings.team.invite'), ['name' => 'Joseph', 'email' => 'joseph@example.com', 'roles' => ['viewer']])->assertSessionHasErrors('email');

    // The billing page shows the use against the plan and the plans to move to, priced for the trucks.
    billPlan(['name' => 'Fleet', 'max_trucks' => 50, 'price_monthly' => 250, 'price_annual' => 2500]);
    app(\App\Platform\Settings\PlatformSettings::class)->set('support_whatsapp', '+91 98470 12345');
    $this->get(route('billing.index'))->assertOk()->assertSeeText('Starter')->assertSeeText('of 1')->assertSeeText('Full — a bigger plan has room for more.')
        ->assertSeeText('Fleet')->assertSeeText('₹250.00')->assertSee('https://wa.me/919847012345?text=', false);
    $this->get(route('settings.company'))->assertOk()->assertSee(route('billing.index'), false);

    // Drivers do not see billing.
    signInTo($company, 'driver');
    $this->get(route('billing.index'))->assertForbidden();
});

it('extends a trial, suspends and resumes a company, each with its reason in the log', function () {
    $company = makeCompany();
    $owner = makeAccount(['email' => 'owner@example.com']);
    joinCompany($owner, $company, 'owner');
    billAdmin();

    $this->post(route('platform.companies.trial', $company), ['days' => 10, 'reason' => 'Asked at the trade fair'])->assertSessionHasNoErrors();
    expect($company->fresh()->trial_ends_at->toDateString())->toBe('2026-11-18');

    $this->post(route('platform.companies.suspend', $company), ['reason' => 'x'])->assertSessionHasErrors('reason');
    $this->post(route('platform.companies.suspend', $company), ['reason' => 'Chargeback on the card'])->assertSessionHasNoErrors();
    expect($company->fresh()->status)->toBe('suspended')->and($company->fresh()->isUsable())->toBeFalse();
    $this->get(route('platform.companies.show', $company))->assertOk()->assertSeeText('Suspended: Chargeback on the card')->assertSeeText('Resume the company');

    $this->post(route('platform.companies.resume', $company))->assertSessionHasNoErrors();
    expect($company->fresh()->status)->toBe('trial');
    expect(DB::table('audit_log')->where('company_id', $company->id)->where('action', 'like', 'billing.%')->pluck('action')->all())
        ->toContain('billing.trial_extended', 'billing.suspended', 'billing.resumed');
    expect(DB::table('audit_log')->where('action', 'billing.trial_extended')->value('reason'))->toBe('Asked at the trade fair');

    // Company logins never reach the console.
    phoneReset();
    signInTo($company);
    $this->get(route('platform.companies'))->assertRedirect(route('platform.login'));
});

it('shows storage per company, removes files no row points to, exports the database and announces to everyone', function () {
    Storage::fake('local');
    $w = phoneWorld();
    $disk = Storage::disk('local');
    $disk->put("companies/{$w['company']->uuid}/documents/2026/10/lost.pdf", '%PDF-1.4 lost');
    $disk->put("companies/{$w['company']->uuid}/documents/2026/10/fresh.pdf", '%PDF-1.4 just written');
    $disk->put("companies/{$w['company']->uuid}/logo-1.png", 'png');
    // The test clock is frozen: the files' times follow it.
    touch($disk->path("companies/{$w['company']->uuid}/documents/2026/10/lost.pdf"), now()->subHours(2)->getTimestamp());
    touch($disk->path("companies/{$w['company']->uuid}/documents/2026/10/fresh.pdf"), now()->subMinutes(5)->getTimestamp());
    touch($disk->path("companies/{$w['company']->uuid}/logo-1.png"), now()->subHours(2)->getTimestamp());
    $w['company']->forceFill(['logo_path' => "companies/{$w['company']->uuid}/logo-1.png"])->save();

    billAdmin();
    $this->get(route('platform.system'))->assertOk()->assertSeeText('Storage by company')->assertSeeText('Sharma Roadlines')
        ->assertSeeText('1 file on disk belongs to no document, photo or logo');
    $this->post(route('platform.system.prune'))->assertSessionHas('status', '1 file removed.');
    expect($disk->exists("companies/{$w['company']->uuid}/documents/2026/10/lost.pdf"))->toBeFalse()
        ->and($disk->exists("companies/{$w['company']->uuid}/documents/2026/10/fresh.pdf"))->toBeTrue()
        ->and($disk->exists("companies/{$w['company']->uuid}/logo-1.png"))->toBeTrue();

    if (is_executable('/usr/bin/mariadb-dump') || is_executable('/usr/bin/mysqldump')) {
        $dump = $this->post(route('platform.system.export'))->assertOk()->assertHeader('Content-Type', 'application/gzip')->streamedContent();
        expect(gzdecode($dump))->toContain('CREATE TABLE `companies`');
        expect(DB::table('audit_log')->where('action', 'data.exported')->exists())->toBeTrue();
    }

    $this->put(route('platform.system.announce'), ['announcement' => 'Maintenance on Sunday 2 am to 3 am <b>IST</b>'])->assertSessionHasNoErrors();
    $owner = makeAccount();
    joinCompany($owner, $w['company'], 'owner');
    phoneReset();
    $this->actingAs($owner, 'web')->withSession([\App\Http\Middleware\ResolveCompany::SESSION_KEY => $w['company']->id]);
    $this->get(route('dashboard'))->assertOk()->assertSeeText('Maintenance on Sunday 2 am to 3 am IST')->assertDontSee('<b>IST</b>', false);
    phoneAs(phoneCrew($w)['driver'], $w['company']);
    $this->getJson('/api/v1/me')->assertJsonPath('announcement', 'Maintenance on Sunday 2 am to 3 am IST');
});
