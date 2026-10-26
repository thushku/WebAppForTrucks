<?php

use App\Modules\Trips\Models\Consignment;
use App\Platform\Tenancy\CompanyContext;

// A client's own page (M47): one secure link with their loads of the last
// 60 days and each one's tracking link, their open bills and what is due —
// never another client's loads, never freight not yet billed. The office
// stops it and makes a new one; the owner app does the same.

it('gives a client one page with their loads, tracking and open bills', function () {
    $w = invWorld(loads: 3);
    [$billed, $waiting, $other] = $w['loads'];
    $ultra = app(CompanyContext::class)->runAs($w['company'], function () use ($other) {
        $ultra = makeParty(['name' => 'UltraTech Cement', 'short_code' => 'UTC'], ['client', 'consignor']);
        Consignment::whereKey($other->id)->update(['billing_party_id' => $ultra->id, 'consignor_party_id' => $ultra->id]);

        return $ultra;
    });
    $invoice = invDraft($w, [$billed->id]);
    $this->post(route('invoices.finalise', $invoice))->assertSessionHasNoErrors();
    $invoice = $invoice->fresh();

    // Made from the client's billing tab; asking again gives the same link.
    $this->get(route('parties.show', [$w['client'], 'tab' => 'billing']))->assertOk()->assertSeeText('Make the link');
    $this->post(route('parties.client-page.store', $w['client']))->assertRedirect()->assertSessionHas('client_page');
    $url = session('client_page')['url'];
    expect(session('client_page')['message'])->toContain('Your loads and bills with')->toContain($url);
    $this->post(route('parties.client-page.store', $w['client']));
    expect(session('client_page')['url'])->toBe($url);
    $this->get(route('parties.show', [$w['client'], 'tab' => 'billing']))->assertSee($url)->assertSeeText('Stop the link');

    // Opened with no sign-in: their two loads with tracking, the open bill — not UltraTech's load, not the unbilled freight.
    auth('web')->logout();
    $page = $this->get($url)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSeeText('ACC Limited')->assertSeeText($billed->lr_number)->assertSeeText($waiting->lr_number)->assertDontSeeText($other->lr_number)
        ->assertSeeText($invoice->number)->assertSee(route('documents.public', $invoice->public_token))->assertSeeText('₹75,520.00')
        ->assertDontSeeText('62,900');
    expect(substr_count($page->getContent(), '/t/'))->toBe(2);

    // Stopped: the link no longer opens; a new one is a different link.
    $this->actingAs($w['owner'], 'web');
    $this->delete(route('parties.client-page.destroy', $w['client']))->assertSessionHas('status');
    $this->get($url)->assertStatus(410)->assertSeeText('This link no longer opens');
    $this->post(route('parties.client-page.store', $w['client']));
    expect(session('client_page')['url'])->not->toBe($url);
    $this->get(session('client_page')['url'])->assertOk()->assertDontSeeText('UltraTech');
    $this->get(route('client.page', str_repeat('x', 40)))->assertStatus(410);
});

it('lets the owner app make, give and stop a client’s page link', function () {
    $w = invWorld();
    phoneReset();
    $this->withHeaders(['Authorization' => 'Bearer ' . $w['owner']->createToken('owner:android')->plainTextToken, 'X-Company' => $w['company']->uuid, 'Accept' => 'application/json']);
    $url = '/api/v1/owner/clients/' . $w['client']->id . '/page';

    $this->getJson($url)->assertOk()->assertJsonPath('url', null);
    $link = $this->postJson($url)->assertOk()->json('url');
    expect($link)->toContain('/c/');
    $this->postJson($url)->assertJsonPath('url', $link);
    $this->getJson($url)->assertJsonPath('url', $link)->assertJsonPath('message', fn ($m) => str_contains($m, $link));
    $this->deleteJson($url)->assertOk()->assertJsonPath('url', null);
    $this->getJson($url)->assertJsonPath('url', null);

    // A supplier has no client page.
    $raju = app(CompanyContext::class)->runAs($w['company'], fn () => makeParty(['name' => 'Raju Transport'], ['supplier']));
    $this->postJson('/api/v1/owner/clients/' . $raju->id . '/page')->assertNotFound();
});
