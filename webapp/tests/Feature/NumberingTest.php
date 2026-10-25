<?php

use App\Modules\Settings\Models\DocumentSequence;
use App\Modules\Settings\Services\DocumentNumbering;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Document numbers (ported from Clinic Ledger): one counter per period, never twice.

function numberingFor(array $company = []): DocumentNumbering
{
    app(CompanyContext::class)->set(makeCompany($company));

    return app(DocumentNumbering::class);
}

it('numbers invoices per Indian financial year, 26-27 style', function () {
    $numbers = numberingFor();

    expect($numbers->preview($numbers->sequence('invoice')))->toBe('INV/26-27/00001')
        ->and($numbers->assign('invoice'))->toBe('INV/26-27/00001')
        ->and($numbers->assign('invoice'))->toBe('INV/26-27/00002')
        ->and($numbers->preview($numbers->sequence('invoice')))->toBe('INV/26-27/00003');
});

it('gives a back-dated document its own year\'s series without moving this year', function () {
    $numbers = numberingFor();
    $numbers->assign('invoice');

    expect($numbers->assign('invoice', CarbonImmutable::parse('2026-03-31 12:00', 'Asia/Kolkata')))->toBe('INV/25-26/00001')
        ->and($numbers->assign('invoice'))->toBe('INV/26-27/00002');
});

it('makes trip numbers from the date and short codes, restarting each day', function () {
    $numbers = numberingFor();
    $parts   = ['SHP' => 'acc', 'FRM' => 'COK', 'TO' => 'BOM'];

    expect($numbers->assign('trip', null, $parts))->toBe('261025-ACC-COK-BOM-01')
        ->and($numbers->assign('trip', null, ['SHP' => 'ULT', 'FRM' => 'COK', 'TO' => 'MAA']))->toBe('261025-ULT-COK-MAA-02')
        ->and($numbers->assign('trip', now()->addDay(), $parts))->toBe('261026-ACC-COK-BOM-01');
});

it('dates a number by the company\'s own clock', function () {
    $numbers = numberingFor();

    // 20:00 UTC on 25 Oct is 01:30 on 26 Oct in India.
    expect($numbers->assign('trip', CarbonImmutable::parse('2026-10-25 20:00', 'UTC'), ['SHP' => 'ACC', 'FRM' => 'COK', 'TO' => 'BOM']))
        ->toBe('261026-ACC-COK-BOM-01');
});

it('uses the calendar year in the UAE', function () {
    $numbers = numberingFor(['name' => 'Gulf Haulage', 'country' => 'AE', 'currency' => 'AED', 'timezone' => 'Asia/Dubai', 'fy_start_month' => 1]);

    expect($numbers->assign('invoice'))->toBe('INV/2026/00001')
        ->and($numbers->sequence('invoice')->max_length)->toBeNull();
});

it('skips a number already on a document after the counter is put back', function () {
    $numbers = numberingFor();
    Schema::create('fuel_slips', function ($t) { $t->id(); $t->unsignedBigInteger('company_id'); $t->string('number'); });

    try {
        $first = $numbers->assign('fuel_slip');
        DB::table('fuel_slips')->insert(['company_id' => app(CompanyContext::class)->id(), 'number' => $first]);
        $numbers->setNext($numbers->sequence('fuel_slip'), 1);

        expect($first)->toBe('FS/26-27/00001')->and($numbers->assign('fuel_slip'))->toBe('FS/26-27/00002');
    } finally {
        Schema::dropIfExists('fuel_slips');
    }
});

it('refuses an Indian tax invoice number longer than 16 characters', function () {
    $numbers  = numberingFor();
    $sequence = $numbers->sequence('invoice');
    $sequence->update(['prefix' => 'SHARMA-INV/', 'format' => '{PREFIX}{FY}/{00001}']);

    expect(fn () => $numbers->assign('invoice'))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('accepts only formats with one counter and known tokens', function () {
    expect(DocumentNumbering::isValidFormat('{PREFIX}{FY2}/{0001}'))->toBeTrue()
        ->and(DocumentNumbering::isValidFormat('{YY}{MM}{DD}-{SHP}-{FRM}-{TO}-{01}'))->toBeTrue()
        ->and(DocumentNumbering::isValidFormat('{PREFIX}{FY}'))->toBeFalse()
        ->and(DocumentNumbering::isValidFormat('{0001}-{0001}'))->toBeFalse()
        ->and(DocumentNumbering::isValidFormat('{PREFIX}{WEEK}/{0001}'))->toBeFalse()
        ->and(DocumentNumbering::isValidFormat('INV {0001}'))->toBeFalse()
        ->and(DocumentNumbering::isValidFormat('INV_{FY}.{0001}', DocumentNumbering::allowedCharacters('invoice')))->toBeFalse()
        ->and(DocumentNumbering::isValidFormat('INV_{FY}.{0001}', DocumentNumbering::allowedCharacters('lr')))->toBeTrue();
});

it('saves a format from the screen and previews it live', function () {
    $company = makeCompany();
    signInTo($company);
    app(CompanyContext::class)->set($company);
    $lr = DocumentSequence::where('doc_type', 'lr')->first();

    $this->postJson(route('settings.numbering.preview', $lr), ['prefix' => 'COK/', 'format' => '{PREFIX}{FY2}/{0001}', 'reset_policy' => 'fiscal_year', 'start_number' => 1, 'next_number' => 41])
        ->assertJson(['ok' => true, 'number' => 'COK/26-27/0041']);
    $this->postJson(route('settings.numbering.preview', $lr), ['prefix' => '', 'format' => '{PREFIX}', 'reset_policy' => 'never', 'start_number' => 1, 'next_number' => 1])
        ->assertJson(['ok' => false]);

    $this->put(route('settings.numbering.update', $lr), ['prefix' => 'COK/', 'format' => '{PREFIX}{FY2}/{0001}', 'reset_policy' => 'fiscal_year', 'start_number' => 1, 'next_number' => 41])
        ->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($company);
    expect(app(DocumentNumbering::class)->assign('lr'))->toBe('COK/26-27/0041');
});

it('refuses a restarting format without its period, and an over-long tax invoice format', function () {
    $company = makeCompany();
    signInTo($company);
    app(CompanyContext::class)->set($company);
    $invoice = DocumentSequence::where('doc_type', 'invoice')->first();
    $trip    = DocumentSequence::where('doc_type', 'trip')->first();

    $this->put(route('settings.numbering.update', $invoice), ['prefix' => 'INV/', 'format' => '{PREFIX}{00001}', 'reset_policy' => 'fiscal_year', 'start_number' => 1, 'next_number' => 1])
        ->assertSessionHasErrors('format');
    $this->put(route('settings.numbering.update', $invoice), ['prefix' => 'SHARMAROAD/', 'format' => '{PREFIX}{FY}/{00001}', 'reset_policy' => 'fiscal_year', 'start_number' => 1, 'next_number' => 1])
        ->assertSessionHasErrors('format');
    $this->put(route('settings.numbering.update', $invoice), ['prefix' => 'IN V/', 'format' => '{PREFIX}{FY2}/{00001}', 'reset_policy' => 'fiscal_year', 'start_number' => 1, 'next_number' => 1])
        ->assertSessionHasErrors('prefix');
    $this->put(route('settings.numbering.update', $trip), ['prefix' => '', 'format' => '{SHP}-{FRM}-{TO}-{01}', 'reset_policy' => 'daily', 'start_number' => 1, 'next_number' => 1])
        ->assertSessionHasErrors('format');
});
