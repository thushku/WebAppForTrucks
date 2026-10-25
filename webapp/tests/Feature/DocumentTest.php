<?php

use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentFile;
use App\Modules\Documents\Models\DocumentRequiredType;
use App\Modules\Documents\Models\DocumentShare;
use App\Modules\Documents\Services\Compliance;
use App\Modules\Documents\Services\DocumentReminders;
use App\Modules\Documents\Services\DocumentService;
use App\Platform\Audit\AuditLog;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// The Document Manager: files, renewals, what is missing or blocks dispatch,
// personal ID privacy, reminders and share links.

function docPhoto(string $name = 'insurance.jpg', int $w = 3000, int $h = 2000): UploadedFile
{
    // A noisy picture, so it does not compress to nothing.
    $img = imagecreatetruecolor($w, $h);
    for ($i = 0; $i < 4000; $i++) {
        imagefilledrectangle($img, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
    }
    $path = tempnam(sys_get_temp_dir(), 'doc');
    imagejpeg($img, $path, 95);

    return new UploadedFile($path, $name, 'image/jpeg', null, true);
}

function docFor($company, string $ownerType, int $ownerId, string $type, ?string $expires, array $extra = []): Document
{
    return app(CompanyContext::class)->runAs($company, fn () => app(DocumentService::class)->create($ownerType, $ownerId, ['document_type' => $type, 'expires_on' => $expires] + $extra));
}

it('adds a paper with photos made small, and refuses files that are not photos or PDFs', function () {
    Storage::fake('local');
    $company = makeCompany();
    signInTo($company);
    $truck = makeTruck([], $company);

    $this->post(route('documents.store', ['truck', $truck->id]), [
        'document_type' => 'insurance', 'number' => 'POL-778812', 'valid_from' => '2026-04-01', 'expires_on' => '2027-03-31', 'amount' => '48500',
        'files' => [docPhoto(), UploadedFile::fake()->createWithContent('policy.pdf', "%PDF-1.4\n% test\n")],
    ])->assertSessionHasNoErrors();
    $this->post(route('documents.store', ['truck', $truck->id]), ['document_type' => 'puc', 'files' => [UploadedFile::fake()->createWithContent('virus.exe', 'MZ...')]])->assertSessionHasErrors('files');
    $this->post(route('documents.store', ['truck', $truck->id]), ['document_type' => 'licence'])->assertSessionHasErrors('document_type');

    app(CompanyContext::class)->set($company);
    $doc   = Document::firstWhere('document_type', 'insurance');
    $files = DocumentFile::where('document_id', $doc->id)->orderBy('id')->get();
    expect($files)->toHaveCount(2)
        ->and($files[0]->mime)->toBe('image/jpeg')
        ->and($files[0]->bytes)->toBeLessThanOrEqual(500 * 1024)
        ->and(max(array_slice(getimagesizefromstring(Storage::disk('local')->get($files[0]->path)), 0, 2)))->toBe(1600)
        ->and($files[0]->path)->toStartWith("companies/{$company->uuid}/documents/")
        ->and($files[1]->mime)->toBe('application/pdf')
        ->and(Document::where('document_type', 'puc')->exists())->toBeFalse();

    $this->get(route('documents.file', $files[0]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->get(route('documents.show', $doc))->assertOk()->assertSee('POL-778812');

    // Another company's members never get the file.
    signInTo(makeCompany(['name' => 'Other Transport']));
    $this->get(route('documents.file', $files[0]))->assertNotFound();
});

it('renews a paper into a new version and keeps the old one with its files', function () {
    Storage::fake('local');
    $company = makeCompany();
    signInTo($company);
    $truck = makeTruck([], $company);
    $doc = docFor($company, 'truck', $truck->id, 'fitness', '2026-11-05', ['number' => 'FIT-1']);
    app(CompanyContext::class)->runAs($company, fn () => $doc->forceFill(['last_reminder_days' => 15])->save());

    $this->post(route('documents.renew', $doc), ['number' => 'FIT-2', 'expires_on' => '2026-10-30'])->assertSessionHasErrors('expires_on');
    $this->post(route('documents.renew', $doc), ['number' => 'FIT-2', 'valid_from' => '2026-11-06', 'expires_on' => '2027-11-05', 'files' => [docPhoto('fitness.jpg', 800, 600)]])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($company);
    $doc->refresh();
    expect($doc->version)->toBe(2)->and($doc->number)->toBe('FIT-2')->and($doc->last_reminder_days)->toBeNull()
        ->and($doc->versions()->first()->number)->toBe('FIT-1')
        ->and($doc->files()->where('version', 2)->count())->toBe(1)
        ->and(AuditLog::where('action', 'document.renewed')->exists())->toBeTrue();
});

it('counts expired and due papers for the dashboard', function () {
    $company = makeCompany();
    $truck = makeTruck([], $company);
    docFor($company, 'truck', $truck->id, 'insurance', '2026-10-20');
    docFor($company, 'truck', $truck->id, 'puc', '2026-10-30');
    docFor($company, 'truck', $truck->id, 'fitness', '2026-11-08');
    docFor($company, 'truck', $truck->id, 'permit_np', '2026-11-20');
    docFor($company, 'truck', $truck->id, 'road_tax', '2027-06-01');

    $counts = app(CompanyContext::class)->runAs($company, fn () => app(Compliance::class)->counts());
    expect($counts)->toMatchArray(['expired' => 1, 'd7' => 1, 'd15' => 1, 'd30' => 1, 'missing' => 0]);

    signInTo($company);
    $this->get(route('documents.index'))->assertOk()->assertSee('Expired 5 days ago')->assertDontSee('Road tax');
    $this->get(route('documents.index', ['show' => 'all']))->assertOk()->assertSee('Road tax');
});

it('shows missing papers and blocks dispatch for an expired or missing paper', function () {
    $company = makeCompany();
    $truck = makeTruck(['truck_type' => 'w12'], $company);
    $other = makeTruck(['reg_no' => 'KL-07-ZZ-0002', 'truck_type' => 'w10'], $company);
    $driver = makePerson(['licence_no' => null], $company);
    app(CompanyContext::class)->runAs($company, function () {
        DocumentRequiredType::create(['applies_to' => 'truck', 'subtype' => null, 'document_type' => 'insurance']);
        DocumentRequiredType::create(['applies_to' => 'truck', 'subtype' => 'w12', 'document_type' => 'permit_np']);
        DocumentRequiredType::create(['applies_to' => 'truck', 'subtype' => null, 'document_type' => 'road_tax']);
    });
    docFor($company, 'truck', $truck->id, 'insurance', '2026-10-01');

    app(CompanyContext::class)->runAs($company, function () use ($truck, $other, $driver) {
        $c = app(Compliance::class);
        expect($c->missing($truck))->toEqualCanonicalizing(['permit_np', 'road_tax'])
            ->and($c->missing($other))->toEqualCanonicalizing(['insurance', 'road_tax'])
            ->and(collect($c->blockers($truck))->pluck('reason', 'type')->all())->toBe(['insurance' => 'expired', 'permit_np' => 'missing'])
            ->and($c->health($truck))->toBe('red')
            ->and(collect($c->blockers($driver))->pluck('reason', 'type')->all())->toBe(['licence' => 'missing'])
            ->and(count($c->missingAll()))->toBe(4);
    });
});

it('keeps personal ID papers to people allowed to see them, and logs every look', function () {
    Storage::fake('local');
    $company = makeCompany();
    $person  = makePerson([], $company);
    $aadhaar = docFor($company, 'person', $person->id, 'aadhaar', null, ['number' => 'XXXX XXXX 1234']);
    app(CompanyContext::class)->runAs($company, fn () => app(\App\Modules\Documents\Services\DocumentFiles::class)->store($aadhaar, docPhoto('aadhaar.jpg', 600, 400)));
    $file = app(CompanyContext::class)->runAs($company, fn () => DocumentFile::first());

    signInTo($company, 'manager');
    $this->get(route('documents.owner', ['person', $person->id]))->assertOk()->assertDontSee('Aadhaar');
    $this->get(route('documents.show', $aadhaar))->assertForbidden();
    $this->get(route('documents.file', $file))->assertForbidden();

    signInTo($company);
    $this->get(route('documents.file', $file))->assertOk();
    app(CompanyContext::class)->set($company);
    expect(AuditLog::where('action', 'document.file_viewed')->value('severity'))->toBe('sensitive');
});

it('keeps a driver\'s licence fields in step with the licence document', function () {
    $company = makeCompany();
    signInTo($company);
    $person = makePerson(['licence_no' => 'OLD123', 'licence_expiry' => '2026-01-01'], $company);

    $this->post(route('documents.store', ['person', $person->id]), ['document_type' => 'licence', 'number' => 'kl07 2015 0012345', 'expires_on' => '2031-02-01'])->assertSessionHasNoErrors();
    expect($person->fresh()->licence_no)->toBe('KL0720150012345')->and($person->fresh()->licence_expiry->toDateString())->toBe('2031-02-01');

    // The person form no longer changes them.
    $this->put(route('people.update', $person), ['name' => 'Ravi Kumar', 'type' => 'driver', 'mobile' => '+919847011111', 'pay_basis' => 'monthly', 'licence_no' => 'X', 'licence_expiry' => '2020-01-01'])->assertSessionHasNoErrors();
    expect($person->fresh()->licence_no)->toBe('KL0720150012345');
});

it('works out the reminder steps', function () {
    expect(DocumentReminders::step(45, 30))->toBeNull()
        ->and(DocumentReminders::step(30, 30))->toBe(30)
        ->and(DocumentReminders::step(12, 30))->toBe(15)
        ->and(DocumentReminders::step(7, 30))->toBe(7)
        ->and(DocumentReminders::step(1, 30))->toBe(1)
        ->and(DocumentReminders::step(0, 30))->toBe(0)
        ->and(DocumentReminders::step(-3, 30))->toBe(0)
        ->and(DocumentReminders::step(-9, 30))->toBe(-7)
        ->and(DocumentReminders::step(20, 15))->toBeNull()
        ->and(DocumentReminders::step(5, 0))->toBeNull()
        ->and(DocumentReminders::step(null, 30))->toBeNull();
});

it('emails each reminder step once, to people who manage documents, and respects a snooze', function () {
    $company = makeCompany();
    $owner = makeAccount(['email' => 'owner@sharma.test']);
    joinCompany($owner, $company, 'owner');
    $viewer = makeAccount(['email' => 'viewer@sharma.test']);
    joinCompany($viewer, $company, 'viewer');
    $truck = makeTruck([], $company);
    $insurance = docFor($company, 'truck', $truck->id, 'insurance', '2026-11-20');
    $puc       = docFor($company, 'truck', $truck->id, 'puc', '2026-11-01');

    $run = fn () => app(DocumentReminders::class)->runFor($company);

    expect($run())->toBe(2)->and($run())->toBe(0);

    // One email (the array mailer keeps it), to the owner only: a viewer cannot manage documents.
    $sent = app('mailer')->getSymfonyTransport()->messages();
    expect($sent)->toHaveCount(1);
    $to = collect($sent[0]->getOriginalMessage()->getTo())->map->getAddress()->all();
    expect($to)->toBe(['owner@sharma.test'])
        ->and($sent[0]->getOriginalMessage()->getTextBody())->toContain('KL-07-AB-1234')->toContain('Pollution certificate');
    app(CompanyContext::class)->set($company);
    expect($insurance->fresh()->last_reminder_days)->toBe(30)->and($puc->fresh()->last_reminder_days)->toBe(7);

    // 14 days before the insurance, 5 days after the PUC expired: both move on a step.
    $this->travelTo('2026-11-06 09:00:00');
    expect($run())->toBe(2);
    app(CompanyContext::class)->set($company);
    expect($insurance->fresh()->last_reminder_days)->toBe(15)->and($puc->fresh()->last_reminder_days)->toBe(0);

    app(DocumentService::class)->snooze($puc->fresh(), '2026-11-30', 'Renewal at the agent');
    $this->travelTo('2026-11-14 09:00:00');
    // The insurance (6 days) moves to 7; the PUC (a week after expiry) is snoozed.
    expect($run())->toBe(1);
});

it('shares a pack by an expiring link with watermarked images, never personal ID papers', function () {
    Storage::fake('local');
    $company = makeCompany();
    signInTo($company);
    $truck  = makeTruck([], $company);
    $person = makePerson([], $company);
    $rc = docFor($company, 'truck', $truck->id, 'rc', null, ['number' => 'RC-1']);
    $file = app(CompanyContext::class)->runAs($company, fn () => app(\App\Modules\Documents\Services\DocumentFiles::class)->store($rc, docPhoto('rc.jpg', 800, 500)));
    $aadhaar = docFor($company, 'person', $person->id, 'aadhaar', null);

    $this->post(route('documents.shares.store'), ['documents' => [$aadhaar->id], 'purpose' => 'Gate entry', 'days' => 3])->assertSessionHas('error');
    $this->post(route('documents.shares.store'), ['documents' => [$rc->id, $aadhaar->id], 'purpose' => 'ACC plant gate entry', 'days' => 3])->assertSessionHas('share_link');
    $link = session('share_link');
    $token = basename($link);

    auth()->logout();
    $this->get($link)->assertOk()->assertSee('ACC plant gate entry')->assertSee('RC-1')->assertDontSee('Aadhaar');
    $image = $this->get(route('share.file', [$token, $file->id]))->assertOk()->assertHeader('Content-Type', 'image/jpeg')->getContent();
    expect($image)->not->toBe(Storage::disk('local')->get($file->path));

    $share = DocumentShare::withoutGlobalScopes()->first();
    expect($share->opened_count)->toBe(1)->and($share->document_ids)->toBe([$rc->id]);

    $this->travel(4)->days();
    $this->get($link)->assertStatus(410);
    $this->get(route('share.file', [$token, $file->id]))->assertStatus(410);
});

it('lets the owner set required papers that match their owner kind', function () {
    $company = makeCompany();
    signInTo($company);

    $this->post(route('documents.required.store'), ['applies_to' => 'truck', 'document_type' => 'licence'])->assertSessionHasErrors('document_type');
    $this->post(route('documents.required.store'), ['applies_to' => 'truck', 'subtype' => 'driver', 'document_type' => 'insurance'])->assertSessionHasErrors('subtype');
    $this->post(route('documents.required.store'), ['applies_to' => 'truck', 'subtype' => 'w12', 'document_type' => 'permit_np'])->assertSessionHasNoErrors();
    $this->post(route('documents.required.store'), ['applies_to' => 'person', 'subtype' => 'driver', 'document_type' => 'licence'])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($company);
    expect(DocumentRequiredType::count())->toBe(2);
    $this->get(route('documents.required'))->assertOk()->assertSee('National permit');
});
