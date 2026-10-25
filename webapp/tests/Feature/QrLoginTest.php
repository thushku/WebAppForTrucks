<?php

use App\Http\Middleware\ResolveCompany;
use Illuminate\Support\Facades\Auth;

// "Sign in with your phone" (decision P7): the browser shows a code, the
// owner app approves it, and only that browser is signed in, once.

/**
 * Back to the browser after a phone call in the same test: in one test the
 * app lives on, so the phone's token header and the guard `auth:sanctum`
 * chose would otherwise carry over (they never do between real requests).
 */
function qrBackToBrowser(): void
{
    test()->flushHeaders();
    config(['auth.defaults.guard' => 'web']);
    Auth::forgetGuards();
}

function qrPhone($company, string $role = 'owner'): array
{
    $account = makeAccount(['name' => 'Phone Owner']);
    joinCompany($account, $company, $role);

    return [$account, $account->createToken('owner-app')->plainTextToken];
}

it('signs the waiting browser in once the phone approves', function () {
    $company = makeCompany();
    [$account, $token] = qrPhone($company);

    $code = $this->postJson(route('login.qr'))->assertOk()->json();
    expect($code['svg'])->toContain('<svg')->and($code['poll'])->toHaveLength(40);
    $this->postJson(route('login.qr.poll'), ['poll' => $code['poll']])->assertJson(['status' => 'waiting']);

    // The phone reads the token out of the link in the QR; the test makes its own code to know the token.
    $fresh = app(\App\Platform\Auth\WebLoginQr::class)->create('10.0.0.9', 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0');
    session()->put('login.qr_poll', hash('sha256', $fresh['poll']));

    $this->withToken($token)->withHeader('X-Company', $company->uuid)
        ->postJson(route('api.web-login.inspect'), ['code' => route('login.qr.scan', $fresh['token'])])
        ->assertOk()->assertJson(['browser' => 'Chrome on Windows', 'ip' => '10.0.0.9']);

    $this->withToken($token)->withHeader('X-Company', $company->uuid)
        ->postJson(route('api.web-login.approve'), ['code' => route('login.qr.scan', $fresh['token'])])
        ->assertOk()->assertJson(['ok' => true]);

    qrBackToBrowser();
    expect(Auth::guard('web')->check())->toBeFalse();

    $this->postJson(route('login.qr.poll'), ['poll' => $fresh['poll']])->assertJson(['status' => 'approved', 'redirect' => route('dashboard')]);
    expect(Auth::guard('web')->id())->toBe($account->id)
        ->and(session(ResolveCompany::SESSION_KEY))->toBe($company->id);

    // Used once only.
    $this->withToken($token)->withHeader('X-Company', $company->uuid)
        ->postJson(route('api.web-login.approve'), ['code' => $fresh['token']])->assertStatus(422)->assertJson(['code' => 'used']);
});

it('does not hand the sign-in to another browser that copied the poll secret', function () {
    $company = makeCompany();
    [, $token] = qrPhone($company);
    $code = $this->postJson(route('login.qr'))->json();
    $qrToken = app(\App\Platform\Auth\WebLoginQr::class)->create(null, null);
    session()->put('login.qr_poll', hash('sha256', $qrToken['poll']));

    $this->withToken($token)->withHeader('X-Company', $company->uuid)->postJson(route('api.web-login.approve'), ['code' => $qrToken['token']])->assertOk();

    qrBackToBrowser();
    // Another browser: its session never asked for this code.
    session()->forget('login.qr_poll');
    $this->postJson(route('login.qr.poll'), ['poll' => $qrToken['poll']])->assertJson(['status' => 'expired']);
    expect(Auth::guard('web')->check())->toBeFalse();
});

it('lets a code expire after two minutes', function () {
    $company = makeCompany();
    [, $token] = qrPhone($company);
    $qr = app(\App\Platform\Auth\WebLoginQr::class)->create(null, null);

    $this->travel(121)->seconds();

    $this->withToken($token)->withHeader('X-Company', $company->uuid)
        ->postJson(route('api.web-login.approve'), ['code' => $qr['token']])->assertStatus(422)->assertJson(['code' => 'expired']);
    expect(app(\App\Platform\Auth\WebLoginQr::class)->collect($qr['poll']))->toBeFalse();
});

it('approves only for a company the phone belongs to', function () {
    $company = makeCompany();
    $other   = makeCompany(['name' => 'Other Transport']);
    [, $token] = qrPhone($company);
    $qr = app(\App\Platform\Auth\WebLoginQr::class)->create(null, null);

    $this->withToken($token)->withHeader('X-Company', $other->uuid)
        ->postJson(route('api.web-login.approve'), ['code' => $qr['token']])->assertForbidden();
    qrBackToBrowser();
    $this->postJson(route('api.web-login.approve'), ['code' => $qr['token']])->assertUnauthorized();
    $this->withToken($token)->withHeader('X-Company', $company->uuid)
        ->postJson(route('api.web-login.approve'), ['code' => 'not-a-code'])->assertStatus(422);
});

it('shows a plain page when a phone camera opens the code as a link', function () {
    $this->get(route('login.qr.scan', str_repeat('a', 40)))->assertOk()->assertSee('Scan this from the app');
});

it('offers the phone sign-in on the login page', function () {
    $this->get(route('login'))->assertOk()->assertSee('data-qr-login', false)->assertSee(route('login.qr'), false);
});
