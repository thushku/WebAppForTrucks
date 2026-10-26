<?php

use App\Modules\Trips\Models\Consignment;
use App\Modules\Trips\Models\LrBook;
use App\Modules\Trips\Models\Trip;
use App\Platform\Tenancy\CompanyContext;

// Pre-printed LR books (M21, blueprint 10 §3): the register, a typed LR
// number taken only from a free leaf of a book in use, spoilt and lost
// leaves, the gaps report, and the automatic series for everything else.

/** A trip form for qtWorld()'s company, with the load's LR number typed (or not). */
function lrbTrip(array $w, ?string $lr, string $date = '2026-10-25'): array
{
    return [
        'trip_date' => $date, 'load_type' => 'ftl', 'trip_kind' => 'loaded', 'truck_source' => 'own', 'truck_id' => $w['truck']->id, 'driver_id' => $w['driver']->id,
        'from_place_id' => $w['cok']->id, 'to_place_id' => $w['bom']->id, 'planned_km' => 1380,
        'load' => ['billing_party_id' => $w['acc']->id, 'material' => 'cement', 'actual_weight_mt' => '18', 'freight_basis' => 'tonne', 'rate' => '3400',
            'payment_type' => 'to_be_billed', 'gst_payable_by' => 'consignor', 'lr_number' => $lr],
    ];
}

it('keeps the register of LR books, takes a typed LR number only from a free leaf, and reports the gaps', function () {
    $w = qtWorld();

    // No book yet: the form has no LR field, and loads get the automatic series.
    $this->get(route('trips.create'))->assertOk()->assertDontSee('name="load[lr_number]"', false);
    $this->get(route('lr-books.index'))->assertOk()->assertSeeText('No LR books');

    $book = ['book_no' => '12', 'prefix' => 'a-', 'first_no' => 1001, 'last_no' => 1050, 'issued_to' => 'Aluva agent', 'issued_on' => '2026-10-20'];
    $this->post(route('lr-books.store'), ['last_no' => 1000] + $book)->assertSessionHasErrors('last_no');
    $this->post(route('lr-books.store'), $book)->assertSessionHasNoErrors();
    $this->post(route('lr-books.store'), ['book_no' => '13', 'first_no' => 1040, 'last_no' => 1090] + $book)->assertSessionHasErrors('first_no');
    $this->post(route('lr-books.store'), $book)->assertSessionHasErrors('book_no');
    app(CompanyContext::class)->set($w['company']);
    $lrBook = LrBook::sole();
    expect([$lrBook->prefix, $lrBook->size(), $lrBook->rangeLabel()])->toBe(['A-', 50, 'A-1001 – A-1050']);

    // A typed number: from the book, written any way, kept as the book prints it.
    $this->get(route('trips.create'))->assertSee('name="load[lr_number]"', false);
    $this->post(route('trips.store'), lrbTrip($w, 'A-2001'))->assertSessionHasErrors(['load.lr_number' => 'LR A-2001 is not a leaf of any LR book. Add the book under Invoices & LR → LR books, or leave the number empty for the next automatic one.']);
    $this->post(route('trips.store'), lrbTrip($w, ' a-01005 '))->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $first = Consignment::latest('id')->first();
    expect([$first->lr_number, $first->lr_book_id, $first->lr_date->toDateString()])->toBe(['A-1005', $lrBook->id, '2026-10-25']);

    // Sending the trip keeps the typed number; a second use of the leaf is refused.
    app(\App\Modules\Trips\Services\TripService::class)->assign(Trip::find($first->trip_id));
    expect($first->fresh()->lr_number)->toBe('A-1005');
    $this->post(route('trips.store'), lrbTrip($w, 'A-1005', '2026-10-27'))->assertSessionHasErrors('load.lr_number');

    // Spoilt and lost leaves never carry a load; a used leaf cannot be marked.
    $this->post(route('lr-books.mark', $lrBook), ['leaf_no' => 1003, 'status' => 'lost', 'reason' => 'Torn out, not found'])->assertSessionHasNoErrors();
    $this->post(route('lr-books.mark', $lrBook), ['leaf_no' => 1002, 'status' => 'cancelled', 'reason' => 'Written wrong'])->assertSessionHasNoErrors();
    $this->post(route('lr-books.mark', $lrBook), ['leaf_no' => 1005, 'status' => 'lost', 'reason' => 'Test'])->assertSessionHasErrors('leaf_no');
    $this->post(route('lr-books.mark', $lrBook), ['leaf_no' => 999, 'status' => 'lost', 'reason' => 'Test'])->assertSessionHasErrors('leaf_no');
    $this->post(route('trips.store'), lrbTrip($w, 'A-1003', '2026-10-27'))->assertSessionHasErrors(['load.lr_number' => 'Leaf A-1003 was marked lost: Torn out, not found']);

    // Empty: the automatic series, as before.
    $this->post(route('trips.store'), lrbTrip($w, '', '2026-10-28'))->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $auto = Consignment::latest('id')->first();
    expect([$auto->lr_number, $auto->lr_book_id])->toBe([null, null]);

    // The book's page: what became of each leaf, and the leaves skipped below the last used one.
    $this->get(route('lr-books.show', $lrBook))->assertOk()->assertSeeText('LR book 12')->assertSeeText('A-1001 – A-1050')
        ->assertSeeText('Leaves skipped so far: A-1001, A-1004.');
    app(CompanyContext::class)->set($w['company']);
    $report = app(\App\Modules\Trips\Services\LrBooks::class)->report($lrBook);
    expect([$report['used'], $report['cancelled'], $report['lost'], $report['free']])->toBe([1, 1, 1, 47])
        ->and($report['gaps']->all())->toBe(['A-1001', 'A-1004']);

    // A leaf marked by mistake is freed again; a closed book gives no more LRs.
    $lost = $lrBook->leaves()->where('leaf_no', 1003)->first();
    $this->delete(route('lr-books.unmark', [$lrBook, $lost->id]))->assertSessionHasNoErrors();
    $this->post(route('lr-books.status', $lrBook), ['status' => 'closed'])->assertSessionHasNoErrors();
    $this->post(route('trips.store'), lrbTrip($w, 'A-1003', '2026-10-27'))->assertSessionHasErrors(['load.lr_number' => 'LR book 12 is closed.']);
    app(CompanyContext::class)->set($w['company']);
    expect(\App\Platform\Audit\AuditLog::where('action', 'like', 'lr_book.%')->pluck('action')->all())
        ->toContain('lr_book.added', 'lr_book.leaf_lost', 'lr_book.leaf_cancelled', 'lr_book.leaf_restored');

    // A viewer sees the books but keeps none.
    signInTo($w['company'], 'viewer');
    $this->get(route('lr-books.show', $lrBook))->assertOk();
    $this->post(route('lr-books.store'), ['book_no' => '14'] + $book)->assertForbidden();
});

it('takes a typed LR on a load added to a sent trip, and never changes an LR’s number', function () {
    $w = qtWorld();
    $this->post(route('lr-books.store'), ['book_no' => 'B7', 'prefix' => '', 'first_no' => 501, 'last_no' => 600, 'issued_on' => '2026-10-20'])->assertSessionHasNoErrors();
    $this->post(route('trips.store'), lrbTrip($w, null))->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $trip = Trip::sole();
    $load = Consignment::sole();
    app(\App\Modules\Trips\Services\TripService::class)->assign($trip->fresh());
    expect($load->fresh()->lr_number)->toBe('LR/26-27/00001');

    // The office tries to put a book leaf on an LR that has its number: refused.
    $form = ['billing_party_id' => $w['acc']->id, 'material' => 'cement', 'actual_weight_mt' => '18', 'freight_basis' => 'tonne', 'rate' => '3400',
        'payment_type' => 'to_be_billed', 'gst_payable_by' => 'consignor', 'pickup_stop_id' => $trip->stops()->where('seq', 1)->value('id'), 'drop_stop_id' => $trip->stops()->where('seq', 2)->value('id')];
    $this->put(route('trips.loads.update', [$trip, $load]), $form + ['lr_number' => '555'])->assertSessionHasErrors(['lr_number' => 'An LR keeps its number. Cancel this LR and add the load again to use another leaf.']);

    // A part load added to the sent trip takes the typed leaf instead of the next automatic number.
    $this->post(route('trips.loads.store', $trip), $form + ['lr_number' => '0555'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect(Consignment::latest('id')->first()->only(['lr_number', 'lr_book_id']))->toBe(['lr_number' => '555', 'lr_book_id' => LrBook::sole()->id]);
});
