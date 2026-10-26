<?php

use App\Modules\Accounts\Models\LedgerAccount;
use App\Modules\Accounts\Models\Voucher;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Fleet\Models\TruckEmi;
use App\Modules\Fleet\Services\TruckLoans;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

// Truck loans and EMIs (M35): the loan set on the truck, each EMI falling due
// on its day, paid as a voucher — principal off the loan, interest the
// truck's cost — and shown when due or overdue.

/** A truck with an HDFC loan: ₹45,000 on the 5th, 10 % a year, ₹12,00,000 owed at the start of the year. */
function emWorld(): array
{
    $company = makeCompany();
    $owner = signInTo($company);
    $truck = app(CompanyContext::class)->runAs($company, fn () => makeTruck(['reg_no' => 'KL 07 CX 4400', 'rc_owner_name' => 'Sharma Roadlines']));
    test()->put(route('trucks.update', $truck), ['reg_no' => 'KL 07 CX 4400', 'rc_owner_name' => 'Sharma Roadlines', 'fuel_type' => 'diesel', 'ownership' => 'own',
        'financier' => 'HDFC Bank', 'emi_amount' => 45000, 'emi_day' => 5, 'loan_rate' => 10, 'loan_starts_on' => '2024-04-05', 'loan_ends_on' => '2028-03-05',
        'loan_opening' => 1200000])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($company);

    return ['company' => $company, 'owner' => $owner, 'truck' => $truck->fresh()];
}

it('follows a truck loan’s EMIs and books each paid one as principal and interest', function () {
    $w = emWorld();
    $loans = app(TruckLoans::class);
    expect($loans->outstanding($w['truck']))->toBe(1200000.0)
        ->and($loans->status($w['truck'], CarbonImmutable::parse('2026-10-25'))['next']->toDateString())->toBe('2026-11-05');

    // 10 December: November's and December's EMIs are overdue.
    $this->travelTo(CarbonImmutable::parse('2026-12-10 10:00', 'Asia/Kolkata'));
    $this->get(route('trucks.emis'))->assertOk()->assertSeeText('KL 07 CX 4400')->assertSeeText('2 overdue, since 05 Nov')->assertSeeText('₹12,00,000.00')
        ->assertSee('value="10000"', false);
    $this->get(route('dashboard'))->assertSeeText('2 truck EMIs are overdue');

    $pay = ['form_truck' => $w['truck']->id, 'due_on' => '2026-11-05', 'paid_on' => '2026-12-10', 'amount' => 45000, 'interest' => 10000, 'mode' => 'bank'];
    $this->post(route('trucks.emis.pay', $w['truck']), $pay)->assertSessionHasErrors('reference');
    $this->post(route('trucks.emis.pay', $w['truck']), $pay + ['reference' => 'UTR 5511'])->assertSessionHasNoErrors()->assertRedirect(route('trucks.emis'));
    $this->post(route('trucks.emis.pay', $w['truck']), $pay + ['reference' => 'UTR 5511'])->assertSessionHasErrors('due_on');
    $this->post(route('trucks.emis.pay', $w['truck']), array_merge($pay, ['due_on' => '2026-11-06', 'reference' => 'X']))->assertSessionHasErrors('due_on');

    app(CompanyContext::class)->set($w['company']);
    $emi = TruckEmi::sole();
    $voucher = Voucher::with('lines')->findOrFail($emi->voucher_id);
    $loan = LedgerAccount::where('system_key', 'truck_loan:' . $w['truck']->id)->value('id');
    expect([(float) $emi->principal, (float) $emi->interest])->toBe([35000.0, 10000.0])->and($voucher->number)->toStartWith('PV/')
        ->and((float) $voucher->lines->firstWhere('ledger_account_id', $loan)->debit)->toBe(35000.0)
        ->and($voucher->lines->where('debit', '>', 0)->pluck('truck_id')->unique()->all())->toBe([$w['truck']->id])
        ->and($loans->outstanding($w['truck']->fresh()))->toBe(1165000.0)
        // Next month's interest on what is now owed.
        ->and($loans->interestFor($w['truck']->fresh()))->toBe(9708.33);
    $this->get(route('trucks.show', $w['truck']))->assertSeeText('1 EMI overdue')->assertSeeText('still owed ₹11,65,000.00');
});

it('lets the owner app see and pay the EMIs, once per key', function () {
    $w = emWorld();
    $this->travelTo(CarbonImmutable::parse('2026-11-05 10:00', 'Asia/Kolkata'));
    phoneReset();
    $this->withHeaders(['Authorization' => 'Bearer ' . $w['owner']->createToken('owner:android')->plainTextToken, 'X-Company' => $w['company']->uuid, 'Accept' => 'application/json']);

    $this->getJson('/api/v1/owner/emis')->assertOk()->assertJsonPath('emis.0.truck.reg_no', 'KL 07 CX 4400')->assertJsonPath('emis.0.next', '2026-11-05')
        ->assertJsonPath('emis.0.interest', 10000)->assertJsonPath('emis.0.overdue', []);
    $key = (string) Str::uuid();
    $body = ['due_on' => '2026-11-05', 'mode' => 'cash'];
    $paid = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/owner/trucks/' . $w['truck']->id . '/emis', $body)->assertCreated()->json('emi');
    $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/owner/trucks/' . $w['truck']->id . '/emis', $body)->assertJsonPath('replayed', true);
    expect([$paid['amount'], $paid['principal'], $paid['interest'], $paid['paid_on']])->toBe([45000, 35000, 10000, '2026-11-05'])->and($paid['voucher'])->toStartWith('PV/');
    app(CompanyContext::class)->set($w['company']);
    expect(TruckEmi::count())->toBe(1);
    $this->getJson('/api/v1/owner/emis')->assertJsonPath('emis.0.next', '2026-12-05')->assertJsonPath('emis.0.outstanding', 1165000)->assertJsonPath('emis.0.last_paid', '2026-11-05');
});
