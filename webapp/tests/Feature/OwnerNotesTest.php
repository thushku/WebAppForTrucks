<?php

use App\Modules\Invoicing\Models\Invoice;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Str;

// Credit and debit notes from the owner app (M43): against an issued bill,
// with their reason, the bill's tax worked out by the engine, issued at once
// when asked — once per Idempotency-Key.

it('credits part of a bill and charges detention from the owner app', function () {
    $w = invWorld();
    $invoice = invDraft($w);
    $this->post(route('invoices.finalise', $invoice))->assertSessionHasNoErrors();
    phoneReset();
    $this->withHeaders(['Authorization' => 'Bearer ' . $w['owner']->createToken('owner:android')->plainTextToken, 'X-Company' => $w['company']->uuid, 'Accept' => 'application/json']);
    $url = '/api/v1/owner/invoices/' . $invoice->id . '/notes';
    $send = fn (array $body) => $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson($url, $body);

    // ₹5,000 off the freight before tax: 18 % on it as on the bill.
    $key = (string) Str::uuid();
    $credit = ['type' => 'credit_note', 'amount' => 5000, 'reason' => 'Rate difference agreed with ACC', 'finalise' => true];
    $this->withHeader('Idempotency-Key', $key)->postJson($url, $credit)->assertCreated()->assertJsonPath('invoice.number', 'CN/26-27/0001')
        ->assertJsonPath('invoice.total', 5900)->assertJsonPath('invoice.against', 'INV/26-27/00001')->assertJsonPath('invoice.reason', 'Rate difference agreed with ACC');
    $this->withHeader('Idempotency-Key', $key)->postJson($url, $credit)->assertJsonPath('replayed', true);
    app(CompanyContext::class)->set($w['company']);
    expect((float) $invoice->fresh()->balance_due)->toBe(69620.0);

    // A credit of the whole bill now is more than is left to credit.
    $send(['type' => 'credit_note', 'reason' => 'Wrong client', 'finalise' => true])->assertStatus(422);
    // A debit note needs its lines; with them it is issued with its own number.
    $send(['type' => 'debit_note', 'reason' => 'Detention'])->assertStatus(422)->assertJsonPath('message', 'Give the debit note’s lines.');
    $debit = $send(['type' => 'debit_note', 'reason' => 'Detention at the ACC depot', 'finalise' => true,
        'lines' => [['description' => 'Detention, 2 days', 'amount' => 2000, 'service_class' => 'DETENTION']]])->assertCreated()->json('invoice');
    expect($debit['type'])->toBe('debit_note')->and($debit['number'])->toStartWith('DN/')->and($debit['total'])->toBeGreaterThanOrEqual(2000);

    // A draft left on the web to finish.
    $draft = $send(['type' => 'debit_note', 'reason' => 'Unloading at Bhiwandi', 'lines' => [['description' => 'Unloading', 'amount' => 800]]])->assertCreated()->json('invoice');
    expect($draft['status'])->toBe('draft')->and($draft['number'])->toBeNull();
    app(CompanyContext::class)->set($w['company']);
    expect(Invoice::whereKey($draft['id'])->value('related_invoice_id'))->toBe($invoice->id);
});
