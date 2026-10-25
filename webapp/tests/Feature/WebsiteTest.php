<?php

use App\Models\Account;
use App\Platform\Billing\Coupon;
use App\Platform\Billing\Plan;
use App\Platform\Console\Admin;
use App\Platform\Tenancy\Company;
use App\Platform\Tenancy\CompanyContext;
use App\Platform\Website\ContactMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

// The public website and sign-up (M14): the pages, pricing from the console's
// plans with a coupon, the plan and coupon carried into sign-up, the contact
// form, the texts the console edits, and the getting-started steps.

function siteCaptcha(): string
{
    $code = app(\App\Platform\Auth\Captcha::class)->issue(app('session.store'));
    session()->save();

    return $code;
}

function sitePlans(): array
{
    $make = fn (array $a) => Plan::create(array_merge(['country' => 'IN', 'currency' => 'INR', 'pricing' => 'flat', 'is_public' => true, 'active' => true], $a));

    return [
        'starter' => $make(['code' => 'starter', 'name' => 'Starter', 'price_monthly' => 500, 'price_annual' => 5000, 'max_trucks' => 5, 'sort_order' => 1]),
        'fleet' => $make(['code' => 'fleet', 'name' => 'Fleet', 'pricing' => 'per_truck', 'price_monthly' => 150, 'price_annual' => 1500, 'sort_order' => 2]),
        'student' => $make(['code' => 'student', 'name' => 'Student', 'price_monthly' => 0, 'price_annual' => 0, 'is_student' => true, 'sort_order' => 3]),
        'hidden' => $make(['code' => 'partner', 'name' => 'Partner deal', 'price_monthly' => 99, 'is_public' => false]),
        'off' => $make(['code' => 'old', 'name' => 'Old plan', 'price_monthly' => 99, 'active' => false]),
        'uae' => $make(['code' => 'fleet-ae', 'name' => 'Fleet UAE', 'country' => 'AE', 'currency' => 'AED', 'pricing' => 'per_truck', 'price_monthly' => 25, 'price_annual' => 250]),
    ];
}

function siteAdmin(): Admin
{
    $admin = new Admin(['name' => 'Ops', 'email' => 'site-ops' . uniqid() . '@example.com']);
    $admin->forceFill(['password_hash' => Hash::make('Console-pass-12345'), 'status' => 'active'])->save();
    test()->actingAs($admin, 'platform');

    return $admin;
}

it('shows the public pages to visitors, with the trial days from the console, and the app to people signed in', function () {
    app(\App\Platform\Settings\PlatformSettings::class)->set('trial_days', 21);

    $this->get('/')->assertOk()->assertSeeText('Every trip, every rupee — proved from the road.')->assertSeeText('Start 21-day free trial')
        ->assertSee('data-splash', false)->assertSee(route('site.legal', 'privacy'), false)->assertSee('Version', false);
    // The splash shows on the first page of a visit only.
    $this->get(route('site.features'))->assertOk()->assertDontSee('data-splash', false)->assertSeeText('Crew app')->assertSeeText('Coming soon');
    $this->get(route('site.faq'))->assertOk()->assertSeeText('What if there is no network on the road?')->assertSeeText('21 days with every feature');
    $this->get(route('site.about'))->assertOk()->assertSeeText('Why we built it');
    foreach (['terms' => 'Terms of service', 'privacy' => 'Privacy policy', 'refund' => 'Refund policy'] as $page => $title) {
        $this->get(route('site.legal', $page))->assertOk()->assertSeeText($title)->assertSeeText('Last updated');
    }
    $this->get('/legal/cookies')->assertNotFound();
    $this->get(route('site.sitemap'))->assertOk()->assertHeader('Content-Type', 'application/xml')->assertSee(route('site.pricing'), false);

    signInTo(makeCompany());
    $this->get('/')->assertRedirect(route('dashboard'));
    $this->get('/?site=1')->assertOk()->assertSeeText('Open the app');
});

it('prices from the console’s plans per country, with the yearly saving and a coupon taken off', function () {
    $plans = sitePlans();
    Coupon::create(['code' => 'LAUNCH20', 'kind' => 'percent', 'value' => 20]);
    Coupon::create(['code' => 'FLEETONLY', 'kind' => 'percent', 'value' => 10, 'plan_ids' => [$plans['uae']->id]]);
    Coupon::create(['code' => 'ENDED', 'kind' => 'percent', 'value' => 10, 'valid_until' => '2026-01-31']);

    $this->get(route('site.pricing'))->assertOk()
        ->assertSeeText('Starter')->assertSeeText('₹500')->assertSeeText('₹5,000')->assertSeeText('per truck a month')->assertSeeText('₹150')
        ->assertSeeText('You save 17% against monthly')->assertSeeText('Free')
        ->assertDontSeeText('Partner deal')->assertDontSeeText('Old plan')->assertDontSeeText('Fleet UAE')
        ->assertSee(route('signup', ['plan' => 'fleet']), false);
    $this->get(route('site.pricing', ['country' => 'AE']))->assertOk()->assertSeeText('Fleet UAE')->assertSeeText('Prices are before VAT.')->assertDontSeeText('Starter');

    $html = $this->get(route('site.pricing', ['coupon' => 'launch20']))->assertOk()->assertSeeText('LAUNCH20: 20% off the plans marked below.')
        ->assertSeeText('₹400')->assertSeeText('₹120')->getContent();
    expect($html)->toContain('<s>₹500</s>')->toContain(e(route('signup', ['plan' => 'starter', 'coupon' => 'LAUNCH20'])))
        // No coupon badge or price on the free plan.
        ->not->toContain(e(route('signup', ['plan' => 'student', 'coupon' => 'LAUNCH20'])));

    $this->get(route('site.pricing', ['coupon' => 'NOPE']))->assertOk()->assertSeeText('There is no coupon NOPE.');
    $this->get(route('site.pricing', ['coupon' => 'ENDED']))->assertOk()->assertSeeText('This coupon ended on 31 Jan 2026.');
    $this->get(route('site.pricing', ['coupon' => 'FLEETONLY']))->assertOk()->assertSeeText('This coupon is not for the Starter plan.');
    $this->get(route('site.pricing', ['coupon' => '<script>']))->assertRedirect();
});

it('carries the plan and coupon chosen on the website into sign-up, for the console to apply', function () {
    Mail::fake();
    $plans = sitePlans();

    $this->get(route('signup', ['plan' => 'fleet', 'coupon' => 'launch20']))->assertOk()
        ->assertSeeText('Your choice: the Fleet plan.')->assertSeeText('Coupon LAUNCH20 is kept for your first payment.')
        ->assertSee('name="plan" value="fleet"', false);
    $this->get(route('signup', ['plan' => 'partner']))->assertOk()->assertDontSeeText('Your choice');

    $this->post(route('signup.store'), [
        'company' => 'Kerala Carriers', 'country' => 'IN', 'name' => 'Thomas George', 'email' => 'thomas@example.com',
        'password' => 'Strong-pass-2026', 'password_confirmation' => 'Strong-pass-2026', 'terms' => '1', 'captcha' => siteCaptcha(),
        'plan' => 'fleet', 'coupon' => 'launch20',
    ])->assertRedirect(route('signup.sent'));
    $company = Company::where('name', 'Kerala Carriers')->firstOrFail();
    expect($company->requested_plan_id)->toBe($plans['fleet']->id)->and($company->requested_coupon)->toBe('LAUNCH20');

    siteAdmin();
    $this->get(route('platform.companies.show', $company))->assertOk()->assertSeeText('Chose on the website: Fleet · coupon LAUNCH20')
        ->assertSee('value="LAUNCH20"', false);
});

it('takes a message from the contact page, checks it, keeps it for the console and mails support', function () {
    Mail::fake();
    app(\App\Platform\Settings\PlatformSettings::class)->set('support_email', 'help@fleet.test');
    app(\App\Platform\Settings\PlatformSettings::class)->set('support_whatsapp', '+91 98470 00000');
    $this->get(route('site.contact'))->assertOk()->assertSee('https://wa.me/919847000000', false);

    $form = ['name' => 'Suresh Nair', 'company' => 'Nair Transports', 'email' => 'Suresh@Example.com', 'mobile' => '+91 98470 11111', 'topic' => 'sales',
        'message' => 'We run 40 trucks from Kochi. Can we see a demo next week?'];
    $this->post(route('site.contact.send'), $form + ['captcha' => 'WRONG'])->assertSessionHasErrors('captcha');
    $this->post(route('site.contact.send'), ['mobile' => 'call me'] + $form + ['captcha' => siteCaptcha()])->assertSessionHasErrors('mobile');
    expect(ContactMessage::count())->toBe(0);

    // A robot fills the hidden field: thanked, nothing kept.
    $this->post(route('site.contact.send'), $form + ['website' => 'http://spam.example', 'captcha' => siteCaptcha()])->assertSessionHas('status');
    expect(ContactMessage::count())->toBe(0);

    $this->post(route('site.contact.send'), ['message' => 'Can we see a <b>demo</b> next week for our 40 trucks?'] + $form + ['captcha' => siteCaptcha()])
        ->assertRedirect(route('site.contact'))->assertSessionHas('status', 'Thank you — we have your message and will answer soon.');
    $message = ContactMessage::firstOrFail();
    expect($message->email)->toBe('suresh@example.com')->and($message->message)->toBe('Can we see a demo next week for our 40 trucks?');

    siteAdmin();
    $this->get(route('platform.home'))->assertOk()->assertSeeText('Messages from the website')->assertSeeText('Suresh Nair')->assertSeeText('Can we see a demo next week');
    $this->post(route('platform.messages.handled', $message))->assertSessionHas('status');
    expect($message->fresh()->handled_at)->not->toBeNull();
    $this->get(route('platform.home'))->assertSeeText('No message waiting.');
});

it('lets the console replace the website’s texts, plain text only, and go back to the shipped ones', function () {
    siteAdmin();
    $shipped = app(\App\Platform\Website\SiteText::class)->shipped('terms');
    $this->get(route('platform.settings'))->assertOk()->assertSeeText('Crew app download link')->assertSee(e(mb_substr($shipped, 0, 60)), false);

    $this->put(route('platform.settings.website'), ['site_terms' => "## Our terms\n\nUse :brand <script>alert(1)</script>fairly.\n\n- One\n- Two", 'site_about' => $shipped, 'crew_app_url' => 'http://not-secure.example'])
        ->assertSessionHasErrors('crew_app_url');
    $this->put(route('platform.settings.website'), ['site_terms' => "## Our terms\n\nUse :brand <script>alert(1)</script>fairly.\n\n- One\n- Two", 'crew_app_url' => 'https://play.google.com/store/apps/details?id=fleet.crew'])
        ->assertSessionHasNoErrors();
    $brand = app(\App\Platform\Settings\PlatformSettings::class)->brandName();
    $html = $this->get(route('site.legal', 'terms'))->assertOk()->getContent();
    expect($html)->toContain('<h2>Our terms</h2>')->toContain("<p>Use {$brand} alert(1)fairly.</p>")->toContain('<ul><li>One</li><li>Two</li></ul>')->not->toContain('<script>alert');

    // Sending the shipped text back unchanged, or ticking the reset, keeps following the shipped text.
    $this->put(route('platform.settings.website'), ['site_terms' => 'anything', 'reset_terms' => '1'])->assertSessionHasNoErrors();
    expect(app(\App\Platform\Website\SiteText::class)->isCustom('terms'))->toBeFalse();
    $this->get(route('site.legal', 'terms'))->assertOk()->assertSeeText('These terms are between you');
});

it('walks a new company through its first steps, from the first sign-in to the first trip', function () {
    $account = makeAccount(['email' => 'new@example.com', 'email_verified_at' => null]);
    $company = makeCompany(['name' => 'Kerala Carriers']);
    joinCompany($account, $company);
    $token = app(\App\Platform\Auth\PasswordTokens::class)->issue('account', $account->id, 'verify');

    // Confirming the email, then signing in, opens the steps.
    $this->get(route('signup.verify', $token))->assertRedirect(route('login'));
    $this->post('/login', ['login' => 'new@example.com', 'password' => 'Secret-pass-123'])->assertRedirect(route('welcome'));
    $this->get(route('welcome'))->assertOk()->assertSeeText('Welcome to Kerala Carriers')->assertSeeText('0 of 5 done')
        ->assertSeeText('The download link appears here as soon as the crew app is published.');
    $this->get(route('dashboard'))->assertOk()->assertSeeText('Getting started: 0 of 5 done');

    $gstin = '32AABCK1234F1Z' . \App\Modules\Parties\Services\TaxIds::gstinCheckCharacter('32AABCK1234F1Z');
    $this->post(route('welcome.company'), ['address_line1' => 'Container Road', 'city' => 'Kochi', 'state_code' => '32', 'postal_code' => '6825', 'fy_start_month' => 4])
        ->assertSessionHasErrors('postal_code');
    $this->post(route('welcome.company'), ['address_line1' => 'Container Road', 'city' => 'Kochi', 'state_code' => '32', 'postal_code' => '682504', 'tax_id' => '32ABC', 'fy_start_month' => 4])
        ->assertSessionHasErrors('tax_id');
    $this->post(route('welcome.company'), ['legal_name' => 'Kerala Carriers LLP', 'address_line1' => 'Container Road', 'city' => 'Kochi', 'state_code' => '32', 'postal_code' => '682504', 'tax_id' => $gstin, 'fy_start_month' => 4])
        ->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($company);
    expect($company->fresh()->city)->toBe('Kochi')->and(\App\Modules\Tax\Models\TaxRegistration::value('tax_id'))->toBe($gstin);

    makeTruck([], $company);
    makePerson(['type' => 'driver'], $company);
    app(\App\Platform\Settings\PlatformSettings::class)->set('crew_app_url', 'https://play.google.com/store/apps/details?id=fleet.crew');
    $this->get(route('welcome'))->assertOk()->assertSeeText('3 of 5 done')->assertSee('<svg', false)->assertSee('id=fleet.crew', false);

    // Once the books have an entry the year's start is fixed.
    DB::table('vouchers')->insert(['company_id' => $company->id, 'uuid' => (string) \Illuminate\Support\Str::uuid(), 'voucher_type' => 'journal', 'number' => 'JV/1', 'voucher_date' => '2026-10-25',
        'narration' => 'Opening', 'total' => 0, 'currency' => 'INR', 'status' => 'posted', 'created_at' => now(), 'updated_at' => now()]);
    $this->post(route('welcome.company'), ['address_line1' => 'Container Road', 'city' => 'Kochi', 'state_code' => '32', 'fy_start_month' => 1])->assertSessionHasErrors('fy_start_month');
    expect($company->fresh()->fy_start_month)->toBe(4);

    $this->post(route('welcome.finish'))->assertRedirect(route('dashboard'));
    expect($company->fresh()->setup_done_at)->not->toBeNull();
    $this->get(route('dashboard'))->assertOk()->assertDontSeeText('Getting started:');

    signInTo($company, 'driver');
    $this->get(route('welcome'))->assertForbidden();
});
