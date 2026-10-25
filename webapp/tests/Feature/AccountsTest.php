<?php

use App\Http\Middleware\ResolveCompany;
use App\Models\Account;
use App\Modules\Accounts\Models\LedgerAccount;
use App\Modules\Accounts\Models\Voucher;
use App\Modules\Accounts\Models\VoucherLine;
use App\Modules\Accounts\Services\Books;
use App\Modules\Accounts\Services\ChartOfAccounts;
use App\Modules\Accounts\Services\Statements;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\Receipt;
use App\Modules\Road\Models\RoadEvent;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

// The accounting core (M11): every money record posts one balanced voucher
// by itself; cancels are reversals; locked days never change; the ledgers,
// trial balance, profit and loss, balance sheet and truck profit are read
// from the book.

/** A ledger's balance by its system key, debit positive (the context must be set). */
function accBalance(string $key, ?string $bucket = null): float
{
    return round((float) VoucherLine::whereHas('ledger', fn ($q) => $q->where('system_key', $key))
        ->when($bucket, fn ($q) => $q->where('bucket', $bucket))->sum(DB::raw('debit - credit')), 2);
}

function accOffice(Account $account, $company): void
{
    phoneReset();
    test()->actingAs($account, 'web')->withSession([ResolveCompany::SESSION_KEY => $company->id]);
}

function accMember(array $w, string $role): Account
{
    $account = makeAccount(['name' => 'Office ' . $role]);
    joinCompany($account, $w['company'], $role);

    return $account;
}

/** A downloaded CSV as rows of cells. */
function accCsv(string $content): array
{
    return array_map('str_getcsv', explode("\n", trim(str_replace("\xEF\xBB\xBF", '', $content))));
}

function accBalanced(): bool
{
    $t = VoucherLine::selectRaw('sum(debit) as d, sum(credit) as c')->first();

    return abs((float) $t->d - (float) $t->c) < 0.0005;
}

it('posts the crew’s money: advance, spending by who paid, money from the party, settlement and bata', function () {
    $w = phoneWorld();
    $phones = phoneCrew($w);
    $owner = accMember($w, 'owner');

    accOffice($owner, $w['company']);
    $this->post(route('trips.advances.store', $w['trip']), ['person_id' => $w['driver']->id, 'amount' => 20000, 'mode' => 'cash', 'given_on' => '2026-10-25'])->assertSessionHasNoErrors();

    phoneAs($phones['driver'], $w['company']);
    phoneStart($w);
    $driver = phonePush([
        phoneEvent($w, 'fuel', ['amount' => 12400, 'payment_mode' => 'cash', 'data' => ['litres' => 130.5, 'pump' => 'IOC Thrissur']]),
        phoneEvent($w, 'toll', ['amount' => 1850, 'payment_mode' => 'cash', 'data' => ['plaza' => 'Paliyekkara']]),
        phoneEvent($w, 'fuel', ['amount' => 10000, 'payment_mode' => 'fuel_card', 'data' => ['litres' => 105, 'pump' => 'HP Salem']]),
        phoneEvent($w, 'money_received', ['amount' => 10000, 'payment_mode' => 'cash', 'data' => ['from' => 'party', 'reference' => 'Freight balance']]),
        phoneEvent($w, 'toll', ['amount' => 500, 'payment_mode' => 'cash', 'data' => ['plaza' => 'Walayar']]),
    ]);
    phoneAs($phones['co'], $w['company']);
    $coFood = phonePush([phoneEvent($w, 'food', ['amount' => 850, 'payment_mode' => 'upi', 'data' => ['what' => 'Food, two days']])])[0];
    $this->travel(20)->hours();
    phoneAs($phones['driver'], $w['company']);
    phoneRun([phoneCommand($w, 'trip.end', ['event' => ['event_at' => now()->toIso8601String(), 'odometer' => 101630]])]);

    accOffice($owner, $w['company']);
    foreach ([$driver[0]['id'], $driver[1]['id'], $driver[2]['id'], $driver[3]['id'], $coFood['id']] as $id) {
        $this->post(route('approvals.decide', $id), ['decision' => 'approve'])->assertSessionHasNoErrors();
    }
    $this->post(route('approvals.decide', $driver[4]['id']), ['decision' => 'reject', 'note' => 'Paid by FASTag'])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($w['company']);
    $truck = $w['truck']->id;
    // Approved spending is a cost of the trip and truck; a rejected one posts nothing.
    expect(accBalance('expense:fuel'))->toBe(22400.0)
        ->and(accBalance('expense:toll'))->toBe(1850.0)
        ->and(accBalance('fuel_card'))->toBe(-10000.0)
        ->and(accBalance('person:' . $w['driver']->id, 'trip_cash'))->toBe(15750.0)      // 20,000 + 10,000 − 12,400 − 1,850
        ->and(accBalance('party:' . $w['trip']->consignments->first()->billing_party_id))->toBe(-10000.0)
        ->and(VoucherLine::whereHas('ledger', fn ($q) => $q->where('system_key', 'expense:fuel'))->pluck('truck_id')->unique()->all())->toBe([$truck])
        ->and(Voucher::where('source_type', 'road_event')->where('source_id', $driver[4]['id'])->exists())->toBeFalse();

    $this->post(route('trips.settle', [$w['trip'], $w['driver']]), ['mode' => 'cash_returned', 'reference' => 'Receipt 118'])->assertSessionHasNoErrors();
    $this->post(route('trips.settle', [$w['trip'], $w['co']]), ['mode' => 'paid_to_crew', 'bata_amount' => 1500])->assertSessionHasNoErrors();
    $this->post(route('trips.settle', [$w['trip'], $w['helper']]), ['mode' => 'nil'])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($w['company']);
    $ravi = LedgerAccount::where('system_key', 'person:' . $w['driver']->id)->first();
    expect(app(Statements::class)->crewBuckets($ravi))->toBe(['trip_cash' => 0.0, 'earnings' => -1200.0, 'net' => -1200.0])
        ->and(accBalance('person:' . $w['co']->id))->toBe(-1500.0)
        ->and(accBalance('person:' . $w['helper']->id))->toBe(-800.0)
        ->and(accBalance('bata'))->toBe(3500.0)
        ->and(accBalance('cash'))->toBe(-5100.0)                                               // −20,000 + 15,750 − 850
        ->and(\App\Modules\Trips\Models\TripSettlement::whereNotNull('voucher_id')->count())->toBe(3)
        ->and(app(Statements::class)->tripProfit($w['trip']->id))->toBe(['income' => 0.0, 'expense' => 28600.0, 'profit' => -28600.0])
        ->and(accBalanced())->toBeTrue();

    // The crew ledger in plain words, its buckets and statement.
    $this->get(route('ledgers.show', $ravi))->assertOk()->assertSeeText('You will give Ravi Kumar ₹1,200.00')->assertSeeText('Trip cash')
        ->assertSeeText('Trip advance to Ravi Kumar')->assertSeeText('Settlement of Ravi Kumar');
    $this->get(route('ledgers.index', ['tab' => 'crew']))->assertOk()->assertSeeText('Suresh Nair')->assertSeeText('You will give Binu Joseph ₹800.00');
    $this->get(route('people.show', $w['driver']))->assertOk()->assertSee(route('ledgers.show', $ravi));

    // An approved entry the office takes back is reversed, not deleted.
    app(CompanyContext::class)->set($w['company']);
    $fuel = RoadEvent::find($driver[0]['id']);
    $fuel->forceFill(['status' => 'cancelled', 'cancel_reason' => 'Entered twice'])->save();
    expect(accBalance('expense:fuel'))->toBe(10000.0)
        ->and(Voucher::where('source_type', 'road_event')->where('source_id', $fuel->id)->pluck('voucher_type', 'status')->all())->toBe(['cancelled' => 'expense', 'posted' => 'reversal']);
});

it('posts a bill and its receipt with TDS; voiding reverses; a posted voucher is never edited', function () {
    $w = invWorld(client: ['tds_deducted' => true]);
    $invoice = invDraft($w);
    $this->post(route('invoices.finalise', $invoice))->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($w['company']);
    $client = 'party:' . $w['client']->id;
    $sale = Voucher::where('source_type', 'invoice')->firstOrFail();
    expect([$sale->voucher_type, $sale->reference, (float) $sale->total])->toBe(['sales', 'INV/26-27/00001', 75520.0])
        ->and(accBalance($client))->toBe(75520.0)
        ->and(accBalance('freight_income'))->toBe(-62900.0)
        ->and(accBalance('output_tax:CGST'))->toBe(-5760.0)
        ->and(accBalance('output_tax:SGST'))->toBe(-5760.0)
        ->and(VoucherLine::whereHas('ledger', fn ($q) => $q->where('system_key', 'freight_income'))->first()->only(['trip_id', 'truck_id', 'consignment_id']))
        ->toBe(['trip_id' => $w['loads'][0]->trip_id, 'truck_id' => $w['loads'][0]->trip->truck_id, 'consignment_id' => $w['loads'][0]->id]);

    expect(fn () => $sale->forceFill(['total' => 1])->save())->toThrow(RuntimeException::class, 'A posted voucher is never edited')
        ->and(fn () => $sale->delete())->toThrow(RuntimeException::class, 'never deleted')
        ->and(fn () => $sale->lines()->first()->delete())->toThrow(RuntimeException::class);

    // 75,520 settled by 70,000 money + 1,280 TDS + 4,240 shortage.
    $this->post(route('receipts.store'), ['party_id' => $w['client']->id, 'receipt_date' => '2026-10-25', 'amount' => 70000, 'tds_amount' => 1280, 'tds_section' => '393(1)',
        'deduction_amount' => 4240, 'deduction_note' => 'Shortage', 'mode' => 'neft', 'reference' => 'UTR77', 'narration' => 'October', 'allocation' => 'oldest'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect(accBalance($client))->toBe(0.0)->and(accBalance('bank'))->toBe(70000.0)->and(accBalance('tds_receivable'))->toBe(1280.0)->and(accBalance('client_deductions'))->toBe(4240.0);

    $this->post(route('receipts.void', Receipt::first()), ['reason' => 'Cheque bounced'])->assertSessionHasNoErrors();
    $this->post(route('invoices.void', $invoice), ['reason' => 'Billed to the wrong GSTIN'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect(accBalance($client))->toBe(0.0)->and(accBalance('freight_income'))->toBe(0.0)->and(accBalance('bank'))->toBe(0.0)
        ->and($sale->fresh()->status)->toBe('cancelled')
        ->and(Voucher::where('voucher_type', 'reversal')->count())->toBe(2)
        ->and(accBalanced())->toBeTrue();
});

it('keeps locked days unchanged: late entries and reversals land on the first open day', function () {
    $w = invWorld();
    $owner = $w['owner'];
    $invoice = invDraft($w);
    $this->post(route('invoices.finalise', $invoice));

    $this->put(route('accounts.lock.update'), ['locked_until' => '2026-10-25'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect(app(Books::class)->lockedUntil()?->toDateString())->toBe('2026-10-25');

    // A journal for a locked day posts the next day, with its own date kept.
    $chart = app(ChartOfAccounts::class);
    $lines = [['ledger_account_id' => $chart->expense('office')->id, 'debit' => 350], ['ledger_account_id' => $chart->ledger('cash')->id, 'credit' => 350]];
    $this->post(route('accounts.journal.store'), ['voucher_date' => '2026-10-20', 'narration' => 'Bank charges for October', 'lines' => $lines])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $journal = Voucher::where('voucher_type', 'journal')->firstOrFail();
    expect([$journal->voucher_date->toDateString(), $journal->document_date->toDateString(), $journal->number])->toBe(['2026-10-26', '2026-10-20', 'JV/26-27/00001']);

    // Voiding the bill of a locked day: the reversal is in the open period; the locked day still shows the sale.
    $this->post(route('invoices.void', $invoice), ['reason' => 'Wrong client'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $reversal = Voucher::where('voucher_type', 'reversal')->firstOrFail();
    expect($reversal->voucher_date->toDateString())->toBe('2026-10-26')
        ->and(app(Statements::class)->trialBalance(CarbonImmutable::parse('2026-10-25'))['debit'])->toBe(75520.0);

    // Unbalanced journals are refused; a journal is reversed by hand, a system voucher only from its document.
    $this->post(route('accounts.journal.store'), ['voucher_date' => '2026-10-26', 'narration' => 'Wrong one', 'lines' => [['ledger_account_id' => $lines[0]['ledger_account_id'], 'debit' => 100], ['ledger_account_id' => $lines[1]['ledger_account_id'], 'credit' => 90]]])
        ->assertSessionHasErrors('lines');
    $this->post(route('accounts.vouchers.reverse', $journal), ['reason' => 'Posted to the wrong ledger'])->assertSessionHasNoErrors();
    $this->post(route('accounts.vouchers.reverse', $reversal->reverses_voucher_id), ['reason' => 'Trying it'])->assertSessionHasErrors('voucher');
    app(CompanyContext::class)->set($w['company']);
    expect($journal->fresh()->status)->toBe('cancelled')->and(accBalance('cash'))->toBe(0.0)->and(accBalanced())->toBeTrue();

    // Opening locked days again needs a reason; every lock is kept.
    $this->put(route('accounts.lock.update'), ['locked_until' => ''])->assertSessionHasErrors('reason');
    $this->put(route('accounts.lock.update'), ['locked_until' => '', 'reason' => 'GST return corrected'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect(app(Books::class)->lockedUntil())->toBeNull()
        ->and(\App\Platform\Audit\AuditLog::where('action', 'books.unlocked')->exists())->toBeTrue();
});

it('shows the book: overview, day book, trial balance, profit and loss, balance sheet, truck profit, chart; exports CSV', function () {
    $w = invWorld(client: ['name' => '=HYPERLINK("x")']);
    $invoice = invDraft($w);
    $this->post(route('invoices.finalise', $invoice));
    app(CompanyContext::class)->set($w['company']);
    $truck = \App\Modules\Trips\Models\Trip::whereKey($w['loads'][0]->trip_id)->value('truck_id');

    $this->get(route('accounts.index'))->assertOk()->assertSeeText('Debits and credits agree: ₹75,520.00 on each side.')->assertSeeText('₹64,000.00');
    $this->get(route('accounts.day-book'))->assertOk()->assertSeeText('INV/26-27/00001')->assertSeeText('Output CGST');
    $this->get(route('accounts.trial-balance'))->assertOk()->assertSeeText('Balanced')->assertSeeText('Freight income');
    $this->get(route('accounts.profit-loss', ['truck_id' => $truck]))->assertOk()->assertSeeText('Profit')->assertSeeText('₹64,000.00');
    $this->get(route('accounts.balance-sheet'))->assertOk()->assertSeeText('Profit so far')->assertSeeText('Sundry debtors');
    $this->get(route('accounts.trucks', ['truck' => $truck]))->assertOk()->assertSeeText('KL-07-AB-1210')->assertSeeText('month by month');
    $this->get(route('accounts.chart'))->assertOk()->assertSeeText('Crew accounts')->assertSeeText('Duties and taxes');
    app(CompanyContext::class)->set($w['company']);
    $voucher = Voucher::firstOrFail();
    $this->get(route('accounts.vouchers.show', $voucher))->assertOk()->assertSeeText('The system, from the document')->assertSee(route('invoices.show', $invoice));

    app(CompanyContext::class)->set($w['company']);
    $client = LedgerAccount::where('system_key', 'party:' . $w['client']->id)->firstOrFail();
    $this->get(route('ledgers.show', $client))->assertOk()->assertSeeText('will give you ₹75,520.00');
    $csv = $this->get(route('ledgers.export', $client))->assertOk()->streamedContent();
    expect($csv)->toContain('Opening balance')->toContain('INV/26-27/00001')->toContain('75520');
    $day = $this->get(route('accounts.day-book', ['format' => 'csv']))->assertOk()->streamedContent();
    expect($day)->toContain("'=HYPERLINK")->not->toContain(',=HYPERLINK');
    expect($this->get(route('accounts.trial-balance', ['format' => 'csv']))->streamedContent())->toContain('Total,75520,75520');

    // The accountant adds their own ledgers.
    app(CompanyContext::class)->set($w['company']);
    $capital = \App\Modules\Accounts\Models\AccountGroup::where('code', 'capital')->value('id');
    $this->post(route('accounts.chart.store'), ['name' => 'Capital — Anil Sharma', 'account_group_id' => $capital, 'opening_balance' => 500000, 'opening_side' => 'cr'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect((float) LedgerAccount::where('name', 'Capital — Anil Sharma')->value('opening_balance'))->toBe(-500000.0);
});

it('gives the book to those allowed: ledgers for managers, the book for accountants, the lock for the owner', function () {
    $w = invWorld();
    $invoice = invDraft($w);
    $this->post(route('invoices.finalise', $invoice));

    foreach (['viewer', 'manager'] as $role) {
        signInTo($w['company'], $role);
        $this->get(route('ledgers.index'))->assertOk();
        $this->get(route('accounts.index'))->assertForbidden();
    }
    signInTo($w['company'], 'accountant');
    $this->get(route('accounts.index'))->assertOk();
    $this->get(route('accounts.journal.create'))->assertOk();
    $this->get(route('accounts.lock'))->assertForbidden();

    signInTo($w['company'], 'driver');
    $this->get(route('ledgers.index'))->assertForbidden();
});

it('posts what was recorded before the books existed, once, and checks the book balances', function () {
    $w = invWorld();
    $invoice = invDraft($w);
    $this->post(route('invoices.finalise', $invoice));
    app(CompanyContext::class)->set($w['company']);
    expect(Voucher::count())->toBe(1);

    // As if the bill was issued before M11: no voucher.
    DB::table('voucher_lines')->delete();
    DB::table('vouchers')->delete();

    $this->artisan('fleet:books', ['--company' => $w['company']->id])->expectsOutputToContain('1 new voucher(s); debits 75,520.00, credits 75,520.00 — balanced')->assertSuccessful();
    $this->artisan('fleet:books', ['--company' => $w['company']->id])->expectsOutputToContain('0 new voucher(s)')->assertSuccessful();
    app(CompanyContext::class)->set($w['company']);
    expect(Voucher::count())->toBe(1)->and(accBalance('party:' . $w['client']->id))->toBe(75520.0);
});

it('pays a vendor and a crew member’s bata by payment voucher', function () {
    $w = invWorld();
    app(CompanyContext::class)->set($w['company']);
    $chart = app(ChartOfAccounts::class);
    $pump = makeParty(['name' => 'IOC Aluva'], ['vendor']);
    $driver = makePerson(['name' => 'Ravi Kumar', 'mobile' => '+919847099999']);
    $pumpLedger = $chart->party($pump);
    $raviLedger = $chart->person($driver);
    app(Books::class)->post(['type' => 'purchase', 'date' => '2026-10-20', 'narration' => 'Diesel bill'], [['ledger' => $chart->expense('fuel'), 'debit' => 42000], ['ledger' => $pumpLedger, 'credit' => 42000]]);
    app(Books::class)->post(['type' => 'journal', 'date' => '2026-10-20', 'narration' => 'Bata'], [['ledger' => $chart->ledger('bata'), 'debit' => 1200], ['ledger' => $raviLedger, 'credit' => 1200, 'bucket' => 'earnings']]);

    $this->get(route('ledgers.show', $pumpLedger))->assertOk()->assertSee(route('accounts.payments.create', ['ledger' => $pumpLedger->id]));
    $this->get(route('accounts.payments.create', ['ledger' => $pumpLedger->id]))->assertOk()->assertSeeText('IOC Aluva');
    $this->post(route('accounts.payments.store'), ['ledger_account_id' => $pumpLedger->id, 'amount' => 42000, 'paid_on' => '2026-10-25', 'mode' => 'bank'])->assertSessionHasErrors('reference');
    $this->post(route('accounts.payments.store'), ['ledger_account_id' => $pumpLedger->id, 'amount' => 42000, 'paid_on' => '2026-10-25', 'mode' => 'bank', 'reference' => 'UTR991', 'narration' => 'October diesel'])->assertSessionHasNoErrors();
    $this->post(route('accounts.payments.store'), ['ledger_account_id' => $raviLedger->id, 'amount' => 1200, 'paid_on' => '2026-10-25', 'mode' => 'cash', 'narration' => 'Bata, October trip'])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($w['company']);
    expect(Voucher::where('voucher_type', 'payment')->orderBy('id')->pluck('number')->all())->toBe(['PV/26-27/00001', 'PV/26-27/00002'])
        ->and(accBalance('party:' . $pump->id))->toBe(0.0)
        ->and(accBalance('person:' . $driver->id, 'earnings'))->toBe(0.0)
        ->and(accBalance('cash'))->toBe(-1200.0)
        ->and(accBalance('bank'))->toBe(-42000.0);
});

it('ages clients’ bills and the hire owed, and keeps the TDS register', function () {
    $w = invWorld(client: ['tds_deducted' => true], loads: 2);
    $first = invDraft($w, [$w['loads'][0]->id]);
    $this->post(route('invoices.finalise', $first));
    $second = invDraft($w, [$w['loads'][1]->id]);
    $this->post(route('invoices.finalise', $second));
    // Part of the first bill paid with TDS.
    $this->post(route('receipts.store'), ['party_id' => $w['client']->id, 'receipt_date' => '2026-10-25', 'amount' => 50000, 'tds_amount' => 1280, 'tds_section' => '393(1)',
        'mode' => 'neft', 'reference' => 'UTR1', 'narration' => 'Part', 'allocation' => 'oldest'])->assertSessionHasNoErrors();

    // Due 24 Nov: on 10 Jan both are 47 days overdue.
    $this->get(route('accounts.ageing', ['to' => '2027-01-10']))->assertOk()->assertSeeText('ACC Limited')->assertSeeText('47 days')->assertSeeText('₹99,760.00');
    $this->get(route('accounts.ageing', ['to' => '2026-10-30']))->assertOk()->assertSeeText('not due');
    $csv = $this->get(route('accounts.ageing', ['to' => '2027-01-10', 'format' => 'csv']))->streamedContent();
    expect(accCsv($csv)[1])->toBe(['ACC Limited', '0', '0', '99760', '0', '0', '99760']);

    $this->get(route('accounts.tds'))->assertOk()->assertSeeText('Q3 2026-27')->assertSeeText('INV/26-27/00001')->assertSeeText('₹1,280.00');
    expect(array_slice(accCsv($this->get(route('accounts.tds', ['format' => 'csv']))->streamedContent())[1], 0, 3))->toBe(['Q3 2026-27', 'ACC Limited', 'AAACA1234B']);
    $this->get(route('accounts.tds', ['tab' => 'payable']))->assertOk()->assertSeeText('No hire challans with TDS in this period.');
});

it('prepares GSTR-1 tables and the 3B working from the issued bills', function () {
    $w = invWorld();
    $invoice = invDraft($w);
    $this->post(route('invoices.finalise', $invoice));

    $this->get(route('accounts.tax', ['month' => '2026-10']))->assertOk()->assertSeeText('GSTR-1 for ' . $w['registration']->tax_id)
        ->assertSeeText('32-Kerala')->assertSeeText('₹64,000.00')->assertSeeText('CGST ₹5,760.00');
    $b2b = $this->get(route('accounts.tax', ['month' => '2026-10', 'section' => 'b2b']))->assertOk()->streamedContent();
    expect(accCsv($b2b)[0])->toBe(\App\Http\Controllers\Accounts\ReportsController::GSTR1_SHEETS['b2b'][0])
        ->and(accCsv($b2b)[1])->toBe([$w['client']->taxIds->first()->tax_id, 'ACC Limited', 'INV/26-27/00001', '25-Oct-2026', '75520', '32-Kerala', 'N', '', 'Regular B2B', '', '18', '64000', '0']);
    $hsn = $this->get(route('accounts.tax', ['month' => '2026-10', 'section' => 'hsn']))->streamedContent();
    expect($hsn)->toContain('996791')->toContain('5760');
    $docs = $this->get(route('accounts.tax', ['month' => '2026-10', 'section' => 'docs']))->streamedContent();
    expect(accCsv($docs)[1])->toBe(['Invoices for outward supply', 'INV/26-27/00001', 'INV/26-27/00001', '1', '0']);

    // Reverse charge: reported with RCM "Y", no tax of ours.
    $rcm = invWorld(gtaOption: null, clientState: '27');
    $bill = invDraft($rcm);
    $this->post(route('invoices.finalise', $bill));
    app(CompanyContext::class)->set($rcm['company']);
    $data = app(\App\Modules\Accounts\Services\TaxReturns::class)->gstr1($rcm['registration'], CarbonImmutable::parse('2026-10-01'));
    expect($data['b2b'][0])->toMatchArray(['rcm' => 'Y', 'pos' => '27-Maharashtra', 'taxable' => 64000.0])
        ->and($data['summary'])->toMatchArray(['taxable' => 0.0, 'rcm_taxable' => 64000.0, 'rcm_tax' => 3200.0, 'igst' => 0.0]);
});

it('writes the UAE VAT 201 output boxes', function () {
    $company = makeCompany(['name' => 'Gulf Haulage', 'country' => 'AE', 'currency' => 'AED', 'timezone' => 'Asia/Dubai', 'fy_start_month' => 1]);
    $owner = signInTo($company);
    $invoice = app(CompanyContext::class)->runAs($company, function () use ($owner) {
        \App\Modules\Tax\Models\TaxRegistration::create(['country' => 'AE', 'regime' => 'ae_vat', 'tax_id' => '100123456700003', 'state_code' => 'DU', 'is_default' => true]);
        $buyer = makeParty(['name' => 'Emirates Steel', 'country' => 'AE', 'state_code' => 'AZ'], ['client']);
        \App\Modules\Parties\Models\PartyTaxId::create(['party_id' => $buyer->id, 'country' => 'AE', 'tax_id' => '100987654300003', 'state_code' => 'AZ', 'is_default' => true]);
        $service = app(\App\Modules\Invoicing\Services\InvoiceService::class);
        $draft = $service->draftBlank($buyer->fresh('taxIds'), $owner);
        $service->updateDraft($draft, ['issue_date' => '2026-10-25', 'due_date' => '2026-11-24', 'transport_type' => 'domestic', 'carrier_role' => 'main', 'discount_type' => 'none',
            'lines' => [['service_class' => 'FREIGHT_ROAD', 'description' => 'Freight Dubai – Abu Dhabi', 'quantity' => 1, 'unit_rate' => 10000]]]);

        return $service->finalise($draft->fresh(), $owner);
    });

    expect((float) $invoice->tax_total)->toBe(500.0);
    $this->get(route('accounts.tax', ['month' => '2026-10']))->assertOk()->assertSeeText('VAT 201 for 100123456700003')
        ->assertSeeText('Standard-rated supplies in Dubai')->assertSeeText('AED 10,000.00')->assertSeeText('AED 500.00');
    app(CompanyContext::class)->set($company);
    expect(accBalance('output_tax:VAT'))->toBe(-500.0);
});

it('exports the book for Tally: masters under Tally’s groups, vouchers with signed amounts', function () {
    $w = invWorld();
    $invoice = invDraft($w);
    $this->post(route('invoices.finalise', $invoice));
    app(CompanyContext::class)->runAs($w['company'], fn () => app(ChartOfAccounts::class)->ensure());

    $xml = $this->get(route('accounts.tally'))->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
    $doc = simplexml_load_string($xml);
    expect($doc)->not->toBeFalse()
        ->and((string) $doc->HEADER->TALLYREQUEST)->toBe('Import Data');
    $ledgers = collect($doc->xpath('//LEDGER'))->mapWithKeys(fn ($l) => [(string) $l['NAME'] => (string) $l->PARENT]);
    expect($ledgers['ACC Limited'])->toBe('Sundry Debtors')->and($ledgers['Output CGST'])->toBe('Duties & Taxes')
        ->and(collect($doc->xpath('//GROUP'))->mapWithKeys(fn ($g) => [(string) $g['NAME'] => (string) $g->PARENT])->all())->toMatchArray(['Crew accounts' => 'Loans & Advances (Asset)', 'Truck maintenance' => 'Direct Expenses']);
    $sale = $doc->xpath('//VOUCHER[@VCHTYPE="Sales"]')[0];
    expect((string) $sale->VOUCHERNUMBER)->toBe('V/26-27/000001')
        ->and(collect($sale->xpath('ALLLEDGERENTRIES.LIST'))->mapWithKeys(fn ($e) => [(string) $e->LEDGERNAME => (string) $e->AMOUNT])->all())
        ->toMatchArray(['ACC Limited' => '-75520.00', 'Freight income' => '62900.00', 'Output CGST' => '5760.00']);

    signInTo($w['company'], 'manager');
    $this->get(route('accounts.tally'))->assertForbidden();
});

it('dates a voucher on the company’s clock, and ages bills in whole calendar days', function () {
    $company = makeCompany(['timezone' => 'Asia/Kolkata']);
    app(CompanyContext::class)->set($company);
    $books = app(Books::class);

    // 20:00 UTC on 25 Oct is 01:30 on 26 Oct in Kochi; a plain date stays as it is.
    expect($books->localDate(CarbonImmutable::parse('2026-10-25 20:00:00', 'UTC'))->toDateString())->toBe('2026-10-26')
        ->and($books->localDate(CarbonImmutable::parse('2026-10-25 10:00:00', 'UTC'))->toDateString())->toBe('2026-10-25')
        ->and($books->localDate(CarbonImmutable::parse('2026-10-25'))->toDateString())->toBe('2026-10-25')
        ->and($books->localDate('2026-10-25')->toDateString())->toBe('2026-10-25');
});
