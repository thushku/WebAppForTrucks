<?php

use App\Modules\Invoicing\Models\DocumentDelivery;
use App\Modules\Invoicing\Models\Quotation;
use App\Modules\Parties\Models\RateContract;
use App\Modules\Parties\Services\RateFinder;
use App\Modules\Settings\Models\Place;
use App\Modules\Trips\Models\Consignment;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;

// Quotations (M21): written as a draft, sent with the next QT/ number and
// never changed after, printed, emailed and shared; the client's answer;
// an accepted one becomes the rate contract and starts trips at its rates.
// And the payment voucher, printed to sign.

it('writes a quotation, sends it with its number, prints, emails and shares it, and never changes it after', function () {
    config(['mail.default' => 'array', 'documents.pdf_driver' => 'browser']);
    app()->forgetInstance('mail.manager');
    app()->forgetInstance('mailer');
    $w = qtWorld();

    $this->get(route('quotations.create'))->assertOk()->assertSeeText('Routes and rates');
    $this->post(route('quotations.store'), qtForm($w, ['lines' => [1 => ['to_place_id' => '']]]))->assertSessionHasErrors('lines.1.to_place_id');
    $this->post(route('quotations.store'), qtForm($w, ['valid_until' => '2026-10-01']))->assertSessionHasErrors('valid_until');
    $this->post(route('quotations.store'), qtForm($w))->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $q = Quotation::with('lines')->sole();
    expect([$q->status, $q->number, $q->lines->count(), (float) $q->lines[0]->rate, $q->lines[0]->basis, $q->currency])->toBe(['draft', null, 2, 3400.0, 'tonne', 'INR']);
    $this->get(route('quotations.print', $q))->assertOk()->assertSeeText('Draft — not sent');

    // Sent: it gets its number and is fixed.
    $this->post(route('quotations.issue', $q))->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $q = $q->fresh();
    expect([$q->status, $q->number])->toBe(['issued', 'QT/26-27/0001']);
    $this->get(route('quotations.edit', $q))->assertRedirect(route('quotations.show', $q));
    $this->put(route('quotations.update', $q), qtForm($w))->assertSessionHasErrors('quotation');

    // On the letterhead: the routes, the rates and their basis, detention, the tax note and the terms.
    $this->get(route('quotations.print', $q))->assertOk()->assertSeeText('Quotation')->assertSeeText('QT/26-27/0001')->assertSeeText('Valid until 09 Nov 2026')
        ->assertSeeText('ACC Limited')->assertSeeText('Cement House')->assertSeeText('Kochi → Mumbai')->assertSeeText('Tarpaulin included')
        ->assertSeeText('₹3,400.00')->assertSeeText('per tonne')->assertSeeText('9 MT')->assertSeeText('₹2,500.00 a day after 24 free hours')
        ->assertSeeText('Kochi → Pune')->assertSeeText('₹61,000.00')->assertSeeText('GST is charged on the bill')->assertSeeText('Payment within 30 days.')
        ->assertSeeText('Accepted by the client')->assertDontSeeText('They asked for a Pune rate too');
    $this->get(route('quotations.pdf', $q))->assertRedirect(route('quotations.print', $q));

    // By email with the company's words, kept as a delivery; and the WhatsApp text.
    $this->get(route('quotations.show', $q))->assertOk()->assertSee('value="logistics@acc.test"', false)->assertSee(e('Quotation QT/26-27/0001 from Sharma Roadlines'), false);
    $this->post(route('quotations.email', $q), ['to_email' => 'logistics@acc.test', 'subject' => 'Our rates', 'body' => 'Attached.'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $sent = collect(app('mailer')->getSymfonyTransport()->messages())->map(fn ($m) => $m->getOriginalMessage());
    expect($sent)->toHaveCount(1)->and($sent[0]->getAttachments()[0]->getFilename())->toBe('Quotation-QT-26-27-0001.html')
        ->and(DocumentDelivery::where('document_type', 'quotation')->where('document_id', $q->id)->value('status'))->toBe('sent');
    $to = $this->get(route('quotations.whatsapp', $q))->headers->get('Location');
    expect($to)->toStartWith('https://wa.me/919820000101?text=')
        ->and(rawurldecode($to))->toContain('Quotation QT/26-27/0001 from Sharma Roadlines, valid until 09 Nov 2026.');

    // Past its date it shows as expired; a copy is a new draft dated today.
    $this->travelTo(CarbonImmutable::parse('2026-11-12 10:00', 'Asia/Kolkata'));
    $this->get(route('quotations.index'))->assertOk()->assertSeeText('Expired');
    $this->post(route('quotations.copy', $q))->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $copy = Quotation::with('lines')->latest('id')->first();
    expect([$copy->status, $copy->quote_date->toDateString(), $copy->valid_until->toDateString(), $copy->lines->count()])->toBe(['draft', '2026-11-12', '2026-11-27', 2]);

    // A viewer reads quotations but writes none.
    signInTo($w['company'], 'viewer');
    $this->get(route('quotations.show', $q))->assertOk();
    $this->post(route('quotations.store'), qtForm($w))->assertForbidden();
});

it('turns an accepted quotation into the client’s rate contract and starts a trip at its rate', function () {
    $w = qtWorld();
    $q = qtSent($w);

    $this->post(route('quotations.decide', $q), ['decision' => 'declined'])->assertSessionHasErrors('note');
    $this->post(route('quotations.contract', $q))->assertSessionHasErrors('quotation');
    $this->post(route('quotations.decide', $q), ['decision' => 'accepted', 'note' => 'PO 4512 of 26 Oct'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect($q->fresh()->only(['status', 'decision_note']))->toBe(['status' => 'accepted', 'decision_note' => 'PO 4512 of 26 Oct']);

    // The rate contract: a draft with the same routes, approved on the client's page as usual.
    $this->post(route('quotations.contract', $q))->assertRedirect();
    app(CompanyContext::class)->set($w['company']);
    $contract = RateContract::with('lines')->sole();
    expect([$contract->status, $contract->party_id, $contract->name, $contract->lines->count(), (float) $contract->lines[0]->rate, (float) $contract->lines[0]->min_weight_mt])
        ->toBe(['draft', $w['acc']->id, 'Quotation QT/26-27/0001', 2, 3400.0, 9.0])
        ->and($contract->payment_terms)->toContain('Payment within 30 days.')
        ->and($q->fresh()->rate_contract_id)->toBe($contract->id);
    $this->post(route('quotations.contract', $q))->assertSessionHasErrors('quotation');
    $this->post(route('parties.rates.approve', [$w['acc'], $contract]))->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect((float) app(RateFinder::class)->find($w['acc']->id, $w['cok']->id, $w['bom']->id, 'cement', null, CarbonImmutable::parse('2026-10-26'))?->rate)->toBe(3400.0);

    // A trip from a route: the form comes filled in; the load remembers the quotation.
    $line = $q->lines()->first();
    $this->get(route('trips.create', ['quotation_line' => $line->id]))->assertOk()
        ->assertSee('name="load[quotation_id]" value="' . $q->id . '"', false)->assertSee('value="3400.00"', false);
    $this->post(route('trips.store'), [
        'trip_date' => '2026-10-26', 'load_type' => 'ftl', 'trip_kind' => 'loaded', 'truck_source' => 'own', 'truck_id' => $w['truck']->id, 'driver_id' => $w['driver']->id,
        'from_place_id' => $w['cok']->id, 'to_place_id' => $w['bom']->id, 'planned_km' => 1380,
        'load' => ['billing_party_id' => $w['acc']->id, 'material' => 'cement', 'actual_weight_mt' => '18', 'freight_basis' => 'tonne', 'rate' => '3400',
            'payment_type' => 'to_be_billed', 'gst_payable_by' => 'consignor', 'quotation_id' => $q->id],
    ])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect(Consignment::where('quotation_id', $q->id)->count())->toBe(1);
    $this->get(route('quotations.show', $q))->assertSeeText('Trips at these rates')->assertSeeText('Rate contract');

    // A declined or cancelled quotation starts no trip; cancelling needs a reason.
    $other = qtSent($w);
    $this->post(route('quotations.decide', $other), ['decision' => 'cancelled'])->assertSessionHasErrors('note');
    $this->post(route('quotations.decide', $other), ['decision' => 'cancelled', 'note' => 'Client closed the plant'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $this->get(route('trips.create', ['quotation_line' => $other->lines()->first()->id]))->assertOk()->assertDontSee('name="load[quotation_id]"', false);
});

it('prints a payment voucher to sign, with the payee, the amount in words and through which bank', function () {
    $w = qtWorld();
    app(CompanyContext::class)->set($w['company']);
    $ledger = app(\App\Modules\Accounts\Services\ChartOfAccounts::class)->party($w['acc']);
    $this->post(route('accounts.payments.store'), ['ledger_account_id' => $ledger->id, 'amount' => 12500, 'paid_on' => '2026-10-25', 'mode' => 'bank', 'reference' => 'UTR 88123', 'narration' => 'Refund of excess freight'])
        ->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $voucher = \App\Modules\Accounts\Models\Voucher::where('voucher_type', 'payment')->sole();

    $this->get(route('accounts.vouchers.show', $voucher))->assertSeeText('Print to sign');
    $this->get(route('accounts.vouchers.print', $voucher))->assertOk()->assertSeeText('Payment voucher')->assertSeeText($voucher->number)
        ->assertSeeText('Paid to')->assertSeeText('ACC Limited')->assertSeeText('₹12,500.00')->assertSeeText('Rupees twelve thousand five hundred only')
        ->assertSeeText('UTR 88123')->assertSeeText('Through: Bank')->assertSeeText('Received by — signature')->assertSeeText('Prepared by');
});
