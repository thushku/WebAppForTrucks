<?php

use App\Models\Account;
use App\Modules\Documents\Models\Document;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\People\Models\Person;
use App\Modules\Trips\Models\Consignment;
use App\Modules\Trips\Models\Trip;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// The owner app making things (M23, api-v1 §12): a trip sent to the crew,
// bills from LRs, money received and a paper photographed — the web's rules
// and permissions, and every write run once per Idempotency-Key.

/** The owner app signed in for someone with this role; their next requests come from it. */
function owPhone($company, string $role = 'owner'): Account
{
    phoneReset();
    $account = makeAccount(['name' => $role === 'owner' ? 'Anil Sharma' : 'Office ' . $role]);
    joinCompany($account, $company, $role);
    test()->withHeaders(['Authorization' => 'Bearer ' . $account->createToken('owner:android')->plainTextToken, 'X-Company' => $company->uuid, 'Accept' => 'application/json']);

    return $account;
}

/** A write from the phone, with its run-once key. */
function owPost(string $url, array $body, ?string $key = null)
{
    return test()->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())->postJson($url, $body);
}

it('makes a trip from the phone and sends it to the crew, once per key', function () {
    $w = invWorld();
    owPhone($w['company']);
    app(CompanyContext::class)->set($w['company']);
    $truck = Truck::sole();
    $driver = Person::where('name', 'Driver 1')->sole();
    $cok = $w['loads'][0]->trip->from_place_id;
    $bom = $w['loads'][0]->trip->to_place_id;

    $masters = $this->getJson('/api/v1/owner/masters')->assertOk()->json();
    expect(collect($masters['places'])->pluck('name')->all())->toContain('Kochi', 'Mumbai')
        ->and(collect($masters['clients'])->pluck('name')->all())->toBe(['ACC Limited'])
        ->and(collect($masters['trucks'])->firstWhere('id', $truck->id))->toMatchArray(['reg_no' => $truck->reg_no, 'busy_on' => null])
        ->and(collect($masters['crew'])->pluck('name')->all())->toContain('Driver 1')
        ->and($masters['freight_bases'])->toHaveKey('tonne')->and($masters['paper_types']['truck'])->toHaveKey('insurance');

    $trip = ['trip_date' => '2026-10-26', 'truck_id' => $truck->id, 'driver_id' => $driver->id, 'from_place_id' => $cok, 'to_place_id' => $bom, 'planned_km' => 1380, 'send' => true,
        'load' => ['billing_party_id' => $w['client']->id, 'material' => 'cement', 'actual_weight_mt' => 18, 'freight_basis' => 'tonne', 'rate' => 3400, 'payment_type' => 'to_be_billed', 'gst_payable_by' => 'consignor']];

    // No key, no write.
    $this->postJson('/api/v1/owner/trips', $trip)->assertStatus(428);
    // Refused: nothing is kept, the same key goes again once it is right.
    $key = (string) Str::uuid();
    owPost('/api/v1/owner/trips', ['truck_id' => null] + $trip, $key)->assertStatus(422)->assertJsonValidationErrors('truck_id');
    $made = owPost('/api/v1/owner/trips', $trip, $key)->assertCreated()->json('trip');
    expect($made)->toMatchArray(['status' => 'assigned', 'from' => 'Kochi', 'to' => 'Mumbai', 'vehicle' => $truck->reg_no, 'date' => '2026-10-26'])
        ->and($made['number'])->not->toBeNull()
        ->and($made['loads'][0])->toMatchArray(['lr_number' => 'LR/26-27/00002', 'freight' => 61200.0]);

    // The answer was lost: the same key gives the same trip, not a second one.
    $again = owPost('/api/v1/owner/trips', $trip, $key)->assertCreated()->assertJsonPath('replayed', true)->json('trip');
    app(CompanyContext::class)->set($w['company']);
    expect($again['uuid'])->toBe($made['uuid'])->and(Trip::count())->toBe(2);
    // A key is for one action only.
    owPost('/api/v1/owner/receipts', ['party_id' => $w['client']->id, 'receipt_date' => '2026-10-25', 'amount' => 1, 'mode' => 'cash'], $key)->assertStatus(409);

    // The truck is on that trip now: sending a second one with it is refused, and nothing is left half made.
    owPost('/api/v1/owner/trips', ['trip_date' => '2026-10-27'] + $trip)->assertStatus(422);
    app(CompanyContext::class)->set($w['company']);
    expect(Trip::count())->toBe(2);
    // A draft, sent later from the phone.
    $draft = owPost('/api/v1/owner/trips', ['send' => false, 'trip_kind' => 'empty', 'trip_date' => '2026-10-28', 'truck_id' => $truck->id] + collect($trip)->except('load')->all())->assertCreated()->json('trip');
    expect([$draft['status'], $draft['number'], $draft['loads']])->toBe(['draft', null, []]);
    owPost("/api/v1/owner/trips/{$draft['uuid']}/send", [])->assertStatus(422);

    // Who may: a viewer makes no trips; a manager does.
    owPhone($w['company'], 'viewer');
    owPost('/api/v1/owner/trips', $trip)->assertForbidden();
    $this->getJson('/api/v1/owner/masters')->assertForbidden();
});

it('bills LRs and records money received from the phone, against the oldest bill', function () {
    $w = invWorld(loads: 2);
    owPhone($w['company']);
    [$one, $two] = $w['loads'];

    $waiting = $this->getJson('/api/v1/owner/unbilled?party=' . $w['client']->id)->assertOk()->json('loads');
    expect(collect($waiting)->pluck('lr_number')->all())->toBe([$one->lr_number, $two->lr_number])
        ->and($waiting[0])->toMatchArray(['party' => 'ACC Limited', 'route' => 'Kochi → Mumbai', 'freight' => 62900.0]);

    // The first LR billed and issued at once; the second as a draft, issued afterwards.
    $key = (string) Str::uuid();
    $bill = owPost('/api/v1/owner/invoices', ['party_id' => $w['client']->id, 'loads' => [$one->id], 'finalise' => true], $key)->assertCreated()->json('invoice');
    expect($bill)->toMatchArray(['number' => 'INV/26-27/00001', 'status' => 'finalised', 'total' => 75520.0, 'party' => 'ACC Limited'])
        ->and($bill['share_url'])->not->toBeNull();
    expect(owPost('/api/v1/owner/invoices', ['party_id' => $w['client']->id, 'loads' => [$one->id], 'finalise' => true], $key)->json('invoice.id'))->toBe($bill['id']);
    $draft = owPost('/api/v1/owner/invoices', ['party_id' => $w['client']->id, 'loads' => [$two->id]])->assertCreated()->json('invoice');
    expect([$draft['status'], $draft['number']])->toBe(['draft', null]);
    owPost('/api/v1/owner/invoices', ['party_id' => $w['client']->id, 'loads' => [$one->id]])->assertStatus(422)->assertJsonValidationErrors('loads');
    owPost("/api/v1/owner/invoices/{$draft['id']}/finalise", [])->assertCreated()->assertJsonPath('invoice.number', 'INV/26-27/00002');
    owPost("/api/v1/owner/invoices/{$draft['id']}/finalise", [])->assertStatus(422);
    $this->getJson('/api/v1/owner/unbilled')->assertOk()->assertJsonCount(0, 'loads');

    // Money received by NEFT with TDS: set against the oldest bill first.
    owPost('/api/v1/owner/receipts', ['party_id' => $w['client']->id, 'receipt_date' => '2026-10-25', 'amount' => 70000, 'mode' => 'neft'])->assertStatus(422)->assertJsonValidationErrors('reference');
    $receipt = owPost('/api/v1/owner/receipts', ['party_id' => $w['client']->id, 'receipt_date' => '2026-10-25', 'amount' => 70000, 'tds_amount' => 1280, 'tds_section' => '393(1)', 'mode' => 'neft', 'reference' => 'UTR77'])
        ->assertCreated()->json('receipt');
    expect($receipt)->toMatchArray(['number' => 'RCT/26-27/00001', 'amount' => 70000.0, 'date' => '2026-10-25'])
        ->and($receipt['applied'][0]['invoice'])->toBe('INV/26-27/00001');
    app(CompanyContext::class)->set($w['company']);
    expect((float) Invoice::where('number', 'INV/26-27/00001')->value('balance_due'))->toBe(4240.0)
        ->and(\App\Modules\Invoicing\Models\Receipt::sole()->narration)->toBe('Received, entered on the owner app');

    // A manager may draft bills but not issue them; a viewer records no money.
    owPhone($w['company'], 'manager');
    owPost('/api/v1/owner/receipts', ['party_id' => $w['client']->id, 'receipt_date' => '2026-10-25', 'amount' => 1, 'mode' => 'cash'])->assertForbidden();
    owPhone($w['company'], 'viewer');
    $this->getJson('/api/v1/owner/unbilled')->assertForbidden();
});

it('adds a paper photographed on the phone to a truck', function () {
    Storage::fake('local');
    $w = invWorld();
    owPhone($w['company']);
    app(CompanyContext::class)->set($w['company']);
    $truck = Truck::sole();
    $photo = UploadedFile::fake()->createWithContent('insurance.pdf', "%PDF-1.4\n% policy\n");

    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->post('/api/v1/owner/papers', ['owner_type' => 'truck', 'owner_id' => $truck->id, 'document_type' => 'insurance', 'number' => 'POL-2026-88', 'expires_on' => '2027-03-31'], ['Accept' => 'application/json'])
        ->assertStatus(422)->assertJsonValidationErrors('files');
    $paper = $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->post('/api/v1/owner/papers', ['owner_type' => 'truck', 'owner_id' => $truck->id, 'document_type' => 'insurance', 'number' => 'POL-2026-88', 'expires_on' => '2027-03-31', 'files' => [$photo]], ['Accept' => 'application/json'])
        ->assertCreated()->json('paper');
    expect($paper)->toMatchArray(['type' => 'insurance', 'number' => 'POL-2026-88', 'expires_on' => '2027-03-31', 'files' => 1]);
    app(CompanyContext::class)->set($w['company']);
    expect(Document::where('owner_type', 'truck')->where('owner_id', $truck->id)->where('document_type', 'insurance')->count())->toBe(1);

    // ID papers only for those who may see them; another company's truck is not found.
    owPhone($w['company'], 'manager');
    expect($this->getJson('/api/v1/owner/masters')->json('paper_types.person'))->not->toHaveKey('aadhaar');
    $other = makeCompany();
    $theirs = app(CompanyContext::class)->runAs($other, fn () => makeTruck(['reg_no' => 'KA-01-ZZ-9999']));
    owPhone($w['company']);
    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->post('/api/v1/owner/papers', ['owner_type' => 'truck', 'owner_id' => $theirs->id, 'document_type' => 'insurance', 'files' => [$photo]], ['Accept' => 'application/json'])
        ->assertStatus(422)->assertJsonValidationErrors('owner_id');
});
