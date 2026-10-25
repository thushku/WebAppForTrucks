<?php

use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\PartyBalance;
use App\Modules\Invoicing\Models\Receipt;
use App\Modules\Invoicing\Services\PdfRenderer;
use App\Modules\Parties\Models\PartyTaxId;
use App\Modules\Parties\Services\TaxIds;
use App\Modules\Settings\Models\CompanyBankAccount;
use App\Modules\Settings\Models\Place;
use App\Modules\Tax\Models\TaxRegistration;
use App\Modules\Tax\Models\TaxRegistrationYear;
use App\Modules\Trips\Models\Consignment;
use App\Modules\Trips\Services\ConsignmentService;
use App\Modules\Trips\Services\TripService;
use App\Platform\Audit\AuditLog;
use App\Platform\Tenancy\CompanyContext;

// Invoicing (M9): bills from LRs on the tax engine, issue, notes, receipts
// with TDS, the secure link, the printed documents.

it('bills a delivered LR: tax by the engine, issued with its number, the LR billed and locked', function () {
    $w = invWorld();
    $invoice = invDraft($w);
    expect($invoice->status)->toBe('draft')->and($invoice->lines()->count())->toBe(2);

    $this->get(route('invoices.edit', $invoice))->assertOk()->assertSee('Freight, LR LR/26-27/00001')->assertSeeText('CGST 9%')->assertSeeText('₹75,520.00');

    $this->post(route('invoices.finalise', $invoice))->assertSessionHasNoErrors()->assertRedirect(route('invoices.show', $invoice));
    app(CompanyContext::class)->set($w['company']);
    $invoice->refresh();
    // 62,900 + 1,100 = 64,000; CGST 5,760 + SGST 5,760.
    expect([$invoice->number, $invoice->status, (float) $invoice->tax_total, (float) $invoice->total, $invoice->kind])->toBe(['INV/26-27/00001', 'finalised', 11520.0, 75520.0, 'tax_invoice'])
        ->and($invoice->snapshot['seller']['tax_id'])->toBe($w['registration']->tax_id)
        ->and($invoice->public_token)->toHaveLength(40)
        ->and(Consignment::find($w['loads'][0]->id))->toMatchArray(['invoice_id' => $invoice->id, 'status' => 'billed'])
        ->and((float) PartyBalance::where('party_id', $w['client']->id)->value('outstanding'))->toBe(75520.0)
        ->and(AuditLog::where('action', 'invoice.finalised')->exists())->toBeTrue();

    // Never edited again: not by a screen, not by code.
    expect(fn () => $invoice->forceFill(['total' => 1])->save())->toThrow(RuntimeException::class, 'A finalised invoice is never edited');
    expect(fn () => ConsignmentService::assertNotBilled(Consignment::find($w['loads'][0]->id)))->toThrow(Illuminate\Validation\ValidationException::class);

    $this->get(route('invoices.print', $invoice))->assertOk()
        ->assertSeeText('Tax invoice')->assertSeeText('Original for recipient')->assertSeeText('Duplicate for supplier')
        ->assertSeeText($w['registration']->tax_id)->assertSeeText('Rupees seventy-five thousand five hundred and twenty only')
        ->assertSeeText('996791')->assertSeeText('Kerala (32)');
});

it('puts GTA freight under reverse charge: no GST charged, the notional IGST and “Reverse charge: Yes” printed', function () {
    $w = invWorld(gtaOption: null, clientState: '27');
    $invoice = invDraft($w);
    $this->post(route('invoices.finalise', $invoice))->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $invoice->refresh();

    expect([$invoice->reverse_charge, (float) $invoice->tax_total, (float) $invoice->tax_notional, (float) $invoice->total])->toBe([true, 0.0, 3200.0, 64000.0]);
    $this->get(route('invoices.print', $invoice))->assertSeeText('Tax payable on reverse charge: Yes')->assertSeeText('payable by the recipient (reverse charge)')->assertSeeText('Maharashtra (27)');
});

it('makes one consolidated bill with an LR annexure, and a bill without GST when not registered', function () {
    $w = invWorld(loads: 2);
    $invoice = invDraft($w);
    expect($invoice->lines()->where('service_class', 'FREIGHT_ROAD')->count())->toBe(2);
    $this->get(route('invoices.print', $invoice))->assertSeeText('Annexure to')->assertSeeText('Total trips / LRs: 2')->assertSeeText('LR/26-27/00002');

    // Billed from no registration: a bill of supply, no tax.
    app(CompanyContext::class)->set($w['company']);
    $this->put(route('invoices.update', $invoice), ['issue_date' => '2026-10-25', 'due_date' => '2026-11-24', 'discount_type' => 'none', 'tax_registration_id' => '',
        'lines' => $invoice->lines()->get()->map(fn ($l) => ['id' => $l->id, 'service_class' => $l->service_class, 'description' => $l->description, 'quantity' => 1, 'unit_rate' => (float) $l->unit_rate])->all()])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect([$invoice->fresh()->kind, (float) $invoice->fresh()->tax_total])->toBe(['bill_of_supply', 0.0]);
});

it('keeps a draft the engine cannot decide, and refuses to issue it', function () {
    $w = invWorld(gtaOption: 'fcm_18', clientState: null);
    app(CompanyContext::class)->runAs($w['company'], fn () => Place::query()->update(['state_code' => null]));
    $invoice = invDraft($w);

    $this->get(route('invoices.edit', $invoice))->assertSeeText('Place of supply unknown');
    $this->post(route('invoices.finalise', $invoice))->assertSessionHasErrors('tax');
    app(CompanyContext::class)->set($w['company']);
    expect($invoice->fresh()->status)->toBe('draft')->and($invoice->fresh()->number)->toBeNull();
});

it('records a receipt with TDS and a deduction over two bills, oldest first, and voids it', function () {
    $w = invWorld(loads: 2, client: ['tds_deducted' => true]);
    $first  = invDraft($w, [$w['loads'][0]->id]);
    $this->post(route('invoices.finalise', $first));
    $second = invDraft($w, [$w['loads'][1]->id]);
    $this->post(route('invoices.finalise', $second));
    app(CompanyContext::class)->set($w['company']);
    expect((float) $first->fresh()->tds_expected)->toBe(1280.0);       // 2% of 64,000

    // 75,520 + 75,520 = 1,51,040 settled by 1,48,000 cash + 2,560 TDS + 480 shortage.
    $this->post(route('receipts.store'), ['party_id' => $w['client']->id, 'receipt_date' => '2026-10-25', 'amount' => 148000, 'tds_amount' => 2560, 'tds_section' => '393(1)',
        'deduction_amount' => 480, 'deduction_note' => 'Shortage 4 bags', 'mode' => 'neft', 'reference' => 'UTR8812', 'narration' => 'October bills', 'allocation' => 'oldest'])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($w['company']);
    $receipt = Receipt::first();
    expect($receipt->number)->toBe('RCT/26-27/00001')->and($first->fresh()->status)->toBe('paid')->and($second->fresh()->status)->toBe('paid')
        ->and($receipt->allocations()->sum('tds'))->toEqual(2560)
        ->and((float) $receipt->allocations()->where('invoice_id', $first->id)->value('tds'))->toBe(1280.0)
        ->and((float) PartyBalance::where('party_id', $w['client']->id)->value('outstanding'))->toBe(0.0);

    $this->get(route('receipts.print', $receipt))->assertSeeText('Money receipt')->assertSeeText('Shortage 4 bags')->assertSeeText('INV/26-27/00002');

    $this->post(route('receipts.void', $receipt), ['reason' => 'Entered twice'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect($first->fresh()->status)->toBe('finalised')->and((float) $first->fresh()->balance_due)->toBe(75520.0)
        ->and((float) PartyBalance::where('party_id', $w['client']->id)->value('outstanding'))->toBe(151040.0);
});

it('credits part of a bill, then voids the note and the bill; the LR can be billed again', function () {
    $w = invWorld();
    $invoice = invDraft($w);
    $this->post(route('invoices.finalise', $invoice));

    $this->post(route('invoices.against', $invoice), ['type' => 'credit_note'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $note = Invoice::where('doc_type', 'credit_note')->first();
    $freight = $note->lines()->where('service_class', 'FREIGHT_ROAD')->first();

    // Credit the loading charge only; keep its tax from the bill.
    $this->put(route('invoices.update', $note), ['issue_date' => '2026-10-25', 'due_date' => '2026-10-25', 'discount_type' => 'none', 'reason' => 'Loading done by the client',
        'lines' => [['id' => $note->lines()->where('service_class', 'LOADING_UNLOADING')->value('id'), 'service_class' => 'LOADING_UNLOADING', 'description' => 'Loading charges', 'quantity' => 1, 'unit_rate' => 1100]]])->assertSessionHasNoErrors();
    $this->post(route('invoices.finalise', $note))->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $note->refresh();
    expect([$note->number, (float) $note->total])->toBe(['CN/26-27/0001', 1298.0])
        ->and((float) $invoice->fresh()->balance_due)->toBe(74222.0);

    // The bill cannot go while its note stands.
    $this->post(route('invoices.void', $invoice), ['reason' => 'Wrong client billed'])->assertSessionHasErrors('invoice');
    $this->post(route('invoices.void', $note), ['reason' => 'Raised by mistake'])->assertSessionHasNoErrors();
    $this->post(route('invoices.void', $invoice), ['reason' => 'Wrong client billed'])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($w['company']);
    expect($invoice->fresh()->status)->toBe('void')->and($invoice->fresh()->number)->toBe('INV/26-27/00001')
        ->and(Consignment::find($w['loads'][0]->id))->toMatchArray(['invoice_id' => null, 'status' => 'delivered'])
        ->and(AuditLog::where('action', 'invoice.voided')->value('severity'))->toBe('sensitive');
});

it('opens a bill by its secure link without signing in, and marks it viewed', function () {
    $w = invWorld();
    $invoice = invDraft($w);
    $this->post(route('invoices.finalise', $invoice));
    app(CompanyContext::class)->set($w['company']);
    $token = $invoice->fresh()->public_token;

    auth()->logout();
    app(CompanyContext::class)->forget();
    $this->get('/d/' . $token)->assertOk()->assertSeeText('INV/26-27/00001')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->get('/d/' . str_repeat('A', 40))->assertNotFound();

    app(CompanyContext::class)->set($w['company']);
    expect($invoice->fresh()->status)->toBe('viewed');
});

it('prints an LR in its four copies, without the freight on the driver’s copy', function () {
    $w = invWorld();
    $c = $w['loads'][0];

    $html = $this->get(route('trips.loads.lr', [$c->trip_id, $c]))->assertOk()->getContent();
    expect(substr_count($html, 'class="sheet"'))->toBe(4)
        ->and($html)->toContain('Consignor copy')->toContain('Driver / lorry copy')->toContain('Proof of delivery')
        ->and(substr_count($html, '₹62,900.00'))->toBe(3);
});

it('keeps bank numbers encrypted and prints the UPI QR for the amount due', function () {
    $w = invWorld();
    $this->post(route('settings.banks.store'), ['label' => 'HDFC current', 'bank_name' => 'HDFC Bank', 'holder_name' => 'Sharma Roadlines Pvt Ltd',
        'account_number' => '50200012345678', 'ifsc' => 'HDFC0001234', 'upi_id' => 'sharmaroadlines@hdfcbank'])->assertSessionHasNoErrors()->assertRedirect();
    app(CompanyContext::class)->set($w['company']);
    expect(str_contains((string) Illuminate\Support\Facades\DB::table('company_bank_accounts')->value('account_number'), '50200012345678'))->toBeFalse()
        ->and(CompanyBankAccount::first()->masked())->toBe('•••• 5678');

    $invoice = invDraft($w);
    $this->post(route('invoices.finalise', $invoice));
    $this->get(route('invoices.print', $invoice))->assertSeeText('50200012345678')->assertSeeText('HDFC0001234')->assertSeeText('Scan to pay')->assertSee('<svg', false);
});

it('downloads the bill as a PDF printed by the server’s Chromium', function () {
    PdfRenderer::reset();
    if (! PdfRenderer::available()) {
        $this->markTestSkipped('No Chromium on this machine.');
    }
    $w = invWorld();
    $invoice = invDraft($w);
    $this->post(route('invoices.finalise', $invoice));

    $response = $this->get(route('invoices.pdf', $invoice))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(substr($response->getContent(), 0, 5))->toBe('%PDF-')
        ->and($response->headers->get('Content-Disposition'))->toContain('Invoice-INV-26-27-00001.pdf');
});

it('lets a viewer read bills but not make or issue them', function () {
    $w = invWorld();
    $invoice = invDraft($w);
    signInTo($w['company'], 'viewer');

    $this->get(route('invoices.index'))->assertOk();
    $this->get(route('invoices.create'))->assertForbidden();
    $this->post(route('invoices.finalise', $invoice))->assertForbidden();
    $this->get(route('receipts.create'))->assertForbidden();
});
