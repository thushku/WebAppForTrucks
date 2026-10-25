<?php

use App\Modules\Accounts\Models\LedgerAccount;
use App\Modules\Accounts\Models\Voucher;
use App\Modules\Payroll\Models\PayRun;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\People\Models\Person;
use App\Modules\Road\Models\DrivingShift;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Facades\DB;

// The basic driver pay run (M16, 13-parity #24): monthly, per trip and per km
// pay for any period, extra lines, approval into each person's earnings,
// payment (with bata owed), cancellation by reversal, and who may do it.

/** Two ended trips (Driver 1 and Driver 2), a monthly-paid driver who joined mid-month, a helper with no pay set. */
function payWorld(): array
{
    $w = invWorld(loads: 2);

    return app(CompanyContext::class)->runAs($w['company'], function () use ($w) {
        $one = Person::where('name', 'Driver 1')->firstOrFail();
        $two = Person::where('name', 'Driver 2')->firstOrFail();
        $one->forceFill(['pay_basis' => 'per_trip', 'per_trip_rate' => 1500])->save();
        $two->forceFill(['pay_basis' => 'per_km', 'per_km_rate' => 2.5])->save();
        $trip = \App\Modules\Trips\Models\Trip::whereHas('crew', fn ($q) => $q->where('person_id', $two->id))->firstOrFail();
        DrivingShift::create(['trip_id' => $trip->id, 'person_id' => $two->id, 'started_at' => '2026-10-24 22:00:00', 'ended_at' => '2026-10-25 06:00:00', 'start_odometer' => 100100, 'end_odometer' => 100500]);
        $monthly = makePerson(['name' => 'Kumar Office Driver', 'mobile' => '+919847066666', 'pay_basis' => 'monthly', 'monthly_salary' => 31000, 'joining_date' => '2026-10-10']);
        $helper = makePerson(['name' => 'Binu Helper', 'type' => 'helper', 'mobile' => '+919847077777']);

        return $w + ['one' => $one, 'two' => $two, 'monthly' => $monthly, 'helper' => $helper, 'trips' => \App\Modules\Trips\Models\Trip::pluck('id')];
    });
}

it('works out pay per month, per trip and per km for any period, and lists who has no pay set', function () {
    $w = payWorld();

    $this->post(route('payroll.store'), ['period_from' => '2026-10-01', 'period_to' => '2026-10-31', 'scope' => 'crew'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $run = PayRun::firstOrFail();
    $slips = Payslip::where('pay_run_id', $run->id)->get()->keyBy('person_id');

    expect($slips)->toHaveCount(3)
        ->and((float) $slips[$w['one']->id]->base_pay)->toBe(1500.0)->and((float) $slips[$w['one']->id]->units)->toBe(1.0)
        ->and((float) $slips[$w['two']->id]->base_pay)->toBe(1000.0)->and((float) $slips[$w['two']->id]->units)->toBe(400.0)
        // Joined on the 10th: 22 of October's 31 days.
        ->and((float) $slips[$w['monthly']->id]->base_pay)->toBe(22000.0)->and((float) $slips[$w['monthly']->id]->units)->toBe(22.0)
        ->and((float) $run->net_total)->toBe(24500.0);

    $this->get(route('payroll.show', $run))->assertOk()->assertSeeText('Pay run 1–31 Oct 2026')->assertSeeText('Binu Helper')
        ->assertSeeText('1 trip at ₹1,500.00')->assertSeeText('400 km at ₹2.50 a km')->assertSeeText('22 days of ₹31,000.00 a month');
    $this->get(route('payroll.index'))->assertOk()->assertSeeText('1–31 Oct 2026')->assertSeeText('₹24,500.00');

    // Nobody is paid twice for a day: an overlapping run leaves them out.
    $this->post(route('payroll.store'), ['period_from' => '2026-10-16', 'period_to' => '2026-11-15', 'scope' => 'crew'])->assertSessionHas('skipped');
    app(CompanyContext::class)->set($w['company']);
    expect(PayRun::latest('id')->first()->people)->toBe(0);
    $this->post(route('payroll.store'), ['period_from' => '2026-10-31', 'period_to' => '2026-10-01', 'scope' => 'crew'])->assertSessionHasErrors('period_to');
});

it('takes bonuses and deductions on a draft, then owes the pay on approval and pays it with the bata owed', function () {
    $w = payWorld();
    $this->post(route('payroll.store'), ['period_from' => '2026-10-01', 'period_to' => '2026-10-31', 'scope' => 'crew']);
    app(CompanyContext::class)->set($w['company']);
    $run = PayRun::firstOrFail();
    $slip = Payslip::where('person_id', $w['one']->id)->firstOrFail();
    $twoSlip = Payslip::where('person_id', $w['two']->id)->value('id');
    $other = Payslip::where('person_id', $w['monthly']->id)->firstOrFail();
    $trip = \App\Modules\Trips\Models\Trip::whereHas('crew', fn ($q) => $q->where('person_id', $w['one']->id))->firstOrFail();

    $this->post(route('payroll.lines.store', [$run, $slip->id]), ['kind' => 'addition', 'type' => 'bonus', 'label' => 'Diwali bonus', 'amount' => 500])->assertSessionHasNoErrors();
    $this->post(route('payroll.lines.store', [$run, $slip->id]), ['kind' => 'deduction', 'type' => 'fine', 'label' => 'Late delivery', 'amount' => 200])->assertSessionHasNoErrors();
    $this->post(route('payroll.lines.store', [$run, $slip->id]), ['kind' => 'deduction', 'type' => 'other', 'amount' => 5000])->assertSessionHasErrors('amount');
    app(CompanyContext::class)->set($w['company']);
    expect((float) $slip->fresh()->net)->toBe(1800.0)->and((float) $run->fresh()->net_total)->toBe(24800.0);
    $this->delete(route('payroll.lines.destroy', [$run, $slip->id, 1]))->assertSessionHasNoErrors();
    $this->post(route('payroll.lines.store', [$run, $slip->id]), ['kind' => 'deduction', 'type' => 'fine', 'label' => 'Late delivery', 'amount' => 200]);
    $this->delete(route('payroll.slips.destroy', [$run, $twoSlip]))->assertSessionHasNoErrors();

    // Bata of ₹800 from settling Driver 1's trip, owed in their earnings.
    $this->post(route('trips.settle', [$trip, $w['one']]), ['mode' => 'nil', 'bata_amount' => 800])->assertSessionHasNoErrors();

    $this->post(route('payroll.approve', $run))->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect($run->fresh()->status)->toBe('approved');
    $this->post(route('payroll.lines.store', [$run, $slip->id]), ['kind' => 'addition', 'type' => 'bonus', 'amount' => 100])->assertSessionHasErrors('run');

    // Approved: salaries 1,500 + 500 bonus and 22,000; the fine recovered; each person's earnings credited.
    app(CompanyContext::class)->set($w['company']);
    $salaries = LedgerAccount::where('name', 'Salaries and wages')->firstOrFail();
    expect(round((float) DB::table('voucher_lines')->where('ledger_account_id', $salaries->id)->sum('debit'), 2))->toBe(24000.0);
    $owed = app(\App\Modules\Payroll\Services\PayRuns::class)->owed($w['one']->fresh());
    expect($owed)->toBe(2600.0); // 1,800 pay + 800 bata

    $this->get(route('payroll.show', $run))->assertOk()->assertSeeText('Pay all owed: ₹2,600.00 (with bata)');
    app(CompanyContext::class)->set($w['company']);
    $this->post(route('payroll.pay', [$run, $slip->id]), ['mode' => 'cash', 'paid_on' => '2026-10-25', 'reference' => 'Cash book 41', 'with_dues' => '1'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect((float) $slip->fresh()->paid_amount)->toBe(2600.0)->and(app(\App\Modules\Payroll\Services\PayRuns::class)->owed($w['one']->fresh()))->toBe(0.0)
        ->and(Voucher::where('source_type', 'payslip_payment')->where('source_id', $slip->id)->value('total'))->toEqual(2600);
    $this->post(route('payroll.pay', [$run, $slip->id]), ['mode' => 'cash', 'paid_on' => '2026-10-25'])->assertSessionHasErrors('payslip');

    $this->post(route('payroll.pay', [$run, $other->id]), ['mode' => 'bank', 'paid_on' => '2026-10-25', 'reference' => 'UTR 99812'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect($run->fresh()->status)->toBe('paid')->and((float) $run->fresh()->paid_total)->toBe(24600.0);
    $this->post(route('payroll.cancel', $run), ['reason' => 'Mistake'])->assertSessionHasErrors('run');

    $this->get(route('payroll.slip', [$run, $slip->id]))->assertOk()->assertSeeText('Payslip')->assertSeeText('Diwali bonus')->assertSeeText('Less: Late delivery')
        ->assertSeeText('This includes ₹800.00 of bata and other dues from trips.');
    $this->get(route('people.show', $w['one']))->assertOk()->assertSeeText('1–31 Oct 2026');

    // The driver sees the payslip in the crew app's My Khata.
    app(CompanyContext::class)->set($w['company']);
    $code = app(\App\Modules\People\Services\ActivationService::class)->issue($w['one']->fresh());
    phoneAs(phoneActivate('+919847011111', $code), $w['company']);
    expect($this->getJson('/api/v1/crew/money')->assertOk()->json('payslips.0'))->toMatchArray(['period' => '1–31 Oct 2026', 'net' => 1800, 'status' => 'paid', 'paid_amount' => 2600, 'paid_mode' => 'cash']);
    phoneReset();
    $this->actingAs(\App\Models\Account::findOrFail($w['owner']->id), 'web')->withSession([\App\Http\Middleware\ResolveCompany::SESSION_KEY => $w['company']->id]);

    // The books rebuild finds nothing missing, and they balance.
    $this->artisan('fleet:books', ['--company' => $w['company']->id])->expectsOutputToContain('0 new voucher(s)')->doesntExpectOutputToContain('NOT BALANCED')->assertSuccessful();
});

it('cancels an approved pay run by reversing what it owed, and keeps pay to those allowed', function () {
    $w = payWorld();
    $this->post(route('payroll.store'), ['period_from' => '2026-10-01', 'period_to' => '2026-10-31', 'scope' => 'crew']);
    app(CompanyContext::class)->set($w['company']);
    $run = PayRun::firstOrFail();
    $this->post(route('payroll.approve', $run))->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect(Voucher::where('source_type', 'payslip')->where('voucher_type', '!=', 'reversal')->where('status', 'posted')->count())->toBe(3);

    $this->post(route('payroll.cancel', $run), ['reason' => 'x'])->assertSessionHasErrors('reason');
    $this->post(route('payroll.cancel', $run), ['reason' => 'Wrong period'])->assertRedirect(route('payroll.index'));
    app(CompanyContext::class)->set($w['company']);
    expect($run->fresh()->status)->toBe('cancelled')->and(Voucher::where('source_type', 'payslip')->where('voucher_type', '!=', 'reversal')->where('status', 'posted')->count())->toBe(0)
        ->and(Voucher::where('source_type', 'payslip')->where('voucher_type', 'reversal')->count())->toBe(3)
        ->and(app(\App\Modules\Payroll\Services\PayRuns::class)->owed($w['monthly']->fresh()))->toBe(0.0);
    expect(DB::table('audit_log')->where('action', 'payroll.run_cancelled')->value('reason'))->toBe('Wrong period');

    // Cancelled days can go in a new run.
    $this->post(route('payroll.store'), ['period_from' => '2026-10-01', 'period_to' => '2026-10-31', 'scope' => 'everyone'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect(PayRun::latest('id')->first()->people)->toBe(3);

    // The accountant runs payroll; managers and drivers do not see it.
    signInTo($w['company'], 'accountant');
    $this->get(route('payroll.index'))->assertOk();
    signInTo($w['company'], 'manager');
    $this->get(route('payroll.index'))->assertForbidden();
    signInTo($w['company'], 'driver');
    $this->get(route('payroll.show', $run))->assertForbidden();
});
