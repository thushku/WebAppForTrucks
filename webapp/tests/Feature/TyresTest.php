<?php

use App\Modules\Fleet\Models\Truck;
use App\Modules\Fleet\Models\Tyre;
use App\Modules\Fleet\Models\TyreFitment;
use App\Modules\Fleet\Services\Tyres;
use App\Modules\Trips\Models\Trip;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Str;

// The tyre register (M38): tyres bought into the store, fitted by position
// from an odometer reading, moved, swapped, taken off, retreaded and
// scrapped; their km run and cost per km; and the wear of a trip's km.

/** A company and a 10-wheeler at 1,00,000 km. */
function tyWorld(): array
{
    $company = makeCompany();
    $owner = signInTo($company);
    $truck = app(CompanyContext::class)->runAs($company, fn () => makeTruck(['reg_no' => 'KL 07 TY 1000', 'wheels' => 10, 'odometer_km' => 100000]));

    return ['company' => $company, 'owner' => $owner, 'truck' => $truck];
}

/** The store's tyre with this serial number. */
function tySerial(array $w, string $serial): Tyre
{
    return app(CompanyContext::class)->runAs($w['company'], fn () => Tyre::where('serial_no', $serial)->firstOrFail());
}

it('lays out the wheel positions from the wheel count', function () {
    $w = tyWorld();
    app(CompanyContext::class)->set($w['company']);
    $tyres = app(Tyres::class);
    $layout = fn (int $wheels) => $tyres->positions(new Truck(['wheels' => $wheels]));

    expect($layout(10))->toBe(['F1-L', 'F1-R', 'R1-LO', 'R1-LI', 'R1-RI', 'R1-RO', 'R2-LO', 'R2-LI', 'R2-RI', 'R2-RO', 'SP'])
        ->and($layout(6))->toBe(['F1-L', 'F1-R', 'R1-LO', 'R1-LI', 'R1-RI', 'R1-RO', 'SP'])
        ->and($layout(4))->toBe(['F1-L', 'F1-R', 'R1-L', 'R1-R', 'SP'])
        ->and(array_slice($layout(12), 0, 4))->toBe(['F1-L', 'F1-R', 'F2-L', 'F2-R']);
    foreach ([6, 10, 12, 14, 16, 18, 22] as $wheels) {
        expect(count($layout($wheels)))->toBe($wheels + 1);
    }
    expect($tyres->positionName('R2-LI'))->toBe('Rear 2, left inner')->and($tyres->positionName('F2-R'))->toBe('Second front, right wheel');
});

it('keeps each tyre from the store to the truck, the retreader and the scrap', function () {
    $w = tyWorld();
    $truck = $w['truck'];

    // A pair bought together; a serial number already in the register is refused.
    $this->post(route('trucks.tyres.store'), ['serials' => "mrf-1\nMRF-2", 'brand' => 'MRF', 'size' => '10.00 R20', 'kind' => 'new', 'cost' => 20000, 'expected_km' => 80000])
        ->assertSessionHasNoErrors()->assertRedirect(route('trucks.tyres', ['status' => 'in_store']));
    $this->post(route('trucks.tyres.store'), ['serials' => 'MRF-2', 'brand' => 'MRF', 'size' => '10.00 R20', 'kind' => 'new', 'cost' => 20000])->assertSessionHasErrors('serials');
    $one = tySerial($w, 'MRF-1');
    $two = tySerial($w, 'MRF-2');
    expect($one->costPerKm())->toBe(0.25);

    // Fitted at R1-LO; the position is then taken, and the truck has no R3.
    $fit = fn (Tyre $t, string $pos, int $km) => $this->post(route('trucks.tyres.fit', $truck), ['tyre_id' => $t->id, 'position' => $pos, 'fitted_on' => '2026-10-20', 'odometer' => $km]);
    $fit($one, 'R1-LO', 100000)->assertSessionHasNoErrors()->assertRedirect(route('trucks.show', [$truck, 'tab' => 'tyres']));
    $fit($two, 'R1-LO', 100000)->assertSessionHasErrors('position');
    $fit($two, 'R3-LO', 100000)->assertSessionHasErrors('position');
    $fit($one, 'R1-LI', 100000)->assertSessionHasErrors('tyre_id');
    $this->get(route('trucks.show', [$truck, 'tab' => 'tyres']))->assertOk()->assertSeeText('MRF-1')->assertSeeText('Rear 1, left outer')->assertSeeText('1 of 11 positions fitted');

    // Moved to R1-LI at 1,01,000 km: 1,000 km on the first fitment.
    $this->post(route('trucks.tyres.move', [$truck, $one]), ['position' => 'R1-LI', 'moved_on' => '2026-10-22', 'odometer' => 99000])->assertSessionHasErrors('odometer');
    $this->post(route('trucks.tyres.move', [$truck, $one]), ['position' => 'R1-LI', 'moved_on' => '2026-10-22', 'odometer' => 101000])->assertSessionHasNoErrors();
    // The second at R1-LO, then swapped with the first.
    $fit($two, 'R1-LO', 101000)->assertSessionHasNoErrors();
    $this->post(route('trucks.tyres.move', [$truck, $two]), ['position' => 'R1-LI', 'moved_on' => '2026-10-23', 'odometer' => 101500])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect([$one->fresh()->position, $two->fresh()->position])->toBe(['R1-LO', 'R1-LI'])
        ->and(TyreFitment::where('tyre_id', $one->id)->orderBy('id')->pluck('km')->all())->toBe([1000, 500, null]);

    // Off for retreading at 1,30,000 km; back with ₹6,000 and 40,000 km more; then scrapped from the store.
    $this->post(route('trucks.tyres.off', [$truck, $one]), ['removed_on' => '2026-10-26', 'odometer' => 130000, 'reason' => 'retread'])->assertSessionHasErrors('on');
    $this->post(route('trucks.tyres.off', [$truck, $one]), ['removed_on' => '2026-10-25', 'odometer' => 130000, 'reason' => 'retread'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect($one->fresh()->only(['status', 'truck_id', 'position', 'km_run']))->toBe(['status' => 'retreading', 'truck_id' => null, 'position' => null, 'km_run' => 30000]);
    $this->post(route('trucks.tyres.returned', $one), ['returned_on' => '2026-10-25', 'cost' => 6000, 'expected_km' => 40000])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $back = $one->fresh();
    expect([$back->status, $back->kind, $back->retreads, (float) $back->cost, $back->expected_km, $back->costPerKm()])->toBe(['in_store', 'retread', 1, 26000.0, 120000, 0.2167]);
    $this->post(route('trucks.tyres.scrap', $one), ['scrapped_on' => '2026-10-25', 'scrap_value' => 2000])->assertSessionHasNoErrors();
    $this->get(route('trucks.tyres.show', $one))->assertOk()->assertSeeText('Scrapped')->assertSeeText('30,000')->assertSeeText('₹0.80')->assertSeeText('Sent for retreading');

    // The store's page: the brand's actual cost per km from the finished tyre.
    $this->get(route('trucks.tyres'))->assertOk()->assertSeeText('MRF-2')->assertSeeText('KL 07 TY 1000')->assertSeeText('What each brand gives')->assertSeeText('30,000');
});

it('charges a trip its tyres’ wear for its km and warns of tyres near their end', function () {
    $w = invWorld();
    $trip = app(CompanyContext::class)->runAs($w['company'], fn () => Trip::with('truck')->findOrFail($w['loads'][0]->trip_id));
    // invWorld's trip ran from 1,00,100 to 1,01,500 km (1,400 km).
    app(CompanyContext::class)->runAs($w['company'], function () use ($w, $trip) {
        $tyres = app(Tyres::class);
        $set = $tyres->add(['serials' => "W1\nW2\nW3\nW4\nW5", 'brand' => 'Apollo', 'size' => '295/80 R22.5', 'kind' => 'new', 'cost' => 20000, 'expected_km' => 80000], $w['owner']);
        foreach (['F1-L', 'F1-R', 'R1-LO'] as $i => $pos) {
            $tyres->fit($set[$i], $trip->truck, $pos, '2026-10-20', 100000, $w['owner']);
        }
        // One went on halfway (700 km of the trip), and the spare runs none.
        $tyres->fit($set[3], $trip->truck, 'R1-LI', '2026-10-24', 100800, $w['owner']);
        $tyres->fit($set[4], $trip->truck, 'SP', '2026-10-20', 100000, $w['owner']);
        expect($tyres->wearFor($trip))->toBe(['km' => 1400, 'tyres' => 4, 'amount' => 1225.0]);
    });

    $this->get(route('trips.show', $trip))->assertOk()->assertSeeText('Tyre wear, 1,400 km (estimate)')->assertSeeText('₹1,225.00')
        ->assertSeeText('Profit after the truck’s costs')->assertSeeText('₹62,775.00');
    $this->get(route('reports.index', ['tab' => 'lanes', 'from' => '2026-10-01', 'to' => '2026-10-31']))->assertSeeText('after truck costs ₹62,775.00');

    // A tyre past 90 % of its km shows on the dashboard.
    app(CompanyContext::class)->runAs($w['company'], fn () => Tyre::where('serial_no', 'W1')->update(['km_run' => 71000]));
    $this->get(route('dashboard'))->assertSeeText('1 tyre is near the end of its expected km');
    $this->get(route('trucks.tyres'))->assertSeeText('1 tyre is near the end of its expected km');

    phoneReset();
    $this->withHeaders(['Authorization' => 'Bearer ' . $w['owner']->createToken('owner:android')->plainTextToken, 'X-Company' => $w['company']->uuid, 'Accept' => 'application/json']);
    $this->getJson('/api/v1/owner/trips/' . $trip->uuid)->assertOk()->assertJsonPath('tyre_wear.amount', 1225)->assertJsonPath('tyre_wear.tyres', 4);
    $this->getJson('/api/v1/owner/reports/routes')->assertJsonPath('routes.0.tyres', 1225)->assertJsonPath('routes.0.net', 62775);
});

it('lets the owner app keep the tyres, each move once per key', function () {
    $w = tyWorld();
    phoneReset();
    $this->withHeaders(['Authorization' => 'Bearer ' . $w['owner']->createToken('owner:android')->plainTextToken, 'X-Company' => $w['company']->uuid, 'Accept' => 'application/json']);

    $key = (string) Str::uuid();
    $body = ['serials' => ['JK-11', 'JK-12'], 'brand' => 'JK Tyre', 'size' => '10.00 R20', 'kind' => 'new', 'cost' => 19800, 'expected_km' => 80000];
    $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/owner/tyres', $body)->assertCreated()->assertJsonPath('tyres.1.serial_no', 'JK-12')->assertJsonPath('tyres.0.status', 'in_store');
    $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/owner/tyres', $body)->assertJsonPath('replayed', true);
    $jk = tySerial($w, 'JK-11');

    $fitKey = (string) Str::uuid();
    $this->withHeader('Idempotency-Key', $fitKey)->postJson('/api/v1/owner/trucks/' . $w['truck']->id . '/tyres', ['tyre_id' => $jk->id, 'position' => 'F1-L'])
        ->assertCreated()->assertJsonPath('tyre.position', 'F1-L')->assertJsonPath('tyre.fitted_odo', 100000)->assertJsonPath('tyre.truck.reg_no', 'KL 07 TY 1000');
    $this->withHeader('Idempotency-Key', $fitKey)->postJson('/api/v1/owner/trucks/' . $w['truck']->id . '/tyres', ['tyre_id' => $jk->id, 'position' => 'F1-L'])->assertJsonPath('replayed', true);
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/owner/trucks/' . $w['truck']->id . '/tyres', ['tyre_id' => tySerial($w, 'JK-12')->id, 'position' => 'F1-L'])
        ->assertStatus(422);

    $this->getJson('/api/v1/owner/trucks/' . $w['truck']->id . '/tyres')->assertOk()->assertJsonPath('axles.0.positions.0.tyre.serial_no', 'JK-11')
        ->assertJsonPath('axles.0.positions.0.name', 'Front, left wheel')->assertJsonPath('store.0.serial_no', 'JK-12')->assertJsonPath('history.0.serial_no', 'JK-11');
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/owner/trucks/' . $w['truck']->id . '/tyres/' . $jk->id . '/off', ['reason' => 'burst', 'odometer' => 104200, 'scrap_value' => 500])
        ->assertCreated()->assertJsonPath('tyre.status', 'scrapped')->assertJsonPath('tyre.km_run', 4200);
    $this->getJson('/api/v1/owner/tyres?status=scrapped')->assertOk()->assertJsonPath('tyres.0.serial_no', 'JK-11');
    $this->getJson('/api/v1/owner/tyres/' . $jk->id)->assertOk()->assertJsonPath('tyre.fitments.0.reason_label', 'Burst or cut')->assertJsonPath('tyre.scrap_value', 500);
});
