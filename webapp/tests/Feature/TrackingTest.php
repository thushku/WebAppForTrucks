<?php

use App\Modules\Road\Models\ConsignmentDelivery;
use App\Modules\Road\Models\GpsPoint;
use App\Modules\Trips\Models\Consignment;
use App\Modules\Trips\Services\TripService;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;

// A client's tracking link for an LR (M45): no sign-in; the steps, the
// nearest place while on the road, the delivery — never the freight or the
// crew; the same link each time; ended 30 days after delivery.

it('gives the client a tracking link that follows the load to its delivery', function () {
    $w = phoneWorld();
    $owner = signInTo($w['company']);
    $load = app(CompanyContext::class)->runAs($w['company'], fn () => Consignment::where('trip_id', $w['trip']->id)->firstOrFail());

    $this->post(route('trips.track', [$w['trip'], $load]))->assertRedirect()->assertSessionHas('track');
    $url = session('track')['url'];
    expect($url)->toContain('/t/')->and(session('track')['message'])->toContain('Kochi → Mumbai');
    $this->post(route('trips.track', [$w['trip'], $load]));
    expect(session('track')['url'])->toBe($url);

    // Sent, not started: booked only.
    auth()->logout();
    $this->get($url)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSeeText('LR ' . $load->lr_number)->assertSeeText('Kochi → Mumbai')
        ->assertSeeText('ACC Limited')->assertDontSeeText('62,900')->assertDontSeeText('Ravi Kumar')->assertDontSeeText('Client pays late');

    // On the road near Thrissur: the nearest place, the position rounded.
    app(CompanyContext::class)->runAs($w['company'], function () use ($w, $owner, $load) {
        auth()->setUser($owner);
        \App\Modules\Settings\Models\Place::create(['name' => 'Thrissur', 'short_code' => 'TCR', 'lat' => 10.5276, 'lng' => 76.2144]);
        app(TripService::class)->start($w['trip']->fresh(), 100100);
        $load->forceFill(['status' => 'in_transit'])->save();
        GpsPoint::create(['person_id' => $w['driver']->id, 'trip_id' => $w['trip']->id, 'recorded_at' => now(), 'lat' => 10.4512345, 'lng' => 76.2321234, 'accuracy_m' => 10, 'received_at' => now()]);
    });
    auth()->logout();
    $this->get($url)->assertOk()->assertSeeText('Near Thrissur (about 9 km)')->assertSee('mlat=10.45', false)->assertDontSee('10.4512345', false)->assertSeeText('On the way');

    // Delivered: who received it; 31 days later the link has ended.
    app(CompanyContext::class)->runAs($w['company'], function () use ($load) {
        ConsignmentDelivery::create(['consignment_id' => $load->id, 'receiver_name' => 'S. Patil', 'short_packages' => 2, 'delivered_at' => now()]);
        $load->forceFill(['status' => 'delivered', 'delivered_at' => now()])->save();
    });
    $this->get($url)->assertOk()->assertSeeText('received by S. Patil')->assertSeeText('2 short')->assertDontSeeText('Near Thrissur');
    $this->travel(31)->days();
    $this->get($url)->assertStatus(410)->assertSeeText('This tracking link has ended');
    $this->get('/t/' . str_repeat('A', 40))->assertStatus(410);
});

it('gives the owner app the same tracking link', function () {
    $w = phoneWorld();
    $owner = signInTo($w['company']);
    $load = app(CompanyContext::class)->runAs($w['company'], fn () => Consignment::where('trip_id', $w['trip']->id)->firstOrFail());
    phoneReset();
    $this->withHeaders(['Authorization' => 'Bearer ' . $owner->createToken('owner:android')->plainTextToken, 'X-Company' => $w['company']->uuid, 'Accept' => 'application/json']);
    $first = $this->postJson('/api/v1/owner/consignments/' . $load->id . '/track')->assertOk()->json();
    expect($first['message'])->toContain($first['url'])
        ->and($this->postJson('/api/v1/owner/consignments/' . $load->id . '/track')->json('url'))->toBe($first['url']);
});
