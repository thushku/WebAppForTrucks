<?php

use App\Modules\Settings\Models\Branch;
use App\Modules\Settings\Models\DocumentSequence;
use App\Modules\Settings\Models\MasterItem;
use App\Modules\Settings\Models\Place;
use App\Modules\Settings\Services\CompanySettings;
use App\Platform\Audit\AuditLog;
use App\Platform\Tenancy\CompanyContext;

// Company set-up (M2): country defaults, the settings pages and who may open them.

it('gives a new company its head office, lists and number series by country', function () {
    $india = makeCompany();
    $uae   = makeCompany(['name' => 'Gulf Haulage', 'country' => 'AE', 'currency' => 'AED', 'timezone' => 'Asia/Dubai', 'fy_start_month' => 1]);

    app(CompanyContext::class)->runAs($india, function () {
        expect(Branch::where('is_head_office', true)->value('code'))->toBe('HO')
            ->and(MasterItem::ofList('document_type')->pluck('code'))->toContain('rc', 'fitness', 'puc')
            ->and(MasterItem::ofList('expense_category')->where('code', 'no_receipt')->value('name'))->toBe('Payment without receipt')
            ->and(DocumentSequence::count())->toBe(count(DocumentSequence::DOC_TYPES))
            ->and(DocumentSequence::where('doc_type', 'invoice')->value('max_length'))->toBe(16);
    });

    app(CompanyContext::class)->runAs($uae, function () {
        expect(MasterItem::ofList('document_type')->pluck('code'))->toContain('mulkiya', 'emirates_id')->not->toContain('puc')
            ->and(DocumentSequence::where('doc_type', 'invoice')->value('max_length'))->toBeNull()
            ->and(app(CompanySettings::class)->get('bata_driver_amount'))->toBe(50.0);
    });
});

it('opens every settings page for the owner', function () {
    $company = makeCompany();
    signInTo($company);

    foreach (array_keys(\App\Http\Controllers\Settings\SettingsController::PAGES) as $route) {
        $this->get(route($route))->assertOk();
    }
    $this->get(route('settings.index'))->assertRedirect(route('settings.company'));
});

it('keeps settings away from a viewer', function () {
    $company = makeCompany();
    signInTo($company, 'viewer');

    $this->get(route('settings.index'))->assertForbidden();
    $this->get(route('settings.company'))->assertForbidden();
    $this->put(route('settings.rules.update'), ['dual_approval_enabled' => '0'])->assertForbidden();
    $this->get(route('dashboard'))->assertOk()->assertDontSee(route('settings.index'));
});

it('lets a manager keep places but not company rules', function () {
    $company = makeCompany();
    signInTo($company, 'manager');

    $this->get(route('settings.index'))->assertRedirect(route('settings.team'));
    $this->get(route('settings.places'))->assertOk();
    $this->get(route('settings.rules'))->assertForbidden();
    $this->get(route('settings.numbering'))->assertForbidden();
});

it('saves the company address with an Indian state code and PIN', function () {
    $company = makeCompany();
    signInTo($company);

    $this->put(route('settings.company.update'), ['name' => 'Sharma Roadlines', 'postal_code' => '6820'])
        ->assertSessionHasErrors('postal_code');

    $this->put(route('settings.company.update'), [
        'name' => 'Sharma Roadlines', 'legal_name' => 'Sharma Roadlines Pvt Ltd', 'short_code' => 'srl',
        'city' => 'Kochi', 'state_code' => '32', 'postal_code' => '682001', 'email' => 'office@sharma.test',
    ])->assertSessionHasNoErrors();

    $company->refresh();
    expect($company->state_code)->toBe('32')->and($company->short_code)->toBe('SRL')->and($company->legal_name)->toBe('Sharma Roadlines Pvt Ltd');

    $this->put(route('settings.company.update'), ['name' => 'X', 'state_code' => 'DU'])->assertSessionHasErrors(['name', 'state_code']);
});

it('accepts only a real image as the logo', function () {
    \Illuminate\Support\Facades\Storage::fake('local');
    $company = makeCompany();
    signInTo($company);

    $fake = \Illuminate\Http\UploadedFile::fake()->createWithContent('logo.png', 'not really a picture');
    $this->put(route('settings.company.update'), ['name' => 'Sharma Roadlines', 'logo' => $fake])->assertSessionHasErrors('logo');

    $png = \Illuminate\Http\UploadedFile::fake()->image('logo.png', 200, 80);
    $this->put(route('settings.company.update'), ['name' => 'Sharma Roadlines', 'logo' => $png])->assertSessionHasNoErrors();
    expect($company->fresh()->logo_path)->toStartWith("companies/{$company->uuid}/");
    $this->get(route('settings.company.logo'))->assertOk();
});

it('adds branches with unique codes and keeps one head office', function () {
    $company = makeCompany();
    signInTo($company);

    $this->post(route('settings.branches.store'), ['name' => 'Mumbai office', 'code' => 'bom', 'state_code' => '27'])->assertSessionHasNoErrors();
    $this->post(route('settings.branches.store'), ['name' => 'Another', 'code' => 'BOM'])->assertSessionHasErrors('code');

    app(CompanyContext::class)->set($company);
    $mumbai = Branch::where('code', 'BOM')->first();
    $head   = Branch::where('code', 'HO')->first();

    $this->put(route('settings.branches.update', $head), ['name' => 'Head office', 'code' => 'HO'])->assertSessionHasErrors('active');
    $this->put(route('settings.branches.update', $mumbai), ['name' => 'Mumbai office', 'code' => 'BOM', 'is_head_office' => '1', 'active' => '1'])->assertRedirect(route('settings.branches'));

    // Each request leaves the company context when it ends.
    app(CompanyContext::class)->set($company);
    expect(Branch::where('is_head_office', true)->pluck('code')->all())->toBe(['BOM']);
});

it('never shows or changes another company\'s branch or place', function () {
    $mine   = makeCompany();
    $theirs = makeCompany(['name' => 'Other Transport']);
    [$branch, $place] = app(CompanyContext::class)->runAs($theirs, fn () => [
        Branch::first(),
        Place::create(['name' => 'Pune', 'short_code' => 'PNQ']),
    ]);

    signInTo($mine);
    $this->get(route('settings.branches.edit', $branch))->assertNotFound();
    $this->put(route('settings.places.update', $place), ['name' => 'Hacked', 'short_code' => 'HAK', 'type' => 'city'])->assertNotFound();
    expect($place->fresh()->name)->toBe('Pune');
});

it('switches dual approval off and records who did it', function () {
    $company = makeCompany();
    $owner   = signInTo($company);
    app(CompanyContext::class)->set($company);
    expect(app(CompanySettings::class)->get('dual_approval_enabled'))->toBeTrue();

    $values = app(CompanySettings::class)->all();
    $values['dual_approval_enabled'] = '0';
    $values['single_driver_approver'] = 'none';
    $values['bata_driver_amount'] = '750';

    $this->put(route('settings.rules.update'), $values)->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($company);
    $settings = app(CompanySettings::class);
    expect($settings->get('dual_approval_enabled'))->toBeFalse()
        ->and($settings->get('single_driver_approver'))->toBe('none')
        ->and($settings->get('bata_driver_amount'))->toBe(750.0);

    $entry = AuditLog::where('action', 'settings.rules_changed')->first();
    expect($entry->actor_label)->toBe($owner->name)
        ->and($entry->summary)->toContain('dual_approval_enabled');

    // A rule nobody changed is not stored at all.
    expect(\Illuminate\Support\Facades\DB::table('company_settings')->where('company_id', $company->id)->count())->toBe(3);
});

it('refuses rule values outside their choices', function () {
    $company = makeCompany();
    signInTo($company);
    app(CompanyContext::class)->set($company);
    $values = app(CompanySettings::class)->all();

    $this->put(route('settings.rules.update'), ['single_driver_approver' => 'driver'] + $values)->assertSessionHasErrors('single_driver_approver');
    $this->put(route('settings.rules.update'), ['fuel_photos_required' => 9] + $values)->assertSessionHasErrors('fuel_photos_required');
});

it('adds places with short codes that are unique within the company only', function () {
    $company = makeCompany();
    $other   = makeCompany(['name' => 'Other Transport']);
    app(CompanyContext::class)->runAs($other, fn () => Place::create(['name' => 'Kochi', 'short_code' => 'COK']));

    signInTo($company);
    $this->post(route('settings.places.store'), ['name' => 'Kochi', 'short_code' => 'cok', 'type' => 'city', 'state_code' => '32'])->assertSessionHasNoErrors();
    $this->post(route('settings.places.store'), ['name' => 'Cochin port', 'short_code' => 'COK', 'type' => 'godown'])->assertSessionHasErrors('short_code');
    $this->post(route('settings.places.store'), ['name' => 'Bad', 'short_code' => 'C-K', 'type' => 'city'])->assertSessionHasErrors('short_code');
    $this->post(route('settings.places.store'), ['name' => 'Half', 'short_code' => 'HLF', 'type' => 'city', 'lat' => '9.9'])->assertSessionHasErrors('lng');

    $this->getJson(route('settings.places.suggest', ['name' => 'Kochi port']))->assertJson(['code' => 'KOC']);
    app(CompanyContext::class)->runAs($company, fn () => Place::create(['name' => 'Kochi port', 'short_code' => 'KOC']));
    $this->getJson(route('settings.places.suggest', ['name' => 'Kochi depot']))->assertJson(['code' => 'KOC2']);

    $this->get(route('settings.places', ['q' => 'koc']))->assertOk()->assertSee('Kochi');
});

it('renames and hides list items, and adds new ones', function () {
    $company = makeCompany();
    signInTo($company);
    app(CompanyContext::class)->set($company);
    $fuel = MasterItem::ofList('expense_category')->where('code', 'fuel')->first();

    $this->put(route('settings.lists.update', $fuel), ['name' => 'Diesel', 'meta' => ['group' => 'running', 'needs_receipt' => '1'], 'active' => '1'])->assertSessionHasNoErrors();
    $this->post(route('settings.lists.store', 'expense_category'), ['name' => 'Tarpaulin hire', 'meta' => ['group' => 'running']])->assertSessionHasNoErrors();
    $this->post(route('settings.lists.store', 'material'), ['name' => 'Tea'])->assertSessionHasNoErrors();
    $this->post(route('settings.lists.store', 'nonsense'), ['name' => 'X'])->assertNotFound();

    app(CompanyContext::class)->set($company);
    expect($fuel->fresh()->name)->toBe('Diesel')
        ->and($fuel->fresh()->meta)->toBe(['group' => 'running', 'needs_receipt' => true])
        ->and(MasterItem::ofList('expense_category')->where('code', 'tarpaulin_hire')->exists())->toBeTrue()
        ->and(MasterItem::ofList('material')->where('code', 'tea')->value('is_system'))->toBeFalse();

    $this->put(route('settings.lists.update', $fuel), ['name' => 'Diesel', 'meta' => ['group' => 'running']])->assertSessionHasNoErrors();
    expect($fuel->fresh()->active)->toBeFalse();
});

it('shows only this company\'s activity, newest first, with filters', function () {
    $company = makeCompany();
    $other   = makeCompany(['name' => 'Other Transport']);
    app(\App\Platform\Audit\AuditRecorder::class)->record('settings.rules_changed', 'Secret change elsewhere', companyId: $other->id);

    signInTo($company);
    $this->post(route('settings.places.store'), ['name' => 'Kochi', 'short_code' => 'COK', 'type' => 'city']);

    $this->get(route('settings.activity'))->assertOk()->assertSee('Kochi')->assertDontSee('Secret change elsewhere');
    $this->get(route('settings.activity', ['area' => 'numbering']))->assertOk()->assertDontSee('Kochi');
    $this->get(route('settings.activity', ['from' => '2026-10-26', 'to' => '2026-10-24']))->assertSessionHasErrors('to');
});
