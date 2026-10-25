<?php

// Website guideline §4: CSP, framing, sniffing, referrer — on every response.

it('sends the security headers on a page', function () {
    $response = $this->get('/login');

    $response->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'same-origin');

    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)->toContain("script-src 'self' 'nonce-")
        ->and($csp)->toContain("object-src 'none'")
        ->and($csp)->toContain("frame-ancestors 'self'")
        ->and($csp)->not->toContain("'unsafe-eval'");
    expect($response->headers->has('X-Powered-By'))->toBeFalse();
});

it('makes a fresh script nonce for every response', function () {
    $first  = $this->get('/login')->headers->get('Content-Security-Policy');
    $second = $this->get('/login')->headers->get('Content-Security-Policy');

    preg_match("/nonce-([^']+)'/", $first, $a);
    preg_match("/nonce-([^']+)'/", $second, $b);
    expect($a[1])->not->toBe($b[1]);
});

it('sends the headers on API answers and errors too', function () {
    $this->getJson('/api/v1/ping')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get('/no-such-page')->assertNotFound()->assertHeader('X-Frame-Options', 'SAMEORIGIN');
});

it('does not serve the console at a guessable address', function () {
    $this->get('/admin')->assertNotFound();
    $this->get('/admin/login')->assertNotFound();
    $this->get('/platform/login')->assertNotFound();
    // The real (configured) path works.
    $this->get('/' . config('platform.path') . '/login')->assertOk();
});

it('protects every web form with a CSRF token', function () {
    // Laravel skips the check inside tests; prove it is in the web group and on the form.
    $groups = app(\Illuminate\Routing\Router::class)->getMiddlewareGroups();
    // Laravel 13 names the CSRF check PreventRequestForgery.
    expect($groups['web'])->toContain(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);

    $this->get('/login')->assertSee('name="_token"', false);
});
