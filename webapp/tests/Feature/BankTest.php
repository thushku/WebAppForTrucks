<?php

use App\Modules\Accounts\Models\BankStatementLine;
use App\Modules\Accounts\Models\LedgerAccount;
use App\Modules\Accounts\Models\Voucher;
use App\Modules\Accounts\Models\VoucherLine;
use App\Modules\Accounts\Services\Books;
use App\Modules\Accounts\Services\ChartOfAccounts;
use App\Modules\Accounts\Services\Statements;
use App\Modules\Invoicing\Models\DocumentDelivery;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

// The books' start and the bank (M19): opening balances typed or brought from
// a CSV, a party's statement printed, saved and emailed, and the bank's
// statement brought in and matched with the book.

/** A CSV upload made from lines of text. */
function bankCsv(string $name, array $lines): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, implode("\n", $lines) . "\n");
}

/**
 * A company with ₹1,00,000 in the bank to start and five entries on the bank
 * ledger in October: a receipt, a cheque for diesel, two payments of the same
 * ₹8,000 told apart only by their UTR, and a deposit the bank has not shown yet.
 */
function bankWorld(): array
{
    $w = invWorld();
    app(CompanyContext::class)->set($w['company']);
    $chart = app(ChartOfAccounts::class);
    $books = app(Books::class);
    $bank = $chart->ledger('bank');
    $client = $chart->party($w['client']);
    test()->put(route('accounts.opening.update'), ['dr' => [$bank->id => 100000]])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);

    $books->post(['type' => 'receipt', 'date' => '2026-10-20', 'reference' => 'UTR123456789', 'narration' => 'NEFT from ACC'], [['ledger' => $bank, 'debit' => 50000], ['ledger' => $client, 'credit' => 50000]]);
    $books->post(['type' => 'payment', 'date' => '2026-10-22', 'reference' => 'CHQ004512', 'narration' => 'Diesel, Indian Oil'], [['ledger' => $chart->expense('fuel'), 'debit' => 12000], ['ledger' => $bank, 'credit' => 12000]]);
    $books->post(['type' => 'payment', 'date' => '2026-10-23', 'reference' => 'UTR555111', 'narration' => 'Tyres'], [['ledger' => $chart->expense('office'), 'debit' => 8000], ['ledger' => $bank, 'credit' => 8000]]);
    $books->post(['type' => 'payment', 'date' => '2026-10-24', 'reference' => 'UTR555222', 'narration' => 'Yard rent'], [['ledger' => $chart->expense('office'), 'debit' => 8000], ['ledger' => $bank, 'credit' => 8000]]);
    $books->post(['type' => 'receipt', 'date' => '2026-10-24', 'reference' => 'CHQ778899', 'narration' => 'Cheque deposited, ACC'], [['ledger' => $bank, 'debit' => 3000], ['ledger' => $client, 'credit' => 3000]]);

    return $w + ['bank' => $bank->fresh(), 'client_ledger' => $client];
}

/** The bank's statement for those days, as an SBI-style download with lines above the header. */
function bankStatementFile(): UploadedFile
{
    return bankCsv('sbi-october.csv', [
        'State Bank of India',
        'Account Number,XXXXXXX1234',
        '',
        'Txn Date,Value Date,Description,Ref No./Cheque No.,Debit,Credit,Balance',
        '21/10/2026,21/10/2026,NEFT CR ACC LIMITED,UTR123456789,,"50,000.00","1,50,000.00"',
        '23/10/2026,23/10/2026,CHQ PAID 004512 INDIAN OIL,004512,"12,000.00",,"1,38,000.00"',
        '24/10/2026,24/10/2026,IMPS UTR555222 YARD,,"8,000.00",,"1,30,000.00"',
        '24/10/2026,24/10/2026,IMPS UTR555111 TYRES,,"8,000.00",,"1,22,000.00"',
        '25/10/2026,25/10/2026,SMS CHARGES,,8.85,,"1,21,991.15"',
        '25/10/2026,25/10/2026,SMS CHARGES,,8.85,,"1,21,982.30"',
        '25/10/2026,25/10/2026,CASH DEPOSIT SELF,,,500.00,"1,22,482.30"',
    ]);
}

it('keeps opening balances on the ledgers, puts what does not balance aside, and brings them from a CSV', function () {
    $w = invWorld();
    app(CompanyContext::class)->set($w['company']);
    $chart = app(ChartOfAccounts::class);
    $cash = $chart->ledger('cash');
    $bank = $chart->ledger('bank');
    $capital = $chart->ledger('owners_capital');

    $income = $chart->ledger('freight_income');
    $this->get(route('accounts.opening'))->assertOk()->assertSeeText('Opening balances')->assertSeeText('Bring them from Tally or Excel (CSV)')
        ->assertSeeText('Owner’s capital')->assertDontSeeText('Freight income');
    $this->put(route('accounts.opening.update'), ['dr' => [$cash->id => '5000', $bank->id => '100000', $income->id => '999'], 'cr' => [$capital->id => '80000']])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    // Income and expenses start the year at nothing.
    expect((float) $income->fresh()->opening_balance)->toBe(0.0);

    $difference = $chart->ledger('opening_difference');
    expect((float) $cash->fresh()->opening_balance)->toBe(5000.0)
        ->and($cash->fresh()->opening_date->toDateString())->toBe('2026-04-01')
        ->and((float) $capital->fresh()->opening_balance)->toBe(-80000.0)
        ->and((float) $difference->fresh()->opening_balance)->toBe(-25000.0);
    // The ledger and the trial balance start from them, and the trial balance agrees.
    $tb = app(Statements::class)->trialBalance(CarbonImmutable::parse('2026-10-25'));
    expect(app(Statements::class)->ledger($bank->fresh(), CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-25'))['opening'])->toBe(100000.0)
        ->and($tb['debit'])->toBe($tb['credit']);
    $this->get(route('accounts.opening'))->assertSeeText('more debit');

    // From Tally or Excel: names matched to ledgers and parties; the party gets its ledger; unknown names are listed.
    $file = bankCsv('opening.csv', ["\xEF\xBB\xBFName,Debit,Credit", 'ACC Limited,"25,000.00",', 'Nobody Transport,1000,']);
    $this->post(route('accounts.opening.import'), ['file' => $file])->assertSessionHasNoErrors()->assertSessionHas('unknown', ['Nobody Transport']);
    app(CompanyContext::class)->set($w['company']);
    expect((float) $chart->party($w['client'])->opening_balance)->toBe(25000.0)
        ->and((float) $difference->fresh()->opening_balance)->toBe(-50000.0);
    $this->post(route('accounts.opening.import'), ['file' => bankCsv('bad.csv', ['Who,What', 'x,y'])])->assertSessionHasErrors('file');

    // Locked books keep their opening balances.
    $this->put(route('accounts.lock.update'), ['locked_until' => '2026-10-25'])->assertSessionHasNoErrors();
    $this->put(route('accounts.opening.update'), ['dr' => [$cash->id => '9000']])->assertSessionHasErrors('opening');
    app(CompanyContext::class)->set($w['company']);
    expect((float) $cash->fresh()->opening_balance)->toBe(5000.0)
        ->and(\App\Platform\Audit\AuditLog::where('action', 'accounts.opening_saved')->count())->toBe(2);

    // Only those who post vouchers change them.
    signInTo($w['company'], 'viewer');
    $this->get(route('accounts.opening'))->assertForbidden();
});

it('prints a party’s statement on the letterhead and emails it with the company’s words', function () {
    config(['mail.default' => 'array', 'documents.pdf_driver' => 'browser']);
    app()->forgetInstance('mail.manager');
    app()->forgetInstance('mailer');
    \App\Modules\Invoicing\Services\PdfRenderer::reset();
    $w = invWorld();
    $invoice = invDraft($w);
    $this->post(route('invoices.finalise', $invoice))->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $w['client']->forceFill(['email' => 'accounts@acc.test'])->save();
    $ledger = app(ChartOfAccounts::class)->party($w['client']);
    $number = $invoice->fresh()->number;
    $period = ['from' => '2026-10-01', 'to' => '2026-10-25'];

    $this->get(route('ledgers.show', [$ledger] + $period))->assertOk()->assertSeeText('Email this statement')
        ->assertSee('value="accounts@acc.test"', false)->assertSee(e('Statement of account from Sharma Roadlines'), false);

    $this->get(route('ledgers.print', [$ledger] + $period))->assertOk()->assertSeeText('Statement of account')->assertSeeText('ACC Limited')
        ->assertSeeText($number)->assertSeeText('75,520.00')->assertSeeText('Sharma Roadlines');
    // Without Chromium on the server, "PDF" opens the page to print or save.
    $this->get(route('ledgers.pdf', [$ledger] + $period))->assertRedirect(route('ledgers.print', [$ledger] + $period));

    $this->post(route('ledgers.email', [$ledger] + $period), ['to_email' => 'not an email', 'subject' => 'x', 'body' => 'y'])->assertSessionHasErrors('to_email');
    $this->post(route('ledgers.email', [$ledger] + $period), ['to_email' => 'accounts@acc.test', 'subject' => 'Your statement', 'body' => 'Please find it attached.'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $sent = collect(app('mailer')->getSymfonyTransport()->messages())->map(fn ($m) => $m->getOriginalMessage());
    expect($sent)->toHaveCount(1)
        ->and($sent[0]->getTo()[0]->getAddress())->toBe('accounts@acc.test')
        ->and($sent[0]->getSubject())->toBe('Your statement')
        ->and($sent[0]->getAttachments()[0]->getFilename())->toBe('Statement-acc-limited.html')
        ->and(DocumentDelivery::where('document_type', 'statement')->where('document_id', $ledger->id)->value('status'))->toBe('sent');

    // A viewer reads the ledger but does not send it.
    signInTo($w['company'], 'viewer');
    $this->post(route('ledgers.email', [$ledger] + $period), ['to_email' => 'accounts@acc.test', 'subject' => 'x', 'body' => 'y'])->assertForbidden();
});

it('brings the bank’s statement in, leaves out lines seen before and matches what is clear', function () {
    $w = bankWorld();
    $bank = $w['bank'];

    $this->get(route('accounts.bank'))->assertOk()->assertSeeText('Bring it in')->assertSeeText('Nothing to match');
    $this->post(route('accounts.bank.import'), ['ledger_account_id' => $bank->id, 'file' => bankStatementFile()])->assertSessionHasNoErrors()
        ->assertSessionHas('status', '7 lines brought in (0 seen before, left out); 4 matched by themselves, 3 to look at.');
    app(CompanyContext::class)->set($w['company']);

    $lines = BankStatementLine::orderBy('id')->get();
    $matchedTo = fn (int $i) => VoucherLine::with('voucher')->find($lines[$i]->voucher_line_id)?->voucher->reference;
    expect($lines)->toHaveCount(7)
        ->and($lines->pluck('amount')->map(fn ($a) => (float) $a)->all())->toBe([50000.0, -12000.0, -8000.0, -8000.0, -8.85, -8.85, 500.0])
        // One clear entry of the amount within a week; the same ₹8,000 twice is told apart by the UTR.
        ->and([$matchedTo(0), $matchedTo(1), $matchedTo(2), $matchedTo(3)])->toBe(['UTR123456789', 'CHQ004512', 'UTR555222', 'UTR555111'])
        ->and($lines->take(4)->pluck('match_how')->unique()->all())->toBe(['auto'])
        ->and($lines->slice(4)->pluck('status')->unique()->all())->toBe(['open']);

    // The same statement again adds nothing (the two alike SMS charges stay two).
    $this->post(route('accounts.bank.import'), ['ledger_account_id' => $bank->id, 'file' => bankStatementFile()])->assertSessionHasNoErrors()
        ->assertSessionHas('status', '0 lines brought in (7 seen before, left out); 0 matched by themselves, 0 to look at.');
    app(CompanyContext::class)->set($w['company']);
    expect(BankStatementLine::count())->toBe(7);

    // Where things stand: the book, the bank, and what is open on each side explains the gap.
    $summary = app(\App\Modules\Accounts\Services\BankReconciliation::class)->summary($bank, app(Statements::class));
    expect($summary['as_of']->toDateString())->toBe('2026-10-25')
        ->and($summary['book'])->toBe(125000.0)->and($summary['bank'])->toBe(122482.3)
        ->and([$summary['open'], $summary['open_in'], $summary['open_out']])->toBe([3, 500.0, 17.7])
        ->and($summary['not_on_statement']->pluck('reference')->all())->toBe(['CHQ778899']);
    $this->get(route('accounts.bank'))->assertOk()->assertSeeText('Reconciled')->assertSeeText('CHQ778899')
        ->assertSeeText('Book as bank charges')->assertSeeText('Book as interest');
    $this->get(route('accounts.bank', ['show' => 'matched']))->assertOk()->assertSeeText('by itself');
});

it('matches by hand, books bank charges, leaves lines out with a note and undoes', function () {
    $w = bankWorld();
    $bank = $w['bank'];
    $this->post(route('accounts.bank.import'), ['ledger_account_id' => $bank->id, 'file' => bankStatementFile()])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    [$receipt, $cheque] = BankStatementLine::orderBy('id')->take(2)->get();
    [$sms1, $sms2, $cash] = BankStatementLine::where('status', 'open')->orderBy('id')->get();

    // Undo, then match by hand; an entry of another amount or side is refused.
    $this->post(route('accounts.bank.match', $cheque), ['action' => 'undo'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect($cheque->fresh()->status)->toBe('open');
    $this->post(route('accounts.bank.match', $cheque), ['action' => 'match', 'voucher_line_id' => $receipt->fresh()->voucher_line_id])->assertSessionHasErrors('voucher_line_id');
    app(CompanyContext::class)->set($w['company']);
    $diesel = VoucherLine::where('ledger_account_id', $bank->id)->where('credit', 12000)->value('id');
    $this->post(route('accounts.bank.match', $cheque), ['action' => 'match', 'voucher_line_id' => $diesel])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect($cheque->fresh()->only(['status', 'match_how', 'voucher_line_id']))->toBe(['status' => 'matched', 'match_how' => 'hand', 'voucher_line_id' => $diesel]);

    // Bank charges the book did not have: booked from the line, matched to it, once.
    $this->post(route('accounts.bank.match', $sms1), ['action' => 'book'])->assertSessionHasNoErrors()->assertSessionHas('status', 'Booked as bank charges and matched.');
    $this->post(route('accounts.bank.match', $sms1), ['action' => 'book'])->assertSessionHasErrors('line');
    $this->post(route('accounts.bank.match', $sms2), ['action' => 'book'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    $charges = Voucher::where('source_type', 'bank_line')->orderBy('id')->get();
    expect($charges)->toHaveCount(2)
        ->and($charges[0]->voucher_date->toDateString())->toBe('2026-10-25')
        ->and($charges[0]->narration)->toBe('Bank charges — SMS CHARGES')
        ->and(round((float) VoucherLine::whereHas('ledger', fn ($q) => $q->where('system_key', 'bank_charges'))->sum(DB::raw('debit - credit')), 2))->toBe(17.7)
        ->and($sms1->fresh()->match_how)->toBe('booked');

    // A line left out needs a reason.
    $this->post(route('accounts.bank.match', $cash), ['action' => 'ignore'])->assertSessionHasErrors('note');
    $this->post(route('accounts.bank.match', $cash), ['action' => 'ignore', 'note' => 'Owner’s own deposit, booked in December'])->assertSessionHasNoErrors();
    app(CompanyContext::class)->set($w['company']);
    expect($cash->fresh()->only(['status', 'note']))->toBe(['status' => 'ignored', 'note' => 'Owner’s own deposit, booked in December']);
    $this->get(route('accounts.bank', ['show' => 'ignored']))->assertOk()->assertSeeText('Owner’s own deposit');
    $this->get(route('accounts.bank'))->assertOk()->assertSeeText('Still ₹500.00 apart');

    // Money in the book balances still; a viewer does not see the page.
    app(CompanyContext::class)->set($w['company']);
    expect(round((float) VoucherLine::sum('debit'), 2))->toBe(round((float) VoucherLine::sum('credit'), 2));
    signInTo($w['company'], 'viewer');
    $this->get(route('accounts.bank'))->assertForbidden();
    signInTo($w['company'], 'accountant');
    $this->get(route('accounts.bank'))->assertOk()->assertSeeText('Match again');
    $this->post(route('accounts.bank.match', $cash), ['action' => 'undo'])->assertSessionHasNoErrors();

    // Another company's line is not found.
    $other = makeCompany();
    signInTo($other);
    $this->post(route('accounts.bank.match', $cash), ['action' => 'undo'])->assertNotFound();
});
