<?php

use App\Platform\Billing\Plan;
use App\Platform\Data\CompanyDeletion;
use App\Platform\Tenancy\Company;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// Your data (M17): the whole company as a ZIP, deleting a company after a
// 72-hour countdown with a copy kept for the law, and the plan's modules and
// storage limit enforced.

/** A company with a party's bank account, a paper with a file and a bill link. */
function dataWorld(): array
{
    $company = makeCompany();
    $owner = signInTo($company, 'owner', ['email' => 'owner@example.com']);
    $party = makeParty(['name' => 'Raju Transport'], ['supplier'], $company);
    test()->post(route('parties.banks.store', $party), ['account_holder_name' => 'Raju K', 'bank_name' => 'HDFC Bank', 'account_number' => '50100123454321', 'ifsc' => 'HDFC0001234'])->assertSessionHasNoErrors();
    $truck = makeTruck([], $company);
    test()->post(route('documents.store', ['truck', $truck->id]), ['document_type' => 'insurance', 'number' => 'POL-778812', 'expires_on' => '2027-03-31',
        'files' => [UploadedFile::fake()->createWithContent('policy.pdf', "%PDF-1.4\n% test\n")]])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($company);

    return ['company' => $company, 'owner' => $owner, 'truck' => $truck];
}

function dataZip(string $file, ?string $password = null): ZipArchive
{
    $zip = new ZipArchive();
    expect($zip->open($file))->toBeTrue();
    if ($password) {
        $zip->setPassword($password);
    }

    return $zip;
}

it('downloads everything a company has as one ZIP, bank numbers readable and secrets left out', function () {
    Storage::fake('local');
    $w = dataWorld();
    // Another company's data never goes in.
    makeParty(['name' => 'Someone Else Ltd'], ['client'], makeCompany(['name' => 'Other Co']));

    $this->get(route('settings.data'))->assertOk()->assertSeeText('Download all your data')->assertSeeText('Delete this company');
    $response = $this->get(route('settings.data.export'))->assertOk()->assertHeader('Content-Type', 'application/zip');
    $zip = dataZip($response->baseResponse->getFile()->getPathname());

    $parties = $zip->getFromName('data/parties.csv');
    expect($parties)->toContain('Raju Transport')->not->toContain('Someone Else')
        ->and($zip->getFromName('data/party_bank_accounts.csv'))->toContain('50100123454321')
        ->and($zip->getFromName('data/company.csv'))->toContain('Sharma Roadlines')
        ->and($zip->getFromName('data/team.csv'))->toContain('owner@example.com')
        ->and($zip->getFromName('data/invoices.csv'))->not->toContain('public_token')
        ->and($zip->locateName('data/activation_codes.csv'))->toBeFalse()
        ->and($zip->getFromName('README.txt'))->toContain('parties')->toContain('files')
        ->and(collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i))->filter(fn ($n) => str_starts_with($n, 'files/documents/'))->count())->toBe(1);
    expect(DB::table('audit_log')->where('action', 'data.exported')->exists())->toBeTrue();

    // A read-only company still downloads; the crew and the manager do not.
    $w['company']->forceFill(['status' => 'lapsed', 'read_only_since' => now()])->save();
    $this->get(route('settings.data.export'))->assertOk();
    signInTo($w['company'], 'manager');
    $this->get(route('settings.data'))->assertForbidden();
});

it('deletes a company 72 hours after the owner asks, unless cancelled, and keeps an encrypted copy for the law', function () {
    Storage::fake('local');
    $w = dataWorld();
    $other = makeCompany(['name' => 'Other Co']);
    makeTruck(['reg_no' => 'KL-01-ZZ-0001'], $other);

    $this->post(route('company.deletion.request'), ['confirm' => 'Sharma', 'password' => 'Secret-pass-123', 'reason' => 'Closing the business'])->assertSessionHasErrors('confirm');
    $this->post(route('company.deletion.request'), ['confirm' => 'sharma roadlines', 'password' => 'wrong', 'reason' => 'Closing the business'])->assertSessionHasErrors('password');
    $this->post(route('company.deletion.request'), ['confirm' => 'sharma roadlines ', 'password' => 'Secret-pass-123', 'reason' => 'Closing the business'])->assertSessionHasNoErrors();
    expect($w['company']->fresh()->deletion_requested_at)->not->toBeNull();
    $this->get(route('dashboard'))->assertOk()->assertSee('data-deletion-banner', false)->assertSeeText('This company will be deleted on 28 October 2026');

    $this->post(route('company.deletion.cancel'))->assertSessionHasNoErrors();
    expect($w['company']->fresh()->deletion_requested_at)->toBeNull();
    $this->post(route('company.deletion.request'), ['confirm' => 'Sharma Roadlines', 'password' => 'Secret-pass-123', 'reason' => 'Closing the business'])->assertSessionHasNoErrors();

    // Not yet: 71 hours.
    $this->travel(71)->hours();
    $this->artisan('fleet:deletions')->expectsOutputToContain('0 company(ies) deleted')->assertSuccessful();
    $this->travel(2)->hours();
    $this->artisan('fleet:deletions')->expectsOutputToContain('1 company(ies) deleted')->assertSuccessful();

    $company = Company::findOrFail($w['company']->id);
    expect($company->status)->toBe('deleted')->and($company->deleted_at)->not->toBeNull()
        ->and($company->retention_until->toDateString())->toBe('2034-10-28')
        ->and(DB::table('trucks')->where('company_id', $company->id)->count())->toBe(0)
        ->and(DB::table('parties')->where('company_id', $company->id)->count())->toBe(0)
        ->and(DB::table('company_users')->where('company_id', $company->id)->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles("companies/{$company->uuid}"))->toBe([])
        // The other company is untouched.
        ->and(DB::table('trucks')->where('company_id', $other->id)->count())->toBe(1);

    // The law copy opens only with its password.
    $file = Storage::disk('local')->path($company->retention_path);
    expect(dataZip($file)->getFromName('data/parties.csv'))->toBeFalse()
        ->and(dataZip($file, app(CompanyDeletion::class)->password($company))->getFromName('data/parties.csv'))->toContain('Raju Transport');

    // Nobody signs in to it any more; the console sees what happened.
    $this->get(route('dashboard'))->assertRedirect(route('company.choose'));
    $admin = new \App\Platform\Console\Admin(['name' => 'Ops', 'email' => 'data-ops@example.com']);
    $admin->forceFill(['password_hash' => \Illuminate\Support\Facades\Hash::make('Console-pass-12345'), 'status' => 'active'])->save();
    $this->actingAs($admin, 'platform');
    $this->get(route('platform.companies.show', $company))->assertOk()->assertSeeText('Deleted on')->assertSeeText('Closing the business');
    $this->get(route('platform.system'))->assertOk()->assertSeeText('Copies kept for the law')->assertSeeText('Sharma Roadlines');

    // Eight years on, the copy goes too.
    $this->travel(8)->years();
    $this->travel(1)->days();
    $this->artisan('fleet:deletions')->expectsOutputToContain('1 law copy(ies) removed')->assertSuccessful();
    expect(Storage::disk('local')->exists($company->retention_path))->toBeFalse()->and($company->fresh()->retention_path)->toBeNull();
});

it('takes away the modules a plan leaves out, on the web and on the phones', function () {
    $company = makeCompany();
    $plan = Plan::create(['code' => 'trips-only', 'name' => 'Trips only', 'currency' => 'INR', 'pricing' => 'flat', 'price_monthly' => 300, 'modules' => ['trips', 'fleet'], 'active' => true]);
    $owner = signInTo($company);
    $this->get(route('invoices.index'))->assertOk();

    $company->forceFill(['plan_id' => $plan->id, 'status' => 'active'])->save();
    app(\App\Platform\Authorization\PermissionResolver::class)->forget();
    $this->get(route('invoices.index'))->assertForbidden();
    $this->get(route('ledgers.index'))->assertForbidden();
    $this->get(route('trucks.index'))->assertOk();
    $this->get(route('dashboard'))->assertOk()->assertDontSee(route('invoices.index'), false)->assertSee(route('trucks.index'), false);

    $token = $owner->createToken('owner:android')->plainTextToken;
    app(\App\Platform\Authorization\PermissionResolver::class)->forget();
    $permissions = $this->withToken($token)->withHeader('X-Company', $company->uuid)->getJson('/api/v1/me')->assertOk()->json('permissions');
    expect($permissions)->toContain('trip.view')->not->toContain('invoice.view')->not->toContain('ledger.view');
});

it('stops office uploads when the plan’s storage is full and warns before', function () {
    Storage::fake('local');
    $company = makeCompany();
    $plan = Plan::create(['code' => 'tiny', 'name' => 'Tiny', 'currency' => 'INR', 'pricing' => 'flat', 'price_monthly' => 100, 'storage_mb' => 1, 'active' => true]);
    $company->forceFill(['plan_id' => $plan->id, 'status' => 'active'])->save();
    signInTo($company);
    $truck = makeTruck([], $company);

    $pdf = fn (int $kb) => UploadedFile::fake()->createWithContent('scan.pdf', "%PDF-1.4\n" . str_repeat('x', $kb * 1024));
    $this->post(route('documents.store', ['truck', $truck->id]), ['document_type' => 'insurance', 'files' => [$pdf(950)]])->assertSessionHasNoErrors();
    $this->get(route('dashboard'))->assertOk()->assertSeeText('Storage is 92% full');
    $this->post(route('documents.store', ['truck', $truck->id]), ['document_type' => 'puc', 'files' => [$pdf(200)]])
        ->assertSessionHasErrors(['files' => 'The plan’s storage (1 MB) is full. Remove old files or move to a bigger plan to add more.']);
});
