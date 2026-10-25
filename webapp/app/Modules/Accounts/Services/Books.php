<?php

namespace App\Modules\Accounts\Services;

use App\Modules\Accounts\Models\BookLock;
use App\Modules\Accounts\Models\LedgerAccount;
use App\Modules\Accounts\Models\Voucher;
use App\Modules\Accounts\Models\VoucherLine;
use App\Modules\Settings\Services\DocumentNumbering;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * The book (blueprint 07 §1): every voucher goes in through here.
 *
 *  · A voucher balances — debits equal credits — or it is refused.
 *  · A document posts once: a voucher carries its source (the invoice, the
 *    advance, the road entry…), and posting the same source again returns
 *    the voucher already there — a retried request or a rebuild never
 *    counts twice.
 *  · Nothing is edited or deleted. A cancel writes the exact reversal; the
 *    original is marked cancelled and points to it.
 *  · Locked books: a voucher dated on or before the lock date is posted on
 *    the first open day instead, keeping the document's own date beside it.
 *    The same goes for reversals, so a locked month never changes.
 */
class Books
{
    public function __construct(private CompanyContext $context, private DocumentNumbering $numbering, private AuditRecorder $audit) {}

    /**
     * @param array{type: string, date: CarbonInterface|string, narration: string, reference?: ?string, source_type?: ?string, source_id?: ?int, currency?: ?string, created_by?: ?int, series?: string} $head
     * @param array<int, array{ledger: LedgerAccount, debit?: float|int|string|null, credit?: float|int|string|null, bucket?: ?string, trip_id?: ?int, truck_id?: ?int, branch_id?: ?int, consignment_id?: ?int, note?: ?string}> $lines
     */
    public function post(array $head, array $lines): ?Voucher
    {
        $sourceType = $head['source_type'] ?? null;
        $sourceId   = $head['source_id'] ?? null;
        if ($sourceType && ($existing = $this->active($sourceType, (int) $sourceId))) {
            return $existing;
        }

        $lines = $this->clean($lines);
        if ($lines === []) {
            return null;
        }
        $debits  = round(array_sum(array_column($lines, 'debit')), 3);
        $credits = round(array_sum(array_column($lines, 'credit')), 3);
        if (abs($debits - $credits) >= 0.0005) {
            throw new RuntimeException("Voucher does not balance: debits {$debits}, credits {$credits} ({$head['narration']}).");
        }

        $documentDate = $this->localDate($head['date']);
        $date = $this->openDate($documentDate);

        return DB::transaction(function () use ($head, $lines, $debits, $documentDate, $date, $sourceType, $sourceId) {
            $earlier = $sourceType ? Voucher::where('source_type', $sourceType)->where('source_id', $sourceId)->where('voucher_type', '!=', 'reversal')->count() : 0;
            $voucher = Voucher::create([
                'voucher_type'  => $head['type'],
                // Typed by hand: journals and payment vouchers have their own series; the system's are V/.
                'number'        => $this->numbering->assign($head['series'] ?? ($head['type'] === 'journal' && ! $sourceType ? 'journal' : 'voucher'), $date->setTime(12, 0)),
                'voucher_date'  => $date->toDateString(),
                'document_date' => $date->equalTo($documentDate) ? null : $documentDate->toDateString(),
                'reference'     => isset($head['reference']) ? mb_substr((string) $head['reference'], 0, 60) : null,
                'narration'     => mb_substr($head['narration'], 0, 500),
                'source_type'   => $sourceType,
                'source_id'     => $sourceId,
                'source_event'  => $sourceType ? ($earlier ? 'posted#' . ($earlier + 1) : 'posted') : null,
                'total'         => $debits,
                'currency'      => $head['currency'] ?? $this->context->company()->currency,
                'created_by'    => $head['created_by'] ?? auth()->id(),
            ]);
            $this->writeLines($voucher, $lines);

            return $voucher;
        });
    }

    /** The exact reversal of a voucher, dated today (or the first open day). The original is marked cancelled. */
    public function reverse(Voucher $voucher, string $reason, ?CarbonInterface $on = null): Voucher
    {
        return DB::transaction(function () use ($voucher, $reason, $on) {
            $voucher = Voucher::whereKey($voucher->id)->lockForUpdate()->firstOrFail();
            if ($voucher->isCancelled()) {
                return $voucher->reversedBy()->firstOrFail();
            }
            if ($voucher->voucher_type === 'reversal') {
                throw ValidationException::withMessages(['voucher' => __('A reversal is not reversed again; post a new voucher instead.')]);
            }
            $documentDate = $this->localDate($on ?? now());
            $date = $this->openDate($documentDate);

            $reversal = Voucher::create([
                'voucher_type' => 'reversal',
                'number'       => $this->numbering->assign('voucher', $date->setTime(12, 0)),
                'voucher_date' => $date->toDateString(),
                'document_date' => $date->equalTo($documentDate) ? null : $documentDate->toDateString(),
                'reference'    => $voucher->reference,
                'narration'    => mb_substr(__('Reversal of :number: :reason', ['number' => $voucher->number, 'reason' => $reason]), 0, 500),
                'source_type'  => $voucher->source_type,
                'source_id'    => $voucher->source_id,
                'source_event' => $voucher->source_type ? 'reversed#' . $voucher->id : null,
                'total'        => $voucher->total,
                'currency'     => $voucher->currency,
                'reverses_voucher_id' => $voucher->id,
                'created_by'   => auth()->id(),
            ]);
            $lines = $voucher->lines()->get()->map(fn (VoucherLine $l) => [
                'ledger_account_id' => $l->ledger_account_id, 'debit' => (float) $l->credit, 'credit' => (float) $l->debit, 'bucket' => $l->bucket,
                'trip_id' => $l->trip_id, 'truck_id' => $l->truck_id, 'branch_id' => $l->branch_id, 'consignment_id' => $l->consignment_id, 'note' => $l->note,
            ])->all();
            $this->writeLines($reversal, $lines);

            $voucher->forceFill(['status' => 'cancelled', 'reversed_by_voucher_id' => $reversal->id, 'cancel_reason' => mb_substr($reason, 0, 255)])->save();

            return $reversal;
        });
    }

    /** Reverses whatever a document posted (nothing when it posted nothing). */
    public function reverseSource(string $sourceType, int $sourceId, string $reason): ?Voucher
    {
        $voucher = $this->active($sourceType, $sourceId);

        return $voucher ? $this->reverse($voucher, $reason) : null;
    }

    /** Whether a document ever posted (also when it was reversed since). */
    public function seen(string $sourceType, int $sourceId): bool
    {
        return Voucher::where('source_type', $sourceType)->where('source_id', $sourceId)->exists();
    }

    public function active(string $sourceType, int $sourceId): ?Voucher
    {
        return Voucher::where('source_type', $sourceType)->where('source_id', $sourceId)->where('voucher_type', '!=', 'reversal')->where('status', 'posted')->first();
    }

    // ---- Locking ---------------------------------------------------------------------

    public function lockedUntil(): ?CarbonImmutable
    {
        $date = BookLock::orderByDesc('id')->value('locked_until');

        return $date ? CarbonImmutable::parse($date) : null;
    }

    /** Lock the books up to a date (null unlocks). Kept as history; sensitive in the audit log. */
    public function lock(?CarbonInterface $until, ?string $reason, ?int $by): void
    {
        $before = $this->lockedUntil();
        BookLock::create(['locked_until' => $until?->toDateString(), 'reason' => $reason ? mb_substr($reason, 0, 255) : null, 'created_by' => $by]);
        $this->audit->record($until ? 'books.locked' : 'books.unlocked',
            $until ? 'Locked the books up to ' . $until->format('d M Y') : 'Unlocked the books', 'company', (string) $this->context->id(),
            changes: ['locked_until' => ['from' => $before?->toDateString(), 'to' => $until?->toDateString()]], reason: $reason, severity: 'notable');
    }

    /**
     * The calendar day of a source: a plain date as it is; a moment (a road
     * entry at 02:10, a settlement) on the company's clock, so an entry made
     * after midnight in Kochi is not dated the day before (UTC).
     */
    public function localDate(CarbonInterface|string $value): CarbonImmutable
    {
        if (is_string($value)) {
            return CarbonImmutable::parse(substr($value, 0, 10));
        }
        if ($value->format('H:i:s') === '00:00:00') {
            return CarbonImmutable::parse($value->toDateString());
        }

        return CarbonImmutable::parse(CarbonImmutable::instance($value)->setTimezone($this->context->company()->timezone ?: 'UTC')->toDateString());
    }

    /** The date a voucher is posted on: its own, or the first day after the lock. */
    public function openDate(CarbonImmutable $date): CarbonImmutable
    {
        $lock = $this->lockedUntil();

        return $lock && $date->lessThanOrEqualTo($lock) ? $lock->addDay() : $date;
    }

    // ---- Helpers ---------------------------------------------------------------------

    /**
     * Amounts rounded to the paisa / fils (3 places, as the bills), zero lines
     * dropped, a negative amount moved to the other side.
     */
    private function clean(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            $amount = round((float) ($line['debit'] ?? 0) - (float) ($line['credit'] ?? 0), 3);
            if (abs($amount) < 0.0005) {
                continue;
            }
            $ledger = $line['ledger'];
            $out[] = [
                'ledger_account_id' => $ledger instanceof LedgerAccount ? $ledger->id : (int) $ledger,
                'debit' => $amount > 0 ? $amount : 0.0, 'credit' => $amount < 0 ? -$amount : 0.0,
                'bucket' => $line['bucket'] ?? null, 'trip_id' => $line['trip_id'] ?? null, 'truck_id' => $line['truck_id'] ?? null,
                'branch_id' => $line['branch_id'] ?? null, 'consignment_id' => $line['consignment_id'] ?? null,
                'note' => isset($line['note']) ? mb_substr((string) $line['note'], 0, 255) : null,
            ];
        }

        return $out;
    }

    private function writeLines(Voucher $voucher, array $lines): void
    {
        foreach (array_values($lines) as $i => $line) {
            VoucherLine::create($line + ['voucher_id' => $voucher->id, 'sort_order' => $i]);
        }
    }
}
