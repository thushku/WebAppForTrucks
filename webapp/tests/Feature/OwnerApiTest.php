<?php

use App\Models\Account;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentFile;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// The owner app's API (M15): the dashboard, map, trips, trucks, papers, bills
// and ledgers — the web's numbers and permissions, for a phone.

/** A phone signed in for someone with a role in the company. */
function ownerPhone($company, string $role = 'owner'): Account
{
    phoneReset();
    $account = makeAccount(['name' => $role === 'owner' ? 'Anil Sharma' : 'Office ' . $role]);
    joinCompany($account, $company, $role);
    test()->withHeaders(['Authorization' => 'Bearer ' . $account->createToken('owner:android')->plainTextToken, 'X-Company' => $company->uuid, 'Accept' => 'application/json']);

    return $account;
}

/** A trip on the road with fuel and a toll entered and three GPS points. */
function ownerRunning(): array
{
    $w = phoneWorld();
    $phones = phoneCrew($w);
    phoneAs($phones['driver'], $w['company']);
    phoneStart($w);
    phonePush([
        phoneEvent($w, 'fuel', ['amount' => 9500, 'payment_mode' => 'cash', 'odometer' => 100300, 'data' => ['litres' => 100, 'rate' => 95, 'pump' => 'HP Aluva']]),
        phoneEvent($w, 'toll', ['amount' => 455, 'payment_mode' => 'fastag', 'lat' => 10.41, 'lng' => 76.28, 'data' => ['plaza' => 'Paliyekkara']]),
    ]);
    test()->travel(5)->minutes();
    test()->postJson('/api/v1/crew/gps', ['batch' => (string) Str::uuid(), 'points' => [
        ['t' => now()->subMinutes(2)->toIso8601String(), 'lat' => 9.9816, 'lng' => 76.2999, 'acc' => 12, 'spd' => 0],
        ['t' => now()->subMinute()->toIso8601String(), 'lat' => 9.99, 'lng' => 76.305, 'acc' => 10, 'spd' => 52],
        ['t' => now()->toIso8601String(), 'lat' => 9.999, 'lng' => 76.31, 'acc' => 9, 'spd' => 60],
    ]])->assertOk();

    return $w + ['phones' => $phones];
}

it('gives the owner the dashboard, the map and the trips, money only to those who may see it', function () {
    $w = ownerRunning();
    ownerPhone($w['company']);

    $dash = $this->getJson('/api/v1/owner/dashboard')->assertOk()->json();
    expect($dash['counts']['trips']['running'])->toBe(1)->and($dash['profit'])->toBeArray()
        ->and(collect($dash['spending_today'])->firstWhere('label', 'Fuel')['amount'])->toEqual(9500)
        ->and($dash['company'])->toMatchArray(['name' => 'Sharma Roadlines', 'currency' => 'INR', 'read_only' => false]);

    $positions = $this->getJson('/api/v1/owner/map')->assertOk()->json('positions');
    expect($positions)->toHaveCount(1)->and($positions[0])->toMatchArray(['trip' => $w['trip']->trip_number, 'driver' => 'Ravi Kumar', 'lat' => 9.999])
        ->and($positions[0]['trip_uuid'])->toBe($w['trip']->uuid)->and($positions[0])->not->toHaveKey('url')->not->toHaveKey('trip_id');

    $list = $this->getJson('/api/v1/owner/trips?status=running')->assertOk()->json();
    expect($list['trips'])->toHaveCount(1)->and($list['trips'][0])->toMatchArray(['uuid' => $w['trip']->uuid, 'status' => 'running', 'from' => 'Kochi', 'to' => 'Mumbai', 'driver' => 'Ravi Kumar'])
        ->and($list['more'])->toBeFalse();
    expect($this->getJson('/api/v1/owner/trips?status=closed')->json('trips'))->toBe([]);
    expect($this->getJson('/api/v1/owner/trips?q=' . $w['truck']->reg_no)->json('trips'))->toHaveCount(1);

    $trip = $this->getJson('/api/v1/owner/trips/' . $w['trip']->uuid)->assertOk()->json();
    // The start of the trip, the fuel and the toll; the two with money wait for the office.
    expect($trip['entries'])->toMatchArray(['count' => 3, 'to_check' => 2])->and($trip['entries']['spent'])->toEqual(9955)
        ->and(collect($trip['crew'])->firstWhere('role', 'driver'))->toMatchArray(['name' => 'Ravi Kumar', 'answer' => 'accepted'])
        ->and($trip['stops'])->not->toBeEmpty()->and($trip['profit'])->toBeArray()->and($trip['route_url'])->toBe(route('api.owner.trips.route', $w['trip']->uuid));
    expect($this->getJson($trip['route_url'])->assertOk()->json('total'))->toBe(3);

    $trucks = $this->getJson('/api/v1/owner/trucks')->assertOk()->json('trucks');
    expect($trucks[0])->toMatchArray(['reg_no' => $w['truck']->reg_no, 'status' => 'on_trip', 'status_label' => 'On trip']);

    // The workshop sees operations but not money; the crew see none of it.
    ownerPhone($w['company'], 'workshop');
    expect($this->getJson('/api/v1/owner/dashboard')->assertOk()->json('profit'))->toBeNull();
    $this->getJson('/api/v1/owner/invoices')->assertForbidden();
    phoneAs($w['phones']['helper'], $w['company']);
    $this->getJson('/api/v1/owner/dashboard')->assertForbidden();
});

it('lists the papers that expire, keeps ID papers to those allowed and logs every look at one', function () {
    Storage::fake('local');
    $company = makeCompany();
    $truck = makeTruck([], $company);
    $person = makePerson([], $company);
    [$insurance, $aadhaar] = app(CompanyContext::class)->runAs($company, function () use ($truck, $person) {
        $insurance = Document::create(['owner_type' => 'truck', 'owner_id' => $truck->id, 'document_type' => 'insurance', 'number' => 'POL-778', 'expires_on' => now()->addDays(10)->toDateString()]);
        $aadhaar = Document::create(['owner_type' => 'person', 'owner_id' => $person->id, 'document_type' => 'aadhaar', 'number' => 'XXXX-1234', 'expires_on' => now()->subDay()->toDateString()]);
        Storage::disk('local')->put('companies/x/documents/a.jpg', 'aadhaar-bytes');
        $file = DocumentFile::create(['document_id' => $aadhaar->id, 'version' => 1, 'path' => 'companies/x/documents/a.jpg', 'original_name' => 'aadhaar.jpg', 'mime' => 'image/jpeg', 'bytes' => 13, 'sha256' => hash('sha256', 'aadhaar-bytes')]);
        Document::create(['owner_type' => 'truck', 'owner_id' => $truck->id, 'document_type' => 'permit', 'number' => 'P-1', 'expires_on' => now()->addDays(200)->toDateString()]);

        return [$insurance, $aadhaar->setRelation('file', $file)];
    });

    ownerPhone($company);
    $papers = $this->getJson('/api/v1/owner/papers')->assertOk()->json('papers');
    expect(collect($papers)->pluck('number')->all())->toBe(['XXXX-1234', 'POL-778'])
        ->and($papers[0])->toMatchArray(['state' => 'expired', 'days_left' => -1, 'owner' => 'Ravi Kumar'])
        ->and($papers[1])->toMatchArray(['type' => 'insurance', 'owner' => $truck->reg_no, 'days_left' => 10]);
    expect($this->getJson('/api/v1/owner/papers?days=365')->json('papers'))->toHaveCount(3);

    $url = $papers[0]['files'][0]['url'];
    expect($this->get($url)->assertOk()->getContent())->toBe('aadhaar-bytes');
    expect(DB::table('audit_log')->where('action', 'document.file_viewed')->exists())->toBeTrue();

    // A viewer (no ID papers) sees the insurance only and cannot open the Aadhaar.
    ownerPhone($company, 'viewer');
    expect(collect($this->getJson('/api/v1/owner/papers')->json('papers'))->pluck('number')->all())->toBe(['POL-778']);
    $this->getJson($url)->assertForbidden();
});

it('lists bills with what is due, shares one on WhatsApp, and shows the ledgers with statements', function () {
    $w = invWorld();
    $invoice = invDraft($w);
    $this->post(route('invoices.finalise', $invoice))->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $invoice->refresh();
    $w['client']->forceFill(['mobile' => '+919847055555'])->save();

    ownerPhone($w['company']);
    $bills = $this->getJson('/api/v1/owner/invoices')->assertOk()->json('invoices');
    expect($bills)->toHaveCount(1)->and($bills[0])->toMatchArray(['number' => $invoice->number, 'party' => 'ACC Limited', 'status' => 'finalised', 'overdue_days' => 0])
        ->and($bills[0]['balance_due'])->toEqual((float) $invoice->balance_due)->and($bills[0]['share_url'])->toBe(route('documents.public', $invoice->public_token));
    expect($this->getJson('/api/v1/owner/invoices?tab=overdue')->json('invoices'))->toBe([]);
    $this->travel(40)->days();
    expect($this->getJson('/api/v1/owner/invoices?tab=overdue')->json('invoices.0.overdue_days'))->toBeGreaterThan(0);

    $detail = $this->getJson('/api/v1/owner/invoices/' . $invoice->id)->assertOk()->json();
    expect($detail['lines'])->not->toBeEmpty()->and($detail['total'])->toEqual((float) $invoice->total);

    $shared = $this->postJson('/api/v1/owner/invoices/' . $invoice->id . '/shared', ['channel' => 'whatsapp'])->assertOk()->json();
    expect($shared['status'])->toBe('sent')->and($shared['whatsapp_url'])->toStartWith('https://wa.me/919847055555?text=')
        ->and($shared['text'])->toContain($invoice->number)->toContain(route('documents.public', $invoice->public_token));

    $ledgers = collect($this->getJson('/api/v1/owner/ledgers?kind=party')->assertOk()->json('ledgers'));
    $acc = $ledgers->firstWhere('name', 'ACC Limited');
    expect($acc['balance'])->toEqual(round((float) $invoice->total, 2));
    $statement = $this->getJson('/api/v1/owner/ledgers/' . $acc['id'] . '?from=2026-10-01')->assertOk()->json();
    expect($statement['lines'])->toHaveCount(1)->and($statement['lines'][0]['reference'])->toBe($invoice->number)
        ->and($statement['closing'])->toEqual(round((float) $invoice->total, 2))->and($statement['buckets'])->toBeNull();

    // A viewer reads the bills but does not share them.
    ownerPhone($w['company'], 'viewer');
    $this->getJson('/api/v1/owner/invoices')->assertOk();
    $this->postJson('/api/v1/owner/invoices/' . $invoice->id . '/shared', ['channel' => 'link'])->assertForbidden();
});

it('makes an activation code from the command line, logged like the people page’s', function () {
    $company = makeCompany();
    makePerson(['name' => 'Ravi Kumar', 'mobile' => '+919847011111'], $company);

    $this->artisan('fleet:code', ['mobile' => '98470 11111', '--company' => $company->uuid])->expectsOutputToContain('Ravi Kumar (+919847011111) at Sharma Roadlines: activation code')->assertSuccessful();
    $this->artisan('fleet:code', ['mobile' => '9999', '--company' => $company->uuid])->assertFailed();
    expect(DB::table('audit_log')->where('action', 'person.activation_issued')->count())->toBe(1);
});

it('shows a crew member their own khata from the books: cash they hold, what is owed to them, the statement', function () {
    $w = phoneWorld();
    $phones = phoneCrew($w);
    phoneAs($phones['driver'], $w['company']);
    expect($this->getJson('/api/v1/crew/money')->assertOk()->json())->toMatchArray(['holding' => 0, 'owed_to_me' => 0, 'net' => 0, 'lines' => []]);

    // The office gives a ₹20,000 advance; the driver spends ₹12,400 on fuel, approved.
    $owner = makeAccount(['name' => 'Anil Sharma']);
    joinCompany($owner, $w['company'], 'owner');
    phoneReset();
    $this->actingAs($owner, 'web')->withSession([\App\Http\Middleware\ResolveCompany::SESSION_KEY => $w['company']->id]);
    $this->post(route('trips.advances.store', $w['trip']), ['person_id' => $w['driver']->id, 'amount' => 20000, 'mode' => 'cash', 'given_on' => '2026-10-25'])->assertSessionHasNoErrors();
    phoneAs($phones['driver'], $w['company']);
    phoneStart($w);
    $fuel = phonePush([phoneEvent($w, 'fuel', ['amount' => 12400, 'payment_mode' => 'cash', 'data' => ['litres' => 130.5, 'pump' => 'IOC Thrissur']])])[0];
    phoneReset();
    $this->actingAs($owner, 'web')->withSession([\App\Http\Middleware\ResolveCompany::SESSION_KEY => $w['company']->id]);
    $this->post(route('approvals.decide', $fuel['id']), ['decision' => 'approve'])->assertSessionHasNoErrors();

    phoneAs($phones['driver'], $w['company']);
    $money = $this->getJson('/api/v1/crew/money')->assertOk()->json();
    expect($money['holding'])->toEqual(7600)->and($money['net'])->toEqual(7600)->and($money['currency'])->toBe('INR')
        ->and(collect($money['lines'])->sum('given'))->toEqual(20000)->and(collect($money['lines'])->sum('credited'))->toEqual(12400)
        ->and(end($money['lines'])['balance'])->toEqual(7600);

    // Office people have no khata here.
    ownerPhone($w['company']);
    $this->getJson('/api/v1/crew/money')->assertForbidden()->assertJsonPath('code', 'not_crew');
});
