<?php

use App\Platform\Audit\AuditLog;
use App\Platform\Settings\PlatformSettings;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// My profile on the web and in both apps, the company's Business tab in the
// owner app, and Help (M28, 13-parity #14, #28, #30).

it('lets a person keep their own name, language, birthday and photo, even in a read-only company', function () {
    Storage::fake('local');
    $company = makeCompany();
    $me = signInTo($company, 'manager', ['name' => 'Anita Menon']);

    $this->get(route('profile.edit'))->assertOk()->assertSeeText('My profile')->assertSeeText('Add a photo')->assertSee('value="hi"', false);
    $this->get(route('dashboard'))->assertSee(route('profile.edit'), false)->assertSee(route('help'), false);

    $this->put(route('profile.update'), ['name' => 'A', 'date_of_birth' => '2020-01-01'])->assertSessionHasErrors(['name', 'date_of_birth']);
    $this->put(route('profile.update'), ['name' => 'Anita Menon K', 'locale' => 'hi', 'date_of_birth' => '1988-04-12', 'photo' => UploadedFile::fake()->image('me.jpg', 1200, 800)])
        ->assertSessionHasNoErrors();
    $me = $me->fresh();
    app(CompanyContext::class)->set($company);
    expect([$me->name, $me->locale, $me->date_of_birth->toDateString()])->toBe(['Anita Menon K', 'hi', '1988-04-12'])
        ->and(getimagesizefromstring(Storage::disk('local')->get($me->photo_path))[0])->toBe(512)
        ->and(getimagesizefromstring(Storage::disk('local')->get($me->photo_path))[1])->toBe(512)
        ->and(AuditLog::where('action', 'account.profile_updated')->exists())->toBeTrue();
    // The screens follow the language chosen; the header shows the photo.
    $this->get(route('dashboard'))->assertSee('lang="hi"', false)->assertSee(route('profile.photo', ['v' => substr(md5($me->photo_path), 0, 8)]), false);
    $this->get(route('profile.photo'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');

    // A read-only company still lets people keep their own profile.
    $company->forceFill(['status' => 'lapsed'])->save();
    $old = $me->photo_path;
    $this->put(route('profile.update'), ['name' => 'Anita Menon K', 'locale' => 'en', 'remove_photo' => '1'])->assertSessionHasNoErrors();
    expect($me->fresh()->photo_path)->toBeNull()->and(Storage::disk('local')->exists($old))->toBeFalse();
    $this->get(route('profile.photo'))->assertNotFound();
});

it('gives both apps the profile, the company’s business details and help', function () {
    Storage::fake('local');
    $settings = app(PlatformSettings::class);
    $settings->set('support_whatsapp', '+91 98470 55555');
    $settings->set('support_email', 'help@fleet.test');
    $w = invWorld();
    phoneReset();
    $this->withHeaders(['Authorization' => 'Bearer ' . $w['owner']->createToken('owner:android')->plainTextToken, 'X-Company' => $w['company']->uuid, 'Accept' => 'application/json']);

    $this->getJson('/api/v1/profile')->assertOk()->assertJsonPath('name', $w['owner']->name)->assertJsonPath('photo_url', null)->assertJsonPath('languages.hi', 'हिन्दी');
    $saved = $this->post('/api/v1/profile', ['name' => 'Anil Sharma', 'date_of_birth' => '1979-02-01', 'photo' => UploadedFile::fake()->image('me.png', 300, 400)])->assertOk()->json();
    expect($saved['name'])->toBe('Anil Sharma')->and($saved['photo_url'])->toContain('/api/v1/profile/photo');
    $this->get($saved['photo_url'])->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->getJson('/api/v1/me')->assertJsonPath('account.photo_url', $saved['photo_url']);
    $this->postJson('/api/v1/profile', ['name' => 'Anil Sharma', 'photo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])->assertStatus(422)->assertJsonValidationErrors('photo');

    $this->getJson('/api/v1/help')->assertOk()->assertJsonPath('whatsapp', '919847055555')->assertJsonPath('email', 'help@fleet.test')
        ->assertJsonPath('faq_url', route('site.faq'));

    // Business: read by the office, changed by the owner (settings.company), the web's rules.
    $company = $this->getJson('/api/v1/owner/company')->assertOk()->assertJsonPath('can_edit', true)->assertJsonPath('tax_ids.0.regime', 'in_gst')->json();
    expect($company['regions'])->toHaveKey('32');
    $this->postJson('/api/v1/owner/company', ['name' => 'Sharma Roadlines', 'postal_code' => '6820'])->assertStatus(422)->assertJsonValidationErrors('postal_code');
    $saved = $this->post('/api/v1/owner/company', ['name' => 'Sharma Roadlines Pvt Ltd', 'city' => 'Kochi', 'state_code' => '32', 'postal_code' => '682030',
        'logo' => UploadedFile::fake()->image('logo.png', 400, 200)->size(40)])->assertOk()->json();
    expect([$saved['name'], $saved['state'], $saved['postal_code']])->toBe(['Sharma Roadlines Pvt Ltd', 'Kerala', '682030'])->and($saved['logo_url'])->not->toBeNull();
    $this->get($saved['logo_url'])->assertOk();

    $viewer = makeAccount(['name' => 'Viewer']);
    joinCompany($viewer, $w['company'], 'viewer');
    phoneReset();
    $this->withHeaders(['Authorization' => 'Bearer ' . $viewer->createToken('owner:android')->plainTextToken, 'X-Company' => $w['company']->uuid, 'Accept' => 'application/json']);
    $this->getJson('/api/v1/owner/company')->assertOk()->assertJsonPath('can_edit', false)->assertJsonPath('city', 'Kochi');
    $this->postJson('/api/v1/owner/company', ['name' => 'Someone Else'])->assertForbidden();
});

it('shows Help with the provider’s contacts, and the contact form when there are none', function () {
    $company = makeCompany();
    signInTo($company);
    $this->get(route('help'))->assertOk()->assertSeeText('Write to us')->assertSeeText('Questions and answers')->assertDontSeeText('WhatsApp support');

    app(PlatformSettings::class)->set('support_whatsapp', '+971 50 123 4567');
    app(PlatformSettings::class)->set('support_phone', '+91 484 400 1234');
    $this->get(route('help'))->assertSeeText('WhatsApp support')->assertSee('https://wa.me/971501234567?text=', false)->assertSee('tel:+914844001234', false)->assertDontSeeText('Write to us');
});
