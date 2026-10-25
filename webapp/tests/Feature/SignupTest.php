<?php

use App\Models\Account;
use App\Platform\Auth\PasswordTokens;
use App\Platform\Tenancy\Company;
use Illuminate\Support\Facades\Mail;

// Sign-up: free trial, standard roles, nobody in before the email is confirmed.

it('creates a company on a free trial with the owner and the standard roles', function () {
    Mail::fake();
    $code = app(\App\Platform\Auth\Captcha::class)->issue(app('session.store'));
    session()->save();

    $this->post(route('signup.store'), [
        'company' => 'Kerala Carriers', 'country' => 'IN', 'name' => 'Thomas George',
        'email' => 'Thomas@Example.com', 'mobile' => '9847012345',
        'password' => 'Strong-pass-2026', 'password_confirmation' => 'Strong-pass-2026',
        'terms' => '1', 'captcha' => $code,
    ])->assertRedirect(route('signup.sent'));

    $company = Company::where('name', 'Kerala Carriers')->first();
    $account = Account::where('email', 'thomas@example.com')->first();

    expect($company->status)->toBe('trial')
        ->and($company->currency)->toBe('INR')
        ->and($company->fy_start_month)->toBe(4)
        ->and($company->trial_ends_at->isSameDay(now()->addDays(14)))->toBeTrue()
        // A 14-day trial reads "14 days left" on the first day, by the company's own clock.
        ->and($company->trialDaysLeft())->toBe(14)
        ->and($account->mobile)->toBe('+919847012345')
        ->and($account->email_verified_at)->toBeNull();

    app(\App\Platform\Tenancy\CompanyContext::class)->set($company);
    expect(\App\Platform\Authorization\Models\Role::pluck('code')->sort()->values()->all())
        ->toBe(['accountant', 'driver', 'helper', 'manager', 'owner', 'viewer', 'workshop']);
});

it('confirms the email from the link, once', function () {
    $account = makeAccount(['email' => 'link@example.com', 'email_verified_at' => null]);
    $account->forceFill(['email_verified_at' => null])->save();
    $token = app(PasswordTokens::class)->issue('account', $account->id, 'verify');

    $this->get(route('signup.verify', $token))->assertRedirect(route('login'));
    expect($account->fresh()->email_verified_at)->not->toBeNull();

    $this->get(route('signup.verify', $token))->assertRedirect(route('login'))->assertSessionHas('error');
});

it('does not reveal that an email already has an account', function () {
    Mail::fake();
    makeAccount(['email' => 'taken@example.com']);
    $code = app(\App\Platform\Auth\Captcha::class)->issue(app('session.store'));
    session()->save();

    $this->post(route('signup.store'), [
        'company' => 'Second Co', 'country' => 'IN', 'name' => 'Someone',
        'email' => 'taken@example.com', 'password' => 'Strong-pass-2026', 'password_confirmation' => 'Strong-pass-2026',
        'terms' => '1', 'captcha' => $code,
    ])->assertRedirect(route('signup.sent'));

    expect(Company::where('name', 'Second Co')->exists())->toBeFalse();
});

it('resets a password from the emailed link and ends any lock', function () {
    Mail::fake();
    $account = makeAccount(['email' => 'reset@example.com']);
    $account->forceFill(['lock_level' => 3, 'locked_hard_at' => now()])->save();
    $token = app(PasswordTokens::class)->issue('account', $account->id, 'reset');

    $this->post(route('password.update', $token), ['password' => 'Brand-new-pass-77', 'password_confirmation' => 'Brand-new-pass-77'])
        ->assertRedirect(route('login'));

    $account->refresh();
    expect(\Illuminate\Support\Facades\Hash::check('Brand-new-pass-77', $account->password_hash))->toBeTrue()
        ->and($account->locked_hard_at)->toBeNull();

    // The link works once.
    $this->post(route('password.update', $token), ['password' => 'Another-pass-88', 'password_confirmation' => 'Another-pass-88'])
        ->assertRedirect(route('password.forgot'));
});
