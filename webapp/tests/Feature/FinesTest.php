<?php

use App\Modules\Accounts\Models\Voucher;
use App\Modules\Fleet\Models\Fine;
use App\Modules\Payroll\Models\PayRun;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Road\Models\DrivingShift;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Fines and e-challans (M48): recorded against the truck at the challan's
// time — the trip and the driver at the wheel then found by themselves —,
// paid from the office (booked as the truck's fine) or on the road (nothing
// new booked), disputed or cancelled; one the driver bears comes off their
// next pay run; black points add up per driver.

/** The phone world's trip on the road since 08:30 IST, the co-driver at the wheel 10:30–12:30, then the driver; both paid monthly. */
function fnWorld(): array
{
    $w = phoneWorld();
    $owner = signInTo($w['company']);
    app(CompanyContext::class)->runAs($w['company'], function () use ($w, $owner) {
        auth()->setUser($owner);
        app(\App\Modules\Trips\Services\TripService::class)->start($w['trip']->fresh(), 100100);
        DB::table('trips')->where('id', $w['trip']->id)->update(['started_at' => '2026-10-25 03:00:00']);
        DrivingShift::create(['trip_id' => $w['trip']->id, 'person_id' => $w['co']->id, 'started_at' => '2026-10-25 05:00:00', 'ended_at' => '2026-10-25 07:00:00']);
        DrivingShift::create(['trip_id' => $w['trip']->id, 'person_id' => $w['driver']->id, 'started_at' => '2026-10-25 07:00:00']);
        foreach (['driver', 'co'] as $who) {
            $w[$who]->forceFill(['pay_basis' => 'monthly', 'monthly_salary' => 18000, 'joining_date' => '2026-01-01'])->save();
        }
    });

    return $w + ['owner' => $owner];
}

it('records a fine for whoever was driving, pays it and takes the driver’s from their pay', function () {
    $w = fnWorld();
    $fine = fn (string $no) => app(CompanyContext::class)->runAs($w['company'], fn () => Fine::where('challan_no', $no)->firstOrFail());
    $base = ['truck_id' => $w['truck']->id, 'authority' => 'Kerala Police', 'place' => 'Aluva'];

    // 11:30 IST: the co-driver's shift. Overspeeding, the driver bears it, 4 black points.
    $this->post(route('trucks.fines.store'), $base + ['issued_at' => '2026-10-25T11:30', 'challan_no' => 'kl0712345678', 'offence' => 'Overspeeding', 'amount' => 1000, 'points' => 4, 'bears' => 'driver'])
        ->assertSessionHasNoErrors()->assertSessionHas('status', 'Fine recorded for Suresh Nair.');
    // 13:00 IST: the driver's shift; the company bears a no-entry fine.
    $this->post(route('trucks.fines.store'), $base + ['issued_at' => '2026-10-25T13:00', 'challan_no' => 'KL0712345679', 'offence' => 'No entry', 'amount' => 500, 'bears' => 'company'])->assertSessionHasNoErrors();
    expect([$fine('KL0712345678')->person_id, $fine('KL0712345678')->trip_id, $fine('KL0712345679')->person_id])->toBe([$w['co']->id, $w['trip']->id, $w['driver']->id]);

    // Before the trip nobody was driving: the driver must be named. A challan number once; never in the future.
    $this->post(route('trucks.fines.store'), $base + ['issued_at' => '2026-10-24T10:00', 'offence' => 'Parking', 'amount' => 300, 'bears' => 'driver'])->assertSessionHasErrors('person_id');
    $this->post(route('trucks.fines.store'), $base + ['issued_at' => '2026-10-25T12:00', 'challan_no' => 'KL0712345678', 'offence' => 'Signal', 'amount' => 300, 'bears' => 'company'])->assertSessionHasErrors('challan_no');
    $this->post(route('trucks.fines.store'), $base + ['issued_at' => '2026-10-25T18:00', 'offence' => 'Signal', 'amount' => 300, 'bears' => 'company'])->assertSessionHasErrors('issued_at');

    $this->get(route('trucks.fines'))->assertOk()->assertSeeText('KL0712345678')->assertSeeText('2 fines unpaid, ₹1,500.00')
        ->assertSeeText('Suresh Nair')->assertSeeText('4 of 24 points')->assertSeeText('The driver, from the next pay run');
    $this->get(route('dashboard'))->assertSeeText('2 fines unpaid, ₹1,500.00');

    // The company's one paid by bank: the truck's fine in the books. The driver's paid by the crew on the road: nothing new booked.
    $this->post(route('trucks.fines.pay', $fine('KL0712345679')), ['paid_on' => '2026-10-25', 'mode' => 'bank', 'reference' => 'UTR 5512'])->assertSessionHasNoErrors();
    $this->post(route('trucks.fines.pay', $fine('KL0712345678')), ['paid_on' => '2026-10-25', 'mode' => 'road'])->assertSessionHasNoErrors();
    $this->post(route('trucks.fines.pay', $fine('KL0712345678')), ['paid_on' => '2026-10-25', 'mode' => 'cash'])->assertSessionHasErrors('fine');
    app(CompanyContext::class)->set($w['company']);
    $voucher = Voucher::with('lines.ledger')->findOrFail($fine('KL0712345679')->voucher_id);
    $cost = $voucher->lines->firstWhere('debit', '>', 0);
    expect([(float) $cost->debit, $cost->truck_id, $cost->trip_id])->toBe([500.0, $w['truck']->id, $w['trip']->id])
        ->and($fine('KL0712345678')->voucher_id)->toBeNull()->and(Voucher::where('source_type', 'fine')->count())->toBe(1);

    // October's pay run takes the co-driver's ₹1,000 back; the driver's pay is whole.
    $this->post(route('payroll.store'), ['period_from' => '2026-10-01', 'period_to' => '2026-10-31', 'scope' => 'crew'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $run = PayRun::firstOrFail();
    $co = Payslip::where('person_id', $w['co']->id)->firstOrFail();
    expect($co->lines)->toHaveCount(1)->and($co->lines[0])->toMatchArray(['kind' => 'deduction', 'type' => 'fine', 'amount' => 1000, 'fine_id' => $fine('KL0712345678')->id])
        ->and((float) $co->net)->toBe(17000.0)->and(Payslip::where('person_id', $w['driver']->id)->value('deductions'))->toEqual(0)
        ->and($fine('KL0712345678')->payslip_id)->toBe($co->id);
    $this->get(route('trucks.fines.show', $fine('KL0712345678')))->assertOk()->assertSeeText('In the draft pay run of 1–31 Oct 2026')->assertSeeText('By the crew on the road');
    $this->post(route('trucks.fines.status', $fine('KL0712345678')), ['action' => 'cancel', 'note' => 'Court cancelled it'])->assertSessionHasErrors('fine');

    // Taken out of the payslip it waits; worked out again it comes back.
    $this->delete(route('payroll.lines.destroy', [$run, $co->id, 0]))->assertSessionHasNoErrors();
    expect($fine('KL0712345678')->payslip_id)->toBeNull();
    $this->post(route('payroll.recalculate', $run))->assertSessionHasNoErrors();
    expect($fine('KL0712345678')->payslip_id)->toBe($co->id);

    // Approved: the ₹1,000 is recovered as income. Cancelled: the fine waits for the next run.
    $this->post(route('payroll.approve', $run))->assertSessionHasNoErrors();
    $this->get(route('trucks.fines.show', $fine('KL0712345678')))->assertSeeText('Taken from the pay of 1–31 Oct 2026');
    app(CompanyContext::class)->set($w['company']);
    $slipVoucher = Voucher::with('lines.ledger')->where('source_type', 'payslip')->where('source_id', $co->id)->firstOrFail();
    expect((float) $slipVoucher->lines->first(fn ($l) => $l->ledger->system_key === 'staff_recoveries')->credit)->toBe(1000.0);
    $this->post(route('payroll.cancel', $run), ['reason' => 'Wrong month'])->assertSessionHasNoErrors();
    expect($fine('KL0712345678')->payslip_id)->toBeNull();

    // A paid fine cancelled by the court: its payment is reversed.
    $this->post(route('trucks.fines.status', $fine('KL0712345679')), ['action' => 'cancel', 'note' => ''])->assertSessionHasErrors('reason');
    $this->post(route('trucks.fines.status', $fine('KL0712345679')), ['action' => 'cancel', 'note' => 'Cancelled by the virtual court'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect(Voucher::find($fine('KL0712345679')->voucher_id)->status)->toBe('cancelled')->and($fine('KL0712345679')->status)->toBe('cancelled');
});

it('lets the owner app record, pay and dispute fines, and shows the crew theirs', function () {
    $w = fnWorld();
    phoneReset();
    $this->withHeaders(['Authorization' => 'Bearer ' . $w['owner']->createToken('owner:android')->plainTextToken, 'X-Company' => $w['company']->uuid, 'Accept' => 'application/json']);

    $key = (string) Str::uuid();
    $body = ['truck_id' => $w['truck']->id, 'issued_at' => '2026-10-25 11:45', 'challan_no' => 'DXB-998877', 'authority' => 'Dubai Police', 'offence' => 'Lane discipline', 'amount' => 400, 'points' => 6, 'bears' => 'driver'];
    $made = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/owner/fines', $body)->assertCreated()->assertJsonPath('fine.person.name', 'Suresh Nair')
        ->assertJsonPath('fine.recovery.state', 'waiting')->assertJsonPath('fine.trip.uuid', $w['trip']->uuid)->json('fine');
    $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/owner/fines', $body)->assertJsonPath('replayed', true);
    $other = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/owner/fines', ['challan_no' => 'DXB-998878', 'offence' => 'Red light'] + $body)->assertCreated()->json('fine');

    $this->getJson('/api/v1/owner/fines')->assertOk()->assertJsonCount(2, 'fines')->assertJsonPath('open.count', 2)
        ->assertJsonPath('points.0.points', 12)->assertJsonPath('points.0.person.name', 'Suresh Nair')->assertJsonPath('points_limit', 24);
    $this->postJson('/api/v1/owner/fines/' . $other['id'] . '/status', ['action' => 'dispute', 'note' => 'Signal was not working'])->assertOk()->assertJsonPath('fine.status', 'disputed');
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/owner/fines/' . $made['id'] . '/pay', ['paid_on' => '2026-10-25', 'mode' => 'cash'])->assertCreated()
        ->assertJsonPath('fine.status', 'paid')->assertJsonPath('fine.paid_mode', 'cash');
    $this->getJson('/api/v1/owner/fines?status=paid')->assertJsonCount(1, 'fines');

    // The co-driver's My Khata: both fines and their 12 points.
    $phones = phoneCrew($w);
    phoneAs($phones['co'], $w['company']);
    $this->getJson('/api/v1/crew/money')->assertOk()->assertJsonCount(2, 'fines')->assertJsonPath('points', 12)->assertJsonPath('fines.0.bears', 'driver');
});
