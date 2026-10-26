<?php

use App\Modules\Accounts\Models\CardStatement;
use App\Modules\Accounts\Models\CardStatementLine;
use App\Modules\Accounts\Models\Voucher;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\UploadedFile;

// FASTag and fuel-card statements (M44): the provider's CSV brought in once,
// each charge matched to the crew's card entry (same truck, same amount,
// within a day) or matched by hand, booked when nobody entered it, or set
// aside; card entries without a charge listed.

/** A FASTag statement as the portal gives it, for this truck. */
function csFile(string $reg): UploadedFile
{
    $csv = "FASTag statement for Sharma Roadlines\n\nTransaction Date & Time,Vehicle Number,Plaza Name,Transaction ID,Debit,Credit\n"
        . "25/10/2026 14:40:00,{$reg},Kumbalam,TXN1001,155.00,\n"
        . "25/10/2026 15:10:00,{$reg},Paliyekkara,TXN1002,255.00,\n"
        . "25/10/2026 16:00:00,{$reg},Pampampallam,TXN1003,95.00,\n"
        . "25/10/2026 16:30:00,,Recharge,TXN1004,,5000.00\n"
        . "25/10/2026 17:00:00,MH12XX9999,Vashi,TXN1005,120.00,\n";
    $path = tempnam(sys_get_temp_dir(), 'fastag');
    file_put_contents($path, $csv);

    return new UploadedFile($path, 'fastag-oct.csv', 'text/csv', null, true);
}

it('matches a FASTag statement to the crew’s entries and books what nobody entered', function () {
    $w = phoneWorld();
    $owner = signInTo($w['company']);
    $phones = phoneCrew($w);
    phoneAs($phones['driver'], $w['company']);
    phoneStart($w);
    // The driver's FASTag entries: 155 (the statement's), 250 (it says 255), 310 (not on the statement); and a cash toll.
    phonePush([
        phoneEvent($w, 'toll', ['amount' => 155, 'payment_mode' => 'fastag', 'data' => ['plaza' => 'Kumbalam']]),
        phoneEvent($w, 'toll', ['amount' => 250, 'payment_mode' => 'fastag', 'data' => ['plaza' => 'Paliyekkara']]),
        phoneEvent($w, 'toll', ['amount' => 310, 'payment_mode' => 'fastag', 'data' => ['plaza' => 'Walayar']]),
        phoneEvent($w, 'toll', ['amount' => 80, 'payment_mode' => 'cash', 'data' => ['plaza' => 'Old road']]),
    ]);
    phoneReset();
    $this->actingAs($owner, 'web')->withSession([\App\Http\Middleware\ResolveCompany::SESSION_KEY => $w['company']->id]);

    $this->post(route('accounts.cards.import'), ['kind' => 'fastag', 'file' => csFile($w['truck']->reg_no)])->assertSessionHasNoErrors()
        ->assertSessionHas('status', '4 charges brought in (0 seen before, left out); 1 matched to the crew’s entries, 3 to look at.');
    app(CompanyContext::class)->set($w['company']);
    $statement = CardStatement::sole();
    $line = fn (string $ref) => app(CompanyContext::class)->runAs($w['company'], fn () => CardStatementLine::where('reference', $ref)->firstOrFail());
    expect([$statement->period_from->toDateString(), $statement->lines])->toBe(['2026-10-25', 4])
        ->and($line('TXN1001')->only(['status', 'match_how', 'truck_id', 'trip_id']))->toBe(['status' => 'matched', 'match_how' => 'auto', 'truck_id' => $w['truck']->id, 'trip_id' => $w['trip']->id])
        ->and($line('TXN1005')->truck_id)->toBeNull();

    $this->get(route('accounts.cards.show', $statement))->assertOk()->assertSeeText('Kumbalam')->assertSeeText('Not one of our trucks')
        ->assertSeeText('2 entries say the card paid, but the statement has no charge')->assertSeeText('₹310.00');

    // 255 against the driver's 250: matched by hand. 95 nobody entered: booked as the truck's toll from the FASTag wallet. The other truck's: set aside.
    $wrong = app(CompanyContext::class)->runAs($w['company'], fn () => \App\Modules\Road\Models\RoadEvent::where('amount', 250)->value('id'));
    $this->post(route('accounts.cards.line', $line('TXN1002')), ['action' => 'match', 'event_id' => $wrong])->assertSessionHasNoErrors();
    $this->get(route('accounts.cards.show', $statement))->assertSeeText('1 entry says the card paid, but the statement has no charge');
    $this->post(route('accounts.cards.line', $line('TXN1003')), ['action' => 'book'])->assertSessionHasNoErrors();
    $this->post(route('accounts.cards.line', $line('TXN1005')), ['action' => 'ignore', 'note' => ''])->assertSessionHasErrors('note');
    $this->post(route('accounts.cards.line', $line('TXN1005')), ['action' => 'ignore', 'note' => 'Our tag on a hired truck'])->assertSessionHasNoErrors();
    $this->post(route('accounts.cards.line', $line('TXN1003')), ['action' => 'book'])->assertSessionHasErrors('line');

    app(CompanyContext::class)->set($w['company']);
    $voucher = Voucher::with('lines.ledger')->findOrFail($line('TXN1003')->voucher_id);
    $toll = $voucher->lines->firstWhere('debit', '>', 0);
    expect(CardStatementLine::pluck('status', 'reference')->all())->toBe(['TXN1001' => 'matched', 'TXN1002' => 'matched', 'TXN1003' => 'booked', 'TXN1005' => 'ignored'])
        ->and([(float) $toll->debit, $toll->truck_id, $toll->trip_id])->toBe([95.0, $w['truck']->id, $w['trip']->id])
        ->and($voucher->lines->firstWhere('credit', '>', 0)->ledger->system_key)->toBe('fastag');

    // The same file again brings nothing in twice.
    $this->post(route('accounts.cards.import'), ['kind' => 'fastag', 'file' => csFile($w['truck']->reg_no)])->assertSessionHasNoErrors()
        ->assertSessionHas('status', '0 charges brought in (4 seen before, left out); 0 matched to the crew’s entries, 0 to look at.');
    $this->post(route('accounts.cards.import'), ['kind' => 'fastag', 'file' => UploadedFile::fake()->createWithContent('x.csv', "Name,City\nA,B\n")])->assertSessionHasErrors('file');
});

it('checks a pump’s credit statement against the fuel taken on credit there', function () {
    $w = phoneWorld();
    $owner = signInTo($w['company']);
    $pump = app(CompanyContext::class)->runAs($w['company'], fn () => makeParty(['name' => 'Indian Oil Aluva', 'vendor_category' => 'fuel_pump'], ['vendor']));
    $phones = phoneCrew($w);
    phoneAs($phones['driver'], $w['company']);
    phoneStart($w);
    phonePush([
        phoneEvent($w, 'fuel', ['amount' => 9500, 'payment_mode' => 'credit', 'data' => ['litres' => 100, 'rate' => 95, 'vendor_party_id' => $pump->id]]),
        phoneEvent($w, 'fuel', ['amount' => 4750, 'payment_mode' => 'credit', 'data' => ['litres' => 50, 'rate' => 95, 'vendor_party_id' => $pump->id]]),
    ]);
    phoneReset();
    $this->actingAs($owner, 'web')->withSession([\App\Http\Middleware\ResolveCompany::SESSION_KEY => $w['company']->id]);

    $reg = $w['truck']->reg_no;
    $path = tempnam(sys_get_temp_dir(), 'pump');
    file_put_contents($path, "Bill Date,Vehicle No,Bill No,Amount\n25/10/2026 14:45,{$reg},1001,9500.00\n25/10/2026 18:00,{$reg},1002,3000.00\n");
    $file = fn () => new UploadedFile($path, 'ioc-aluva-oct.csv', 'text/csv', null, true);
    $this->post(route('accounts.cards.import'), ['kind' => 'pump', 'file' => $file()])->assertSessionHasErrors('vendor_party_id');
    $this->post(route('accounts.cards.import'), ['kind' => 'pump', 'vendor_party_id' => $pump->id, 'file' => $file()])->assertSessionHasNoErrors()
        ->assertSessionHas('status', '2 charges brought in (0 seen before, left out); 1 matched to the crew’s entries, 1 to look at.');

    app(CompanyContext::class)->set($w['company']);
    $statement = CardStatement::sole();
    $open = CardStatementLine::where('status', 'open')->sole();
    expect($open->reference)->toBe('p' . $pump->id . '-1002');
    $this->get(route('accounts.cards.show', $statement))->assertOk()->assertSeeText('1 entry says the card paid, but the statement has no charge')->assertSeeText('₹4,750.00');

    // Fuel nobody entered: the truck's fuel, owed to the pump.
    $this->post(route('accounts.cards.line', $open), ['action' => 'book'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $voucher = Voucher::with('lines.ledger')->findOrFail($open->fresh()->voucher_id);
    expect($voucher->lines->firstWhere('credit', '>', 0)->ledger->party_id)->toBe($pump->id)
        ->and($voucher->narration)->toContain('Fuel on credit at Indian Oil Aluva');
});
