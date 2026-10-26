<?php

use App\Modules\Accounts\Models\LedgerAccount;
use App\Modules\Accounts\Models\Voucher;
use App\Modules\Invoicing\Models\Quotation;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Str;

// The owner app's quotations and payments (M31): write, send (number, link,
// WhatsApp words), answer and copy a quotation; pay a vendor or expense.

/** The owner app signed in as the world's owner. */
function odPhone(array $w, ?\App\Models\Account $as = null): void
{
    phoneReset();
    test()->withHeaders(['Authorization' => 'Bearer ' . ($as ?? $w['owner'])->createToken('owner:android')->plainTextToken, 'X-Company' => $w['company']->uuid, 'Accept' => 'application/json']);
}

it('writes, sends, answers and copies a quotation from the phone', function () {
    $w = qtWorld();
    odPhone($w);
    $lines = [['from_place_id' => $w['cok']->id, 'to_place_id' => $w['bom']->id, 'basis' => 'tonne', 'rate' => 3400, 'min_weight_mt' => 9]];

    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/owner/quotations', ['party_id' => $w['acc']->id, 'lines' => [['from_place_id' => $w['cok']->id, 'rate' => 3400]]])
        ->assertStatus(422)->assertJsonValidationErrors('lines.0.to_place_id');
    $key = (string) Str::uuid();
    $draft = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/owner/quotations', ['party_id' => $w['acc']->id, 'subject' => 'Cement, November', 'lines' => $lines])
        ->assertCreated()->json('quotation');
    expect([$draft['status'], $draft['number'], $draft['quote_date'], $draft['valid_until'], $draft['share_url']])->toBe(['draft', null, '2026-10-25', '2026-11-09', null])
        ->and($draft['terms'])->toContain('Detention')->and($draft['lines'][0]['to']['name'])->toBe('Mumbai')->and($draft['lines'][0]['rate'])->toEqual(3400);
    $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/owner/quotations', ['party_id' => $w['acc']->id, 'lines' => $lines])->assertJsonPath('replayed', true);

    // The draft saved again with a second route; then sent: its number, the client's link and the words.
    $this->putJson('/api/v1/owner/quotations/' . $draft['id'], ['party_id' => $w['acc']->id, 'quote_date' => '2026-10-25', 'valid_until' => '2026-11-09',
        'lines' => array_merge($lines, [['from_place_id' => $w['cok']->id, 'to_place_id' => $w['pnq']->id, 'basis' => 'trip', 'rate' => 61000]])])->assertOk()->assertJsonCount(2, 'quotation.lines');
    $sent = $this->postJson('/api/v1/owner/quotations/' . $draft['id'] . '/issue')->assertOk()->json('quotation');
    expect([$sent['status'], $sent['number']])->toBe(['issued', 'QT/26-27/0001'])->and($sent['share_url'])->toContain('/d/')
        ->and($sent['whatsapp_text'])->toContain('QT/26-27/0001')->and($sent['whatsapp_text'])->toContain($sent['share_url']);
    $this->putJson('/api/v1/owner/quotations/' . $draft['id'], ['party_id' => $w['acc']->id, 'quote_date' => '2026-10-25', 'lines' => $lines])->assertStatus(422);

    // Lists by tab; the answer; a copy for a new offer.
    $this->getJson('/api/v1/owner/quotations')->assertOk()->assertJsonPath('quotations.0.number', 'QT/26-27/0001')->assertJsonPath('quotations.0.routes', 2);
    $this->postJson('/api/v1/owner/quotations/' . $draft['id'] . '/decide', ['decision' => 'cancelled'])->assertStatus(422)->assertJsonValidationErrors('note');
    $this->postJson('/api/v1/owner/quotations/' . $draft['id'] . '/decide', ['decision' => 'accepted', 'note' => 'PO 4512 by email'])->assertOk()->assertJsonPath('quotation.status', 'accepted');
    $this->getJson('/api/v1/owner/quotations?tab=accepted')->assertJsonCount(1, 'quotations');
    $copy = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/owner/quotations/' . $draft['id'] . '/copy')->assertCreated()->json('quotation');
    expect([$copy['status'], $copy['number'], $copy['routes']])->toBe(['draft', null, 2]);

    // A viewer reads them but makes none.
    $viewer = makeAccount(['name' => 'Viewer']);
    joinCompany($viewer, $w['company'], 'viewer');
    odPhone($w, $viewer);
    $this->getJson('/api/v1/owner/quotations/' . $draft['id'])->assertOk()->assertJsonPath('quotation.decision_note', 'PO 4512 by email');
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/owner/quotations', ['party_id' => $w['acc']->id, 'lines' => $lines])->assertForbidden();
});

it('pays a vendor or an expense from the phone, once per key', function () {
    $w = qtWorld();
    $pump = app(CompanyContext::class)->runAs($w['company'], fn () => makeParty(['name' => 'Highway Fuels', 'short_code' => 'HWF'], ['vendor']));
    odPhone($w);

    $payees = collect($this->getJson('/api/v1/owner/payees?q=highway')->assertOk()->json('payees'));
    expect($payees->pluck('name')->all())->toBe(['Highway Fuels']);
    $ledger = $payees->first()['id'];

    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/owner/payments', ['ledger_account_id' => $ledger, 'amount' => 18500, 'mode' => 'bank', 'narration' => 'October diesel bill'])
        ->assertStatus(422)->assertJsonValidationErrors('reference');
    $key = (string) Str::uuid();
    $body = ['ledger_account_id' => $ledger, 'amount' => 18500, 'mode' => 'bank', 'reference' => 'UTR 88123', 'narration' => 'October diesel bill'];
    $paid = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/owner/payments', $body)->assertCreated()->json('voucher');
    $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/owner/payments', $body)->assertJsonPath('voucher.number', $paid['number']);
    expect($paid['number'])->toStartWith('PV/')->and($paid['date'])->toBe('2026-10-25')->and($paid['narration'])->toContain('Highway Fuels');

    app(CompanyContext::class)->set($w['company']);
    expect(Voucher::where('number', $paid['number'])->count())->toBe(1)
        ->and((float) Voucher::where('number', $paid['number'])->first()->lines()->where('ledger_account_id', $ledger)->value('debit'))->toBe(18500.0);
    $this->getJson('/api/v1/owner/payees?q=highway')->assertJsonPath('payees.0.balance', 18500);
    // Cash and bank ledgers are not payees.
    expect(collect($this->getJson('/api/v1/owner/payees')->json('payees'))->pluck('kind')->unique()->intersect(['cash', 'bank'])->all())->toBe([]);

    // Paying needs the right to post vouchers (the office manager has none).
    $manager = makeAccount(['name' => 'Manager']);
    joinCompany($manager, $w['company'], 'manager');
    odPhone($w, $manager);
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/owner/payments', $body)->assertForbidden();
});
