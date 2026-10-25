<?php

use App\Platform\Auth\Models\OtpChallenge;
use App\Platform\Console\Admin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

// The super-admin console: secret path, picture every time, code every time.

function makeAdmin(string $email = 'ops@example.com', string $password = 'Console-pass-12345'): Admin
{
    $admin = new Admin(['name' => 'Ops', 'email' => $email]);
    $admin->forceFill(['password_hash' => Hash::make($password), 'status' => 'active'])->save();

    return $admin;
}

/** Pass the picture challenge in a test by fixing its answer in the session. */
function withKnownCaptcha(): string
{
    $code = app(\App\Platform\Auth\Captcha::class)->issue(app('session.store'));
    session()->save();

    return $code;
}

it('asks for the emailed code after every correct password', function () {
    Mail::fake();
    $admin = makeAdmin();
    $path  = '/' . config('platform.path');

    $this->get($path . '/login')->assertOk();
    $code = withKnownCaptcha();

    $this->post($path . '/login', ['email' => 'ops@example.com', 'password' => 'Console-pass-12345', 'captcha' => $code])
        ->assertRedirect(route('platform.otp'));
    $this->assertGuest('platform');

    $challenge = OtpChallenge::where('subject_type', 'admin')->latest('id')->first();
    $challenge->forceFill(['code_hash' => Hash::make('654321')])->save();

    $this->post(route('platform.otp.verify'), ['code' => '654321'])->assertRedirect(route('platform.home'));
    $this->assertAuthenticatedAs($admin, 'platform');
    $this->get(route('platform.home'))->assertOk()->assertSee('Locked logins');
});

it('refuses a console sign-in without the picture', function () {
    makeAdmin();
    $this->from(route('platform.login'))
        ->post(route('platform.login.attempt'), ['email' => 'ops@example.com', 'password' => 'Console-pass-12345', 'captcha' => 'WRONG'])
        ->assertSessionHasErrors('captcha');
    $this->assertGuest('platform');
});

it('keeps a company login out of the console', function () {
    signInTo(makeCompany());
    $this->get(route('platform.home'))->assertRedirect(route('platform.login'));
});

it('lets the super-admin unlock a hard-locked login', function () {
    $admin   = makeAdmin();
    $account = makeAccount(['email' => 'stuck@example.com']);
    $account->forceFill(['lock_level' => 3, 'locked_hard_at' => now()])->save();

    $this->actingAs($admin, 'platform')
        ->post(route('platform.accounts.unlock', $account))
        ->assertRedirect();

    expect($account->fresh()->locked_hard_at)->toBeNull()->and($account->fresh()->lock_level)->toBe(0);
    expect(\Illuminate\Support\Facades\DB::table('audit_log')->where('action', 'security.unlocked')->exists())->toBeTrue();
});

it('saves the trial length, product name and languages', function () {
    $this->actingAs(makeAdmin(), 'platform')
        ->put(route('platform.settings.save'), [
            'captcha_mode' => 'always', 'trial_days' => 21, 'brand_name' => 'Truck Pro',
            'enabled_languages' => ['hi', 'ar'],
        ])->assertRedirect();

    $settings = app(\App\Platform\Settings\PlatformSettings::class);
    expect($settings->trialDays())->toBe(21)
        ->and($settings->brandName())->toBe('Truck Pro')
        ->and($settings->get('enabled_languages'))->toBe(['en', 'hi', 'ar']);
});
