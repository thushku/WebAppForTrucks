<?php

namespace App\Modules\Accounts\Services;

use App\Models\Account;
use App\Modules\Accounts\Models\BankStatement;
use App\Modules\Accounts\Models\BankStatementLine;
use App\Modules\Accounts\Models\LedgerAccount;
use App\Modules\Accounts\Models\VoucherLine;
use App\Platform\Audit\AuditRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Bank reconciliation (blueprint 07 §1): the bank's statement (CSV, as Indian
 * and UAE banks give it) brought in for one bank ledger; each line matched to
 * the book's entry of the same amount on that ledger — by itself when there
 * is one clear candidate (the UTR or cheque number decides between several),
 * by hand otherwise — ignored on purpose, or booked on the spot as bank
 * charges or interest. A line already brought in is never brought in twice.
 */
class BankReconciliation
{
    /** How far apart (days) the bank's date and the book's date may be for a match. */
    public const DAYS = 7;

    private const HEADERS = [
        'date' => ['transaction date', 'txn date', 'tran date', 'value date', 'posting date', 'date'],
        'description' => ['narration', 'description', 'particulars', 'transaction details', 'details', 'remarks'],
        'reference' => ['chq/ref', 'ref no', 'reference', 'cheque no', 'chq no', 'utr', 'ref', 'cheque', 'chq'],
        'debit' => ['withdrawal', 'debit', 'dr amount', 'paid out'],
        'credit' => ['deposit', 'credit', 'cr amount', 'paid in'],
        'amount' => ['amount', 'transaction amount'],
        'type' => ['dr/cr', 'cr/dr', 'type'],
        'balance' => ['balance'],
    ];

    public function __construct(private Books $books, private ChartOfAccounts $chart, private AuditRecorder $audit) {}

    /** @return Collection<int, LedgerAccount> the company's bank ledgers */
    /** The bank ledgers, the company's own accounts first (the default one leading), the general "account not chosen" last. */
    public function bankLedgers(): Collection
    {
        $default = \App\Modules\Settings\Models\CompanyBankAccount::where('active', true)->orderByDesc('is_default')->value('id');

        return LedgerAccount::where('kind', 'bank')->where('active', true)->orderBy('name')->get()
            ->sortBy(fn ($l) => match (true) { $default && $l->company_bank_account_id === $default => 0, $l->company_bank_account_id !== null => 1, default => 2 })->values();
    }

    /** Bring a CSV in, leave out lines seen before, then match what is clear. */
    public function import(LedgerAccount $bank, string $path, string $fileName, Account $by): BankStatement
    {
        if ($bank->kind !== 'bank') {
            throw ValidationException::withMessages(['ledger' => __('Choose a bank ledger.')]);
        }
        $rows = $this->read($path);
        if ($rows === []) {
            throw ValidationException::withMessages(['file' => __('No lines found. The file needs a header row with a date and a withdrawal / deposit (or amount) column.')]);
        }

        $statement = DB::transaction(function () use ($bank, $rows, $fileName, $by) {
            $statement = BankStatement::create(['ledger_account_id' => $bank->id, 'file_name' => mb_substr($fileName, 0, 200), 'imported_by' => $by->id,
                'period_from' => min(array_column($rows, 'date')), 'period_to' => max(array_column($rows, 'date')),
                'closing_balance' => end($rows)['balance']]);
            $seen = BankStatementLine::where('ledger_account_id', $bank->id)->pluck('fingerprint')->flip();
            $added = 0;
            $duplicates = 0;
            $repeats = [];
            foreach ($rows as $row) {
                // Two real lines can look alike (two SMS charges on one day):
                // the count of the same line within this file keeps them apart,
                // and the same file brought in again still gives the same prints.
                $base = implode('|', [$row['date'], number_format($row['amount'], 3, '.', ''), mb_strtolower($row['description']), mb_strtolower($row['reference'])]);
                $repeats[$base] = ($repeats[$base] ?? 0) + 1;
                $fingerprint = hash('sha256', $base . '|' . $repeats[$base]);
                if (isset($seen[$fingerprint])) {
                    $duplicates++;

                    continue;
                }
                $seen[$fingerprint] = true;
                BankStatementLine::create(['bank_statement_id' => $statement->id, 'ledger_account_id' => $bank->id, 'line_date' => $row['date'],
                    'description' => mb_substr($row['description'], 0, 255) ?: null, 'reference' => mb_substr($row['reference'], 0, 80) ?: null,
                    'amount' => $row['amount'], 'balance' => $row['balance'], 'fingerprint' => $fingerprint, 'status' => 'open']);
                $added++;
            }
            $statement->forceFill(['lines' => $added, 'duplicates' => $duplicates])->save();

            return $statement;
        });
        $this->audit->record('accounts.bank_imported', "Bank statement {$fileName} brought in for {$bank->name}: {$statement->lines} lines", 'ledger', (string) $bank->id, $bank->name);
        $this->autoMatch($bank);

        return $statement->fresh();
    }

    /** @return int lines matched */
    public function autoMatch(LedgerAccount $bank): int
    {
        $matched = 0;
        foreach (BankStatementLine::where('ledger_account_id', $bank->id)->where('status', 'open')->orderBy('line_date')->orderBy('id')->get() as $line) {
            $candidates = $this->candidates($line);
            $pick = $candidates->count() === 1 ? $candidates->first() : $this->byReference($line, $candidates);
            if ($pick) {
                $this->link($line, $pick->id, 'auto', null);
                $matched++;
            }
        }

        return $matched;
    }

    /**
     * Book entries on the bank ledger that could be this line: the same
     * amount on the same side, within DAYS days, not matched to another line.
     */
    public function candidates(BankStatementLine $line, int $days = self::DAYS): Collection
    {
        $amount = abs((float) $line->amount);
        $taken = BankStatementLine::where('ledger_account_id', $line->ledger_account_id)->whereNotNull('voucher_line_id')->pluck('voucher_line_id');

        return VoucherLine::query()->join('vouchers as v', 'v.id', '=', 'voucher_lines.voucher_id')
            ->where('voucher_lines.ledger_account_id', $line->ledger_account_id)->where('v.status', 'posted')->where('v.voucher_type', '!=', 'reversal')
            ->where((float) $line->amount > 0 ? 'voucher_lines.debit' : 'voucher_lines.credit', $amount)
            ->whereBetween('v.voucher_date', [$line->line_date->copy()->subDays($days)->toDateString(), $line->line_date->copy()->addDays($days)->toDateString()])
            ->whereNotIn('voucher_lines.id', $taken)
            ->orderByRaw('abs(datediff(v.voucher_date, ?))', [$line->line_date->toDateString()])
            ->get(['voucher_lines.id', 'voucher_lines.debit', 'voucher_lines.credit', 'voucher_lines.note', 'v.id as voucher_id', 'v.number', 'v.voucher_date', 'v.reference', 'v.narration']);
    }

    /**
     * The same as candidates(), for a page of open lines at once: one query
     * for the whole date range, sorted out per line here, so a long
     * statement does not cost a query per line.
     *
     * @return Collection<int, Collection> keyed by the statement line's id
     */
    public function candidatesFor(Collection $lines, int $days = self::DAYS, int $take = 5): Collection
    {
        $lines = $lines->where('status', 'open');
        if ($lines->isEmpty()) {
            return collect();
        }
        $ledgerId = $lines->first()->ledger_account_id;
        $taken = BankStatementLine::where('ledger_account_id', $ledgerId)->whereNotNull('voucher_line_id')->pluck('voucher_line_id');
        $pool = VoucherLine::query()->join('vouchers as v', 'v.id', '=', 'voucher_lines.voucher_id')
            ->where('voucher_lines.ledger_account_id', $ledgerId)->where('v.status', 'posted')->where('v.voucher_type', '!=', 'reversal')
            ->whereBetween('v.voucher_date', [$lines->min('line_date')->copy()->subDays($days)->toDateString(), $lines->max('line_date')->copy()->addDays($days)->toDateString()])
            ->whereNotIn('voucher_lines.id', $taken)
            ->get(['voucher_lines.id', 'voucher_lines.debit', 'voucher_lines.credit', 'voucher_lines.note', 'v.id as voucher_id', 'v.number', 'v.voucher_date', 'v.reference', 'v.narration']);

        return $lines->mapWithKeys(function (BankStatementLine $line) use ($pool, $days, $take) {
            $amount = round(abs((float) $line->amount), 2);
            $day = CarbonImmutable::parse($line->line_date->toDateString());
            $side = (float) $line->amount > 0 ? 'debit' : 'credit';

            return [$line->id => $pool->filter(fn ($c) => round((float) $c->{$side}, 2) === $amount
                && abs($day->diffInDays(CarbonImmutable::parse($c->voucher_date), false)) <= $days)
                ->sortBy(fn ($c) => abs($day->diffInDays(CarbonImmutable::parse($c->voucher_date), false)))->take($take)->values()];
        });
    }

    public function match(BankStatementLine $line, int $voucherLineId, Account $by): void
    {
        if (! $this->candidates($line, 31)->contains('id', $voucherLineId)) {
            throw ValidationException::withMessages(['voucher_line_id' => __('That entry does not fit this line (amount, side or date), or is matched already.')]);
        }
        $this->link($line, $voucherLineId, 'hand', $by);
    }

    public function unmatch(BankStatementLine $line): void
    {
        $line->forceFill(['status' => 'open', 'voucher_line_id' => null, 'match_how' => null, 'matched_by' => null, 'matched_at' => null, 'note' => null])->save();
    }

    public function ignore(BankStatementLine $line, string $note, Account $by): void
    {
        $line->forceFill(['status' => 'ignored', 'note' => mb_substr($note, 0, 255), 'matched_by' => $by->id, 'matched_at' => now()])->save();
    }

    /** Book a line the book does not have yet — bank charges (out) or interest (in) — and match it. */
    public function book(BankStatementLine $line, Account $by): void
    {
        if ($line->status !== 'open') {
            throw ValidationException::withMessages(['line' => __('This line is matched already.')]);
        }
        DB::transaction(function () use ($line, $by) {
            $bank = LedgerAccount::findOrFail($line->ledger_account_id);
            $amount = abs((float) $line->amount);
            $in = (float) $line->amount > 0;
            $other = $this->chart->ledger($in ? 'bank_interest' : 'bank_charges');
            $voucher = $this->books->post([
                'type' => 'journal', 'date' => $line->line_date, 'reference' => $line->reference,
                'narration' => ($in ? __('Bank interest') : __('Bank charges')) . ($line->description ? ' — ' . $line->description : ''),
                'source_type' => 'bank_line', 'source_id' => $line->id, 'created_by' => $by->id,
            ], $in ? [['ledger' => $bank, 'debit' => $amount], ['ledger' => $other, 'credit' => $amount]] : [['ledger' => $other, 'debit' => $amount], ['ledger' => $bank, 'credit' => $amount]]);
            $bankLine = VoucherLine::where('voucher_id', $voucher->id)->where('ledger_account_id', $bank->id)->firstOrFail();
            $this->link($line, $bankLine->id, 'booked', $by);
        });
    }

    /**
     * Where the bank and the book stand: the book's balance and the bank's on
     * the last statement date, and what explains the gap.
     */
    public function summary(LedgerAccount $bank, Statements $statements): array
    {
        $last = BankStatementLine::where('ledger_account_id', $bank->id)->orderByDesc('line_date')->orderByDesc('id')->first();
        $asOf = $last ? CarbonImmutable::parse($last->line_date->toDateString()) : CarbonImmutable::today();
        $book = $statements->ledger($bank, $asOf, $asOf)['closing'];
        $first = BankStatementLine::where('ledger_account_id', $bank->id)->min('line_date');
        $matchedIds = BankStatementLine::where('ledger_account_id', $bank->id)->whereNotNull('voucher_line_id')->pluck('voucher_line_id');
        $notOnStatement = $first ? VoucherLine::query()->join('vouchers as v', 'v.id', '=', 'voucher_lines.voucher_id')
            ->where('voucher_lines.ledger_account_id', $bank->id)->where('v.status', 'posted')->where('v.voucher_type', '!=', 'reversal')
            ->whereBetween('v.voucher_date', [$first, $asOf->toDateString()])->whereNotIn('voucher_lines.id', $matchedIds)
            ->orderBy('v.voucher_date')->get(['voucher_lines.id', 'voucher_lines.debit', 'voucher_lines.credit', 'v.id as voucher_id', 'v.number', 'v.voucher_date', 'v.reference', 'v.narration']) : collect();

        return [
            'as_of' => $asOf, 'book' => round($book, 2), 'bank' => $last?->balance !== null ? round((float) $last->balance, 2) : null,
            'open' => BankStatementLine::where('ledger_account_id', $bank->id)->where('status', 'open')->count(),
            'open_in' => round((float) BankStatementLine::where('ledger_account_id', $bank->id)->where('status', 'open')->where('amount', '>', 0)->sum('amount'), 2),
            'open_out' => round(abs((float) BankStatementLine::where('ledger_account_id', $bank->id)->where('status', 'open')->where('amount', '<', 0)->sum('amount')), 2),
            'not_on_statement' => $notOnStatement,
        ];
    }

    private function link(BankStatementLine $line, int $voucherLineId, string $how, ?Account $by): void
    {
        $line->forceFill(['status' => 'matched', 'voucher_line_id' => $voucherLineId, 'match_how' => $how, 'matched_by' => $by?->id, 'matched_at' => now()])->save();
    }

    /** Among several of the same amount: the one whose reference or narration carries the line's UTR / cheque number. */
    private function byReference(BankStatementLine $line, Collection $candidates): ?object
    {
        $tokens = collect(preg_split('/[^A-Za-z0-9]+/', ($line->reference ?? '') . ' ' . ($line->description ?? '')) ?: [])
            ->filter(fn ($t) => strlen($t) >= 6 && preg_match('/\d/', $t))->map(fn ($t) => mb_strtolower($t))->unique();
        if ($tokens->isEmpty()) {
            return null;
        }
        $hits = $candidates->filter(fn ($c) => $tokens->contains(fn ($t) => str_contains(mb_strtolower(($c->reference ?? '') . ' ' . ($c->narration ?? '') . ' ' . ($c->note ?? '')), $t)));

        return $hits->count() === 1 ? $hits->first() : null;
    }

    /**
     * The statement's lines: date, description, reference, signed amount and
     * the running balance where given. Header names vary by bank; dates are
     * read day first (India, UAE).
     *
     * @return array<int, array{date: string, description: string, reference: string, amount: float, balance: ?float}>
     */
    public function read(string $path): array
    {
        $handle = fopen($path, 'r');
        $map = null;
        $rows = [];
        $scanned = 0;
        while (($cells = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $cells = array_map(fn ($c) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $c)), $cells);
            if ($map === null) {
                if (++$scanned > 40) {
                    break;
                }
                $map = $this->headerMap($cells);

                continue;
            }
            $cell = fn (string $field) => isset($map[$field]) ? (string) ($cells[$map[$field]] ?? '') : '';
            $date = $this->date($cell('date'));
            if (! $date) {
                continue;
            }
            $amount = isset($map['debit']) || isset($map['credit'])
                ? abs($this->number($cell('credit'))) - abs($this->number($cell('debit')))
                : $this->number($cell('amount')) * (preg_match('/^\s*d/i', $cell('type')) ? -1 : 1);
            if (abs($amount) < 0.0005) {
                continue;
            }
            $rows[] = [
                'date' => $date, 'description' => $cell('description'), 'reference' => $cell('reference'),
                'amount' => round($amount, 3), 'balance' => $cell('balance') !== '' ? round($this->number($cell('balance')), 3) : null,
            ];
        }
        fclose($handle);

        return $rows;
    }

    /** The column of each field, when this row is the header (a date and some amount column); null otherwise. */
    private function headerMap(array $cells): ?array
    {
        $lower = array_map(fn ($c) => mb_strtolower($c), $cells);
        $map = [];
        foreach (self::HEADERS as $field => $words) {
            foreach ($words as $word) {
                foreach ($lower as $i => $cell) {
                    if ($cell !== '' && str_contains($cell, $word) && ! in_array($i, $map, true)) {
                        $map[$field] = $i;

                        continue 3;
                    }
                }
            }
        }
        $hasAmount = isset($map['debit']) || isset($map['credit']) || isset($map['amount']);

        return isset($map['date']) && $hasAmount ? $map : null;
    }

    private function date(string $value): ?string
    {
        // Some banks add the time: "21/10/2026 10:15:00".
        $value = trim(preg_replace('/\s+\d{1,2}:\d{2}(:\d{2})?(\s*[AP]M)?$/i', '', trim($value)));
        foreach (['d/m/Y', 'd-m-Y', 'd.m.Y', 'd/m/y', 'd-m-y', 'Y-m-d', 'd M Y', 'd-M-Y', 'd-M-y', 'd M y', 'j/n/Y', 'j-n-Y'] as $format) {
            $d = \DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($d && $d->format($format) === $value && (int) $d->format('Y') >= 2000) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }

    /** "1,23,456.50", "(500.00)", "250.00 Dr", "₹ 1,000" → numbers. */
    private function number(string $value): float
    {
        $v = trim($value);
        if ($v === '' || $v === '-') {
            return 0.0;
        }
        $negative = str_starts_with($v, '(') || preg_match('/dr\.?$/i', $v);
        $v = preg_replace('/[^0-9.\-]/', '', $v);

        return ($negative ? -1 : 1) * abs((float) $v);
    }
}
