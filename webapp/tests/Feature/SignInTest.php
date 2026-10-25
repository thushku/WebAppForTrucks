<?php

use App\Models\Account;
use App\Platform\Auth\Models\OtpChallenge;
use Illuminate\Support\Facades\DB;

// Sign-in rules: same answer for every miss, the lock ladder (15 min → 1 h →
// by hand), the picture challenge, unverified emails, the one-time code.

it('signs a person in with email and password and opens their only company', function () {
    $company = makeCompany();
    $account = makeAccount(['email' => 'owner@example.com']);
    joinCompany($account, $company);

    $this->post('/login', ['login' => 'Owner@Example.com ', 'password' => 'Secret-pass-123'])
        ->assertRedirect(route('company.choose'));

    $this->assertAuthenticatedAs($account, 'web');
    $this->get(route('company.choose'))->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertOk()->assertSee('Sharma Roadlines');
});

it('signs in by mobile number in any common format', function () {
    $company = makeCompany();
    $account = makeAccount(['mobile' => '+919876543210']);
    joinCompany($account, $company);

    $this->post('/login', ['login' => '98765 43210', 'password' => 'Secret-pass-123'])->assertRedirect();
    $this->assertAuthenticatedAs($account, 'web');
});

it('gives the same answer for an unknown login and a wrong password', function () {
    makeAccount(['email' => 'known@example.com']);

    $unknown = $this->from('/login')->post('/login', ['login' => 'nobody@example.com', 'password' => 'x'])->getSession()->get('errors')->first('login');
    $wrong   = $this->from('/login')->post('/login', ['login' => 'known@example.com', 'password' => 'wrong'])->getSession()->get('errors')->first('login');

    expect($unknown)->toBe($wrong);
    $this->assertGuest('web');
});

it('locks for 15 minutes, then an hour, then until unlocked by hand', function () {
    $account = makeAccount(['email' => 'lock@example.com']);
    $tryWrong = fn () => $this->from('/login')->post('/login', ['login' => 'lock@example.com', 'password' => 'wrong', 'captcha' => 'x']);

    // Rung one: five wrong passwords.
    \Illuminate\Support\Facades\Cache::flush();
    for ($i = 0; $i < 5; $i++) {
        $this->app['cache']->flush(); // keep the picture challenge out of this test
        $tryWrong();
    }
    $account->refresh();
    expect($account->lock_level)->toBe(1)
        ->and($account->locked_until->diffInMinutes(now(), true))->toBeGreaterThanOrEqual(14);

    // Even the right password is refused while locked.
    $this->app['cache']->flush();
    $this->post('/login', ['login' => 'lock@example.com', 'password' => 'Secret-pass-123']);
    $this->assertGuest('web');

    // Rung two: after the 15 minutes, five more wrong → an hour.
    $this->travel(16)->minutes();
    for ($i = 0; $i < 5; $i++) {
        $this->app['cache']->flush();
        $tryWrong();
    }
    $account->refresh();
    expect($account->lock_level)->toBe(2)
        ->and((int) round(now()->diffInMinutes($account->locked_until)))->toBe(60);

    // Rung three: locked for good.
    $this->travel(61)->minutes();
    for ($i = 0; $i < 5; $i++) {
        $this->app['cache']->flush();
        $tryWrong();
    }
    $account->refresh();
    expect($account->lock_level)->toBe(3)->and($account->locked_hard_at)->not->toBeNull();

    $this->travel(30)->days();
    $this->app['cache']->flush();
    $this->post('/login', ['login' => 'lock@example.com', 'password' => 'Secret-pass-123']);
    $this->assertGuest('web');

    // Opening it by hand lets the person in again.
    $account->unlock();
    joinCompany($account, makeCompany());
    $this->app['cache']->flush();
    $this->post('/login', ['login' => 'lock@example.com', 'password' => 'Secret-pass-123']);
    $this->assertAuthenticatedAs($account->fresh(), 'web');
});

it('asks for the picture after three failures from the same computer', function () {
    makeAccount(['email' => 'pic@example.com']);

    for ($i = 0; $i < 3; $i++) {
        $this->from('/login')->post('/login', ['login' => 'pic@example.com', 'password' => 'wrong']);
    }

    $this->get('/login')->assertSee('Type the characters in the picture');

    // The right password without the picture is refused.
    $this->from('/login')->post('/login', ['login' => 'pic@example.com', 'password' => 'Secret-pass-123'])
        ->assertSessionHasErrors('captcha');
    $this->assertGuest('web');
});

it('does not let an unconfirmed email sign in', function () {
    $account = makeAccount(['email' => 'new@example.com']);
    $account->forceFill(['email_verified_at' => null])->save();

    $this->from('/login')->post('/login', ['login' => 'new@example.com', 'password' => 'Secret-pass-123'])
        ->assertSessionHasErrors('login');
    $this->assertGuest('web');
});

it('asks for an emailed code when the company requires it, and accepts it once', function () {
    \Illuminate\Support\Facades\Mail::fake();
    $company = makeCompany(['mfa_policy' => 'always']);
    $account = makeAccount(['email' => 'mfa@example.com']);
    joinCompany($account, $company);

    $this->post('/login', ['login' => 'mfa@example.com', 'password' => 'Secret-pass-123'])
        ->assertRedirect(route('login.otp'));
    $this->assertGuest('web');

    // Only the hash is stored; read the code by issuing a known one.
    $challenge = OtpChallenge::latest('id')->first();
    expect($challenge->code_hash)->toStartWith('$2y$');
    $challenge->forceFill(['code_hash' => \Illuminate\Support\Facades\Hash::make('123456')])->save();

    $this->post(route('login.otp.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
    $this->post(route('login.otp.verify'), ['code' => '123456'])->assertRedirect(route('company.choose'));
    $this->assertAuthenticatedAs($account, 'web');

    // A used code cannot be used again.
    expect($challenge->fresh()->consumed_at)->not->toBeNull();
});

it('records every attempt for the activity log', function () {
    makeAccount(['email' => 'log@example.com']);
    $this->from('/login')->post('/login', ['login' => 'log@example.com', 'password' => 'wrong']);

    expect(DB::table('login_attempts')->where('identifier', 'log@example.com')->where('succeeded', false)->count())->toBe(1);
});

it('asks a person with two companies which one to open, and only offers their own', function () {
    $account = makeAccount(['email' => 'two@example.com']);
    $a = makeCompany(['name' => 'Alpha Transport']);
    $b = makeCompany(['name' => 'Bravo Logistics']);
    $c = makeCompany(['name' => 'Charlie Cargo']);
    joinCompany($account, $a, 'driver');
    joinCompany($account, $b, 'manager');

    $this->post('/login', ['login' => 'two@example.com', 'password' => 'Secret-pass-123']);
    $this->get(route('company.choose'))->assertOk()->assertSee('Alpha Transport')->assertSee('Bravo Logistics')->assertDontSee('Charlie Cargo');

    // Someone else's company is refused.
    $this->post(route('company.switch'), ['company' => $c->uuid])->assertRedirect(route('company.choose'));
    $this->post(route('company.switch'), ['company' => $b->uuid])->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertOk()->assertSee('Bravo Logistics');
});
