<?php

use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Services\InvoiceService;
use App\Modules\Settings\Models\Branch;
use App\Modules\Settings\Models\DocumentSequence;
use App\Modules\Tax\Models\TaxRegistration;
use App\Modules\Tax\Models\TaxRegistrationYear;
use App\Modules\Trips\Models\Consignment;
use App\Modules\Trips\Models\Trip;
use App\Platform\Tenancy\CompanyContext;

// Number series per GSTIN and per branch (M30): GST wants each registration's
// invoices in their own consecutive run; LRs, quotations and hire challans
// may run per branch; {BR} is the branch's code.

it('bills a branch with its own GSTIN from it, in that GSTIN’s own run, and the main GSTIN in the company series', function () {
    $w = invWorld(loads: 2);
    [$one, $two] = $w['loads'];
    $mh = app(CompanyContext::class)->runAs($w['company'], function () use ($two) {
        $bom = Branch::create(['name' => 'Mumbai branch', 'code' => 'BOM', 'state_code' => '27', 'city' => 'Bhiwandi']);
        $mh = TaxRegistration::create(['country' => 'IN', 'regime' => 'in_gst', 'tax_id' => invGstin('27', 'AABCS1234F'), 'state_code' => '27', 'branch_id' => $bom->id, 'is_default' => false]);
        TaxRegistrationYear::create(['tax_registration_id' => $mh->id, 'fy_key' => '2026-27', 'gta_option' => 'fcm_18']);
        Trip::whereKey($two->trip_id)->update(['branch_id' => $bom->id]);

        return $mh;
    });

    $numbers = [];
    foreach ([$one, $two] as $load) {
        $this->post(route('invoices.store'), ['party_id' => $w['client']->id, 'loads' => [$load->id]])->assertSessionHasNoErrors();
        app(CompanyContext::class)->set($w['company']);
        $bill = Invoice::latest('id')->first();
        $this->post(route('invoices.finalise', $bill))->assertSessionHasNoErrors();
        app(CompanyContext::class)->set($w['company']);
        $numbers[] = $bill->fresh();
    }
    expect($numbers[0]->number)->toBe('INV/26-27/00001')->and($numbers[0]->tax_registration_id)->toBe($w['registration']->id)
        ->and($numbers[1]->number)->toBe('BOM/26-27/00001')->and($numbers[1]->tax_registration_id)->toBe($mh->id)
        // Mumbai to a Kerala client: inter-state, IGST.
        ->and(implode(',', $numbers[1]->taxes()->pluck('code')->all()))->toContain('IGST');

    // A credit note on the Mumbai bill: the same GSTIN, its own run, within 16 characters.
    $note = app(InvoiceService::class)->draftCreditNote($numbers[1], $w['owner']);
    $note->forceFill(['reason' => 'Short delivery'])->save();
    $note = app(InvoiceService::class)->finalise($note, $w['owner']);
    expect($note->number)->toBe('BOMCN26-27/0001')->and(mb_strlen($note->number))->toBeLessThanOrEqual(16)->and($note->tax_registration_id)->toBe($mh->id);

    // Settings → Numbering lists the further GSTIN's series under the company-wide ones.
    $this->get(route('settings.numbering'))->assertOk()->assertSeeText('Per GSTIN')->assertSeeText('GSTIN ' . $mh->tax_id)
        ->assertSee('BOM/26-27/00002', false)->assertSee('BOMCN26-27/0002', false)->assertSee('INV/26-27/00002', false);
});

it('numbers LRs and quotations per branch when switched on, with {BR} as the branch code', function () {
    $w = qtWorld();
    app(CompanyContext::class)->set($w['company']);
    $bom = Branch::create(['name' => 'Mumbai branch', 'code' => 'BOM', 'state_code' => '27', 'city' => 'Bhiwandi']);
    $lr = app(\App\Modules\Settings\Services\DocumentNumbering::class)->sequence('lr');

    // Off by default: the switch is shown once there are two branches.
    $this->get(route('settings.numbering'))->assertOk()->assertSeeText('Number each branch separately')->assertDontSeeText('Per branch');
    $this->put(route('settings.numbering.update', $lr), ['prefix' => 'LR/', 'format' => '{PREFIX}{FY2}/{00001}', 'reset_policy' => 'fiscal_year', 'start_number' => 1, 'next_number' => 1, 'per_branch' => '1'])
        ->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect($lr->fresh()->per_branch)->toBeTrue();
    $this->get(route('settings.numbering'))->assertSeeText('Per branch')->assertSeeText('Mumbai branch (BOM)')->assertSee('BOM/LR/26-27/00001', false)->assertSee('HO/LR/26-27/00001', false);

    // A Mumbai trip's LR takes Mumbai's run; a trip with no branch the company's.
    app(CompanyContext::class)->set($w['company']);
    $trips = app(\App\Modules\Trips\Services\TripService::class);
    $make = fn (?int $branch) => $trips->create(['trip_date' => '2026-10-25', 'truck_id' => makeTruck(['reg_no' => 'MH-04-' . random_int(1000, 9999)])->id, 'from_place_id' => $w['bom']->id, 'to_place_id' => $w['cok']->id, 'branch_id' => $branch],
        [['person_id' => makePerson(['name' => 'D ' . random_int(1, 999), 'mobile' => '+9198' . random_int(10000000, 99999999), 'licence_no' => 'MH04' . random_int(100000, 999999), 'licence_expiry' => '2030-01-01'])->id, 'role' => 'driver']],
        ['billing_party_id' => $w['acc']->id, 'material' => 'cement', 'actual_weight_mt' => 10, 'freight_basis' => 'trip', 'rate' => 30000, 'freight' => 30000, 'payment_type' => 'to_be_billed']);
    $mumbai = $make($bom->id);
    $trips->assign($mumbai->fresh());
    $shared = $make(null);
    $trips->assign($shared->fresh());
    expect(Consignment::where('trip_id', $mumbai->id)->value('lr_number'))->toBe('BOM/LR/26-27/00001')
        ->and(Consignment::where('trip_id', $shared->id)->value('lr_number'))->toBe('LR/26-27/00001');

    // {BR} prints the branch's code, not its number.
    $qt = app(\App\Modules\Settings\Services\DocumentNumbering::class)->sequence('quotation');
    $qt->update(['format' => '{PREFIX}{BR}/{FY2}/{0001}']);
    $q = qtSent($w);
    expect($q->number)->toBe('QT/HO/26-27/0001');
    expect(DocumentSequence::where('scope_type', 'branch')->where('doc_type', 'quotation')->exists())->toBeFalse();
});
