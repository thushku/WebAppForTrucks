<?php

use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\Quotation;
use App\Platform\Notifications\UserNotification;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;

// The client's page as a billing desk (M24, 13-parity #20–21) and the
// quotation answered by the client on its secure link.

it('shows the client what is not billed, what is due and what came in, and bills and settles from there', function () {
    $w = invWorld(loads: 2);
    [$one, $two] = $w['loads'];
    $tab = route('parties.show', [$w['client'], 'tab' => 'billing']);

    $this->get($tab)->assertOk()->assertSeeText('Not billed yet')->assertSeeText('₹1,28,000.00')->assertSeeText('2 LRs waiting')
        ->assertSeeText($one->lr_number)->assertSeeText($two->lr_number)->assertSeeText('Make the bill for the ticked LRs')
        ->assertDontSeeText('Coming in a later milestone');

    // Tick one LR and bill it; issue it.
    $this->post(route('invoices.store'), ['party_id' => $w['client']->id, 'loads' => [$one->id]])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $bill = Invoice::latest('id')->first();
    $this->post(route('invoices.finalise', $bill))->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $bill = $bill->fresh();

    $this->get($tab)->assertSeeText('1 LR waiting')->assertDontSeeText($one->lr_number . ' ')->assertSeeText('Billed, not paid')->assertSeeText('₹75,520.00')
        ->assertSee(route('receipts.create', ['invoice' => $bill->id]), false)->assertSeeText($bill->number);

    // Settle: the receipt form comes filled for that bill; the money shows under Payments and Received this year.
    $this->get(route('receipts.create', ['invoice' => $bill->id]))->assertOk()->assertSee('Settlement for', false)->assertSee($bill->number, false);
    $this->post(route('receipts.store'), ['party_id' => $w['client']->id, 'receipt_date' => '2026-10-25', 'amount' => 50000, 'mode' => 'upi', 'reference' => 'UPI 4471',
        'narration' => 'Settlement for tax invoice ' . $bill->number, 'allocation' => 'manual', 'apply' => [$bill->id => 50000]])->assertSessionHasNoErrors();
    $this->get($tab)->assertSeeText('Received this year')->assertSeeText('₹50,000.00')->assertSeeText('UPI 4471')->assertSeeText('₹25,520.00');

    // A viewer reads it but bills and settles nothing; without bills to see there is no Billing tab.
    signInTo($w['company'], 'viewer');
    $this->get($tab)->assertOk()->assertSeeText('Not billed yet')->assertDontSeeText('Make the bill for the ticked LRs')->assertDontSee(route('receipts.create', ['invoice' => $bill->id]), false);
    signInTo($w['company'], 'workshop');
    $this->get(route('parties.show', $w['client']))->assertForbidden();
});

it('lets the client accept a sent quotation on its link, and tells the office', function () {
    $w = qtWorld();
    $q = qtSent($w);
    expect($q->public_token)->not->toBeNull();
    $link = route('documents.public', $q->public_token);

    // The email and WhatsApp words carry the link.
    app(CompanyContext::class)->set($w['company']);
    expect(app(\App\Modules\Invoicing\Services\Messages::class)->renderQuotation('email', $q)['body'])->toContain($link);

    // The client, not signed in, reads it and sees the answer form.
    auth()->guard('web')->logout();
    $this->get($link)->assertOk()->assertSeeText('Quotation')->assertSeeText('Kochi → Mumbai')->assertSeeText('Accept the quotation')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    app(CompanyContext::class)->set($w['company']);
    expect($q->fresh()->viewed_at)->not->toBeNull();

    $answer = route('documents.public.answer', $q->public_token);
    $this->post($answer, ['decision' => 'declined', 'name' => 'Meena Iyer'])->assertSessionHasErrors('note');
    $this->post($answer, ['decision' => 'accepted', 'name' => 'Meena Iyer', 'note' => 'PO 4512'])->assertRedirect($link);
    $this->get($link)->assertSeeText('Thank you — we have your acceptance')->assertDontSeeText('Accept the quotation');
    app(CompanyContext::class)->set($w['company']);
    $q = $q->fresh();
    expect([$q->status, $q->answered_by_name, $q->decision_note, $q->decided_by])->toBe(['accepted', 'Meena Iyer', 'PO 4512', null])
        ->and(UserNotification::for($w['owner'])->where('kind', 'quotation.accepted')->value('title'))->toBe('ACC Limited accepted quotation QT/26-27/0001')
        ->and(UserNotification::for($w['owner'])->where('kind', 'quotation.accepted')->value('body'))->toBe('Meena Iyer — PO 4512')
        ->and(\App\Platform\Audit\AuditLog::where('action', 'quotation.accepted')->exists())->toBeTrue();

    // Answered once; the link of a quotation past its date takes no answer.
    $this->post($answer, ['decision' => 'declined', 'name' => 'Someone', 'note' => 'Changed my mind'])->assertSessionHasErrors('decision');
    $this->actingAs($w['owner'], 'web')->withSession([\App\Http\Middleware\ResolveCompany::SESSION_KEY => $w['company']->id]);
    $other = qtSent($w);
    $this->get(route('quotations.show', $q))->assertSeeText('Answered on the client link by Meena Iyer')->assertSeeText('Client link:');
    $this->travelTo(CarbonImmutable::parse('2026-11-12 10:00', 'Asia/Kolkata'));
    auth()->guard('web')->logout();
    $this->get(route('documents.public', $other->public_token))->assertOk()->assertDontSeeText('Accept the quotation');
    $this->post(route('documents.public.answer', $other->public_token), ['decision' => 'accepted', 'name' => 'Meena Iyer'])->assertSessionHasErrors('decision');
    app(CompanyContext::class)->set($w['company']);
    expect(Quotation::find($other->id)->status)->toBe('issued');
});
