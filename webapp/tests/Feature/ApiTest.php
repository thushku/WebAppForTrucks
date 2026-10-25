<?php

// The phone-app API: reachable before sign-in for the clock check; the rest
// needs a token and a company the person belongs to.

it('answers ping with the server time', function () {
    $this->getJson('/api/v1/ping')->assertOk()->assertJsonStructure(['ok', 'server_time', 'version']);
});

it('needs a token for anything else', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

it('works in the company named in the header, and only a company the person belongs to', function () {
    $account = makeAccount();
    $a = makeCompany(['name' => 'Alpha']);
    $b = makeCompany(['name' => 'Bravo']);
    $c = makeCompany(['name' => 'Charlie']);
    joinCompany($account, $a, 'driver');
    joinCompany($account, $b, 'driver');
    $token = $account->createToken('test-phone')->plainTextToken;

    // Two companies and no header: the phone must choose.
    $this->withToken($token)->getJson('/api/v1/me')->assertStatus(409)->assertJson(['code' => 'company_required']);

    $this->withToken($token)->withHeader('X-Company', $b->uuid)->getJson('/api/v1/me')
        ->assertOk()->assertJsonPath('company.name', 'Bravo')->assertJsonFragment(['event.record']);

    $this->withToken($token)->withHeader('X-Company', $c->uuid)->getJson('/api/v1/me')
        ->assertForbidden()->assertJson(['code' => 'company_forbidden']);
});
