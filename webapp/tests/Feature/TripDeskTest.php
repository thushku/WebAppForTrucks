<?php

use App\Modules\Fleet\Models\Truck;
use App\Modules\Invoicing\Design\DesignSchema;
use App\Modules\Invoicing\Models\DocumentTemplate;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Parties\Models\Party;
use App\Modules\People\Models\Person;
use App\Modules\Settings\Models\Place;
use App\Platform\Tenancy\CompanyContext;

// The trip desk (M25, 13-parity #3–5, #25): "+ New" inside the trip form,
// the billing filter on the trip list, the trip report of the filtered
// list, and the driver column of the LR annexure.

it('adds a client, a driver, a truck and a place from inside the trip form', function () {
    $w = qtWorld();

    $this->get(route('trips.create'))->assertOk()->assertSee('data-quick-dialog="client"', false)->assertSee('data-quick-dialog="truck"', false)
        ->assertSee('data-quick-open="place"', false)->assertSeeText('New driver');

    $client = $this->postJson(route('quick.client'), ['name' => 'Kerala Agro Foods', 'mobile' => '+91 98470 12345'])->assertCreated()->json();
    $driver = $this->postJson(route('quick.person'), ['name' => 'Manoj Thomas', 'type' => 'driver'])->assertCreated()->json();
    $truck = $this->postJson(route('quick.truck'), ['reg_no' => 'kl 07 ce 9090', 'rc_owner_name' => 'Sharma Roadlines'])->assertCreated()->json();
    $this->postJson(route('quick.truck'), ['reg_no' => 'KL-07-CE-9090', 'rc_owner_name' => 'X Y'])->assertStatus(422)->assertJsonValidationErrors('reg_no');
    $place = $this->postJson(route('quick.place'), ['name' => 'Thrissur', 'address' => "Warehouse 1, Puzhakkal\nThrissur 680553"])->assertCreated()->json();
    $this->postJson(route('quick.person'), ['name' => 'X', 'type' => 'driver'])->assertStatus(422);

    app(CompanyContext::class)->set($w['company']);
    $party = Party::find($client['id']);
    expect($party->roleCodes())->toBe(['client'])->and($party->short_code)->not->toBeNull()->and($client['label'])->toBe('Kerala Agro Foods (' . $party->short_code . ')')
        ->and(Person::find($driver['id'])->only(['type', 'status']))->toBe(['type' => 'driver', 'status' => 'active'])
        ->and(Truck::find($truck['id'])->only(['reg_no', 'ownership', 'fuel_type']))->toBe(['reg_no' => 'KL 07 CE 9090', 'ownership' => 'own', 'fuel_type' => 'diesel'])
        ->and(Place::find($place['id'])->type)->toBe('customer_site');

    // Then the trip is planned with them, as from the lists.
    $this->post(route('trips.store'), ['trip_date' => '2026-10-26', 'load_type' => 'ftl', 'trip_kind' => 'loaded', 'truck_source' => 'own', 'truck_id' => $truck['id'], 'driver_id' => $driver['id'],
        'from_place_id' => $place['id'], 'to_place_id' => $w['bom']->id, 'planned_km' => 1200,
        'load' => ['billing_party_id' => $client['id'], 'material' => 'cement', 'actual_weight_mt' => 10, 'freight_basis' => 'trip', 'rate' => 45000, 'payment_type' => 'to_be_billed', 'gst_payable_by' => 'consignor']])
        ->assertSessionHasNoErrors();

    // Each "+ New" needs the right to add that kind of record.
    signInTo($w['company'], 'viewer');
    $this->get(route('trips.index'))->assertOk();
    $this->postJson(route('quick.client'), ['name' => 'Someone'])->assertForbidden();
    $this->postJson(route('quick.place'), ['name' => 'Somewhere'])->assertForbidden();
});

it('filters the trips by billing and reports the filtered list as a page, a PDF and a spreadsheet', function () {
    config(['documents.pdf_driver' => 'browser']);
    $w = invWorld(loads: 3);
    [$one, $two, $three] = $w['loads'];
    app(CompanyContext::class)->set($w['company']);
    [$n1, $n3] = [$one->trip->trip_number, $three->trip->trip_number];

    // LR 1 billed and paid in full; LR 2 billed, unpaid; LR 3 not billed.
    foreach ([$one, $two] as $load) {
        $this->post(route('invoices.store'), ['party_id' => $w['client']->id, 'loads' => [$load->id]])->assertSessionHasNoErrors();
        app(CompanyContext::class)->set($w['company']);
        $this->post(route('invoices.finalise', Invoice::latest('id')->first()))->assertSessionHasNoErrors();
    }
    app(CompanyContext::class)->set($w['company']);
    $first = Invoice::orderBy('id')->first();
    $this->post(route('receipts.store'), ['party_id' => $w['client']->id, 'receipt_date' => '2026-10-25', 'amount' => (float) $first->total, 'mode' => 'neft', 'reference' => 'UTR1',
        'narration' => 'Full payment', 'allocation' => 'manual', 'apply' => [$first->id => (float) $first->total]])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect($first->fresh()->status)->toBe('paid');

    $show = fn ($load) => route('trips.show', $load->trip_id);
    $this->get(route('trips.index', ['status' => 'all', 'billing' => 'not_invoiced']))->assertOk()->assertSee($show($three), false)->assertDontSee($show($one), false)->assertDontSee($show($two), false);
    $this->get(route('trips.index', ['status' => 'all', 'billing' => 'invoiced']))->assertSee($show($two), false)->assertDontSee($show($one), false)->assertDontSee($show($three), false);
    $this->get(route('trips.index', ['status' => 'all', 'billing' => 'paid']))->assertSee($show($one), false)->assertDontSee($show($two), false)
        ->assertSee(route('trips.report', ['status' => 'all', 'billing' => 'paid']));

    // The report: the letterhead, the filters in words, one row per trip and the totals.
    $this->get(route('trips.report', ['status' => 'all', 'billing' => 'not_invoiced']))->assertOk()->assertSeeText('Trip report')->assertSeeText('Not invoiced')
        ->assertSeeText($n3)->assertDontSeeText($n1)->assertSeeText('Kochi → Mumbai')->assertSeeText('Driver 3')
        ->assertSeeText('ACC Limited')->assertSeeText('1 trip')->assertSeeText('₹64,000.00');
    $this->get(route('trips.report', ['status' => 'all', 'format' => 'pdf']))->assertRedirect(route('trips.report', ['status' => 'all']));

    // The spreadsheet: a header row and one row per trip.
    $csv = $this->get(route('trips.report', ['status' => 'all', 'format' => 'csv']))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->streamedContent();
    $rows = array_map('str_getcsv', explode("\n", trim(str_replace("\xEF\xBB\xBF", '', $csv))));
    expect($rows[0])->toBe(['Date', 'Trip no.', 'Route', 'Truck', 'Driver', 'Client', 'Freight', 'Other charges', 'Total', 'Office note'])
        ->and($rows)->toHaveCount(4)->and($rows[1][2])->toBe('Kochi → Mumbai')->and((float) $rows[1][8])->toBe(64000.0);
});

it('shows the driver in the annexure of a consolidated bill when the design says so', function () {
    $w = invWorld(loads: 2);
    $this->post(route('invoices.store'), ['party_id' => $w['client']->id, 'loads' => collect($w['loads'])->pluck('id')->all()])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $bill = Invoice::latest('id')->first();

    // Off by default: the annexure has the LR count and no driver.
    $this->get(route('invoices.print', $bill))->assertOk()->assertSeeText('Total trips / LRs: 2')->assertSeeText('Subtotal (2 LRs)')->assertDontSeeText('Driver 1');

    // Switched on in the template.
    $this->post(route('settings.templates.store'), ['family' => 'invoice', 'theme' => 'gst_classic', 'name' => 'Our bill'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $template = DocumentTemplate::where('name', 'Our bill')->firstOrFail();
    $design = $template->designNormalized();
    $design['fields'] = array_fill_keys(array_keys(DesignSchema::FIELDS['invoice']), 1);
    $this->put(route('settings.templates.update', $template), ['name' => 'Our bill', 'design' => $design])->assertSessionHasNoErrors();
    $this->get(route('invoices.print', $bill))->assertOk()->assertSeeText('Driver 1')->assertSeeText('Driver 2');
});
