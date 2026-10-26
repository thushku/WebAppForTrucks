<?php

namespace App\Modules\Accounts\Services;

use App\Models\Account;
use App\Modules\Accounts\Models\CardStatement;
use App\Modules\Accounts\Models\CardStatementLine;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Parties\Models\Party;
use App\Modules\Road\Models\RoadEvent;
use App\Modules\Trips\Models\Trip;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * FASTag and fuel-card statements (M44, blueprint 08 phase 2). The
 * provider's CSV of charges — date and time, vehicle, plaza or pump, amount,
 * transaction id — brought in; a charge already brought in is never brought
 * in twice, and recharges and refunds are left out.
 *
 * Each charge is matched by itself to the crew's entry paid by that card for
 * the same truck, of the same amount, within a day; by hand otherwise. A
 * charge nobody recorded is booked on the spot (the toll or fuel as the
 * truck's cost, from the card's wallet) or set aside with a reason; entries
 * that say the card paid but have no charge on the statement are listed.
 */
class CardStatements
{
    /** How far (hours) the crew's entry and the card's charge may be apart for a match. */
    public const HOURS = 24;

    /** How much (₹) the amounts may differ for a match: rounding on the statement. */
    public const SLACK = 0.5;

    private const HEADERS = [
        'date' => ['transaction date & time', 'transaction date', 'txn date', 'date & time', 'date time', 'processing date', 'bill date', 'date'],
        'time' => ['transaction time', 'txn time', 'time'],
        'vehicle' => ['vehicle number', 'vehicle no', 'vehicle', 'vrn', 'registration', 'reg no'],
        'place' => ['plaza name', 'toll plaza', 'plaza', 'merchant name', 'merchant', 'outlet', 'pump', 'location', 'description', 'narration'],
        'reference' => ['transaction id', 'txn id', 'transaction no', 'reference', 'ref no', 'rrn', 'bill no', 'bill number', 'invoice no', 'slip no'],
        'debit' => ['debit', 'dr amount', 'withdrawal'],
        'credit' => ['credit', 'cr amount', 'recharge'],
        'amount' => ['transaction amount', 'txn amount', 'amount'],
        'type' => ['transaction type', 'txn type', 'dr/cr', 'type'],
    ];

    public function __construct(private Books $books, private ChartOfAccounts $chart, private CompanyContext $context, private AuditRecorder $audit) {}

    /** Bring a statement in; its charges are matched at once where clear. A pump's statement names the pump (M46). */
    public function import(string $kind, string $path, string $fileName, Account $by, ?Party $pump = null): CardStatement
    {
        if (! isset(CardStatement::KINDS[$kind])) {
            throw ValidationException::withMessages(['kind' => __('Choose FASTag, fuel card or a pump.')]);
        }
        if ($kind === 'pump' && ! $pump) {
            throw ValidationException::withMessages(['vendor_party_id' => __('Choose the pump whose statement this is.')]);
        }
        $rows = $this->read($path);
        if (! $rows) {
            throw ValidationException::withMessages(['file' => __('No charges found: the file needs a date column and an amount column (CSV from the FASTag or fuel-card portal).')]);
        }
        $trucks = Truck::get(['id', 'reg_no'])->keyBy(fn ($t) => self::plate($t->reg_no));

        return DB::transaction(function () use ($kind, $rows, $fileName, $by, $trucks, $pump) {
            $statement = CardStatement::create(['kind' => $kind, 'vendor_party_id' => $kind === 'pump' ? $pump->id : null, 'file_name' => mb_substr($fileName, 0, 190), 'created_by' => $by->id,
                'period_from' => min(array_column($rows, 'day')), 'period_to' => max(array_column($rows, 'day'))]);
            $added = 0;
            $skipped = 0;
            foreach ($rows as $row) {
                // A pump's bill numbers are its own: two pumps may both have bill 1001.
                $reference = ($kind === 'pump' ? 'p' . $pump->id . '-' : '') . ($row['reference'] !== '' ? mb_substr($row['reference'], 0, 70)
                    : 'row-' . substr(sha1($kind . '|' . $row['at']->toIso8601String() . '|' . $row['vehicle'] . '|' . $row['amount'] . '|' . $row['place']), 0, 32));
                if (CardStatementLine::where('kind', $kind)->where('reference', $reference)->exists()) {
                    $skipped++;

                    continue;
                }
                $truck = $trucks[self::plate($row['vehicle'])] ?? null;
                $trip = $truck ? Trip::where('truck_id', $truck->id)->whereNotNull('started_at')->where('started_at', '<=', $row['at'])
                    ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>=', $row['at']))->orderByDesc('started_at')->first(['id', 'truck_id', 'branch_id']) : null;
                CardStatementLine::create(['card_statement_id' => $statement->id, 'kind' => $kind, 'txn_at' => $row['at'], 'vehicle' => mb_substr($row['vehicle'], 0, 20) ?: null,
                    'truck_id' => $truck?->id, 'trip_id' => $trip?->id, 'place' => mb_substr($row['place'], 0, 150) ?: null, 'amount' => $row['amount'], 'reference' => $reference, 'status' => 'open']);
                $added++;
            }
            $statement->forceFill(['lines' => $added, 'skipped' => $skipped])->save();
            $matched = $this->autoMatch($statement);
            $this->audit->record('card_statement.imported', CardStatement::KINDS[$kind] . " statement {$fileName}: {$added} charges in, {$skipped} skipped, {$matched} matched", 'card_statement', (string) $statement->id, $statement->label);

            return $statement;
        });
    }

    /** Match each open charge with its one clear entry: the card's, same truck, same amount, the nearest in time within a day. */
    public function autoMatch(CardStatement $statement): int
    {
        $used = [];
        $matched = 0;
        foreach (CardStatementLine::where('card_statement_id', $statement->id)->where('status', 'open')->whereNotNull('truck_id')->orderBy('txn_at')->get() as $line) {
            $event = $this->candidates($line, self::HOURS)->reject(fn ($e) => in_array($e->id, $used, true))
                ->filter(fn ($e) => abs((float) $e->amount - (float) $line->amount) <= self::SLACK)->first();
            if ($event) {
                $used[] = $event->id;
                $line->update(['status' => 'matched', 'road_event_id' => $event->id, 'match_how' => 'auto', 'trip_id' => $line->trip_id ?? $event->trip_id]);
                $matched++;
            }
        }

        return $matched;
    }

    /**
     * The crew's entries a charge could be: paid by this card, on this
     * truck's trips, not matched to another charge, nearest in time first.
     *
     * @return Collection<int, RoadEvent>
     */
    public function candidates(CardStatementLine $line, int $hours = 72): Collection
    {
        $at = CarbonImmutable::instance($line->txn_at);

        $pump = $line->kind === 'pump' ? CardStatement::whereKey($line->card_statement_id)->value('vendor_party_id') : null;

        return RoadEvent::with(['person:id,name', 'trip:id,trip_number,truck_id'])->where('payment_mode', CardStatement::MODES[$line->kind])->where('type', '!=', 'money_received')
            // On a pump's statement: credit entries at that pump, or with no pump named.
            ->when($pump, fn ($q) => $q->where(fn ($w) => $w->where('data->vendor_party_id', $pump)->orWhereNull('data->vendor_party_id')))
            ->whereNotIn('status', ['rejected', 'cancelled'])->whereNotNull('amount')
            ->when($line->truck_id, fn ($q) => $q->whereHas('trip', fn ($t) => $t->where('truck_id', $line->truck_id)))
            ->whereBetween('trusted_at', [$at->subHours($hours), $at->addHours($hours)])
            ->whereNotIn('id', CardStatementLine::whereNotNull('road_event_id')->whereKeyNot($line->id)->select('road_event_id'))
            ->get()->sortBy(fn ($e) => abs(CarbonImmutable::instance($e->trusted_at)->diffInMinutes($at)))->values();
    }

    public function match(CardStatementLine $line, int $eventId, Account $by): void
    {
        $this->assertOpen($line);
        $event = $this->candidates($line)->firstWhere('id', $eventId) ?? throw ValidationException::withMessages(['event' => __('That entry is not one this charge can be matched to.')]);
        $line->update(['status' => 'matched', 'road_event_id' => $event->id, 'match_how' => 'hand', 'trip_id' => $line->trip_id ?? $event->trip_id, 'decided_by' => $by->id]);
    }

    public function unmatch(CardStatementLine $line): void
    {
        if ($line->status !== 'matched') {
            throw ValidationException::withMessages(['line' => __('This charge is not matched.')]);
        }
        $line->update(['status' => 'open', 'road_event_id' => null, 'match_how' => null]);
    }

    /** A charge nobody recorded: the toll or fuel as the truck's (and trip's) cost, paid from the card's wallet. */
    public function book(CardStatementLine $line, Account $by): void
    {
        $this->assertOpen($line);
        DB::transaction(function () use ($line, $by) {
            $line->loadMissing(['truck:id,reg_no,branch_id', 'trip:id,trip_number,branch_id']);
            $centres = ['truck_id' => $line->truck_id, 'trip_id' => $line->trip_id, 'branch_id' => $line->trip?->branch_id ?? $line->truck?->branch_id];
            $pump = $line->kind === 'pump' ? Party::find(CardStatement::whereKey($line->card_statement_id)->value('vendor_party_id')) : null;
            $what = match ($line->kind) {
                'fastag' => __('FASTag at :place', ['place' => $line->place ?: '—']),
                'pump' => __('Fuel on credit at :pump', ['pump' => $pump?->name ?? '—']),
                default => __('Fuel card at :place', ['place' => $line->place ?: '—']),
            };
            $voucher = $this->books->post([
                'type' => 'expense', 'date' => CarbonImmutable::instance($line->txn_at)->setTimezone($this->context->company()->timezone ?: 'UTC')->toDateString(),
                'reference' => mb_substr($line->reference, 0, 60), 'narration' => $what . ($line->truck ? ', ' . $line->truck->reg_no : '') . ' — ' . __('from the statement, not entered by the crew'),
                'source_type' => 'card_line', 'source_id' => $line->id, 'created_by' => $by->id,
            ], [
                ['ledger' => $this->chart->expense($line->kind === 'fastag' ? 'toll' : 'fuel'), 'debit' => (float) $line->amount] + $centres,
                // The card's wallet; for a pump, what we owe the pump.
                ['ledger' => $pump ? $this->chart->party($pump) : $this->chart->ledger($line->kind), 'credit' => (float) $line->amount],
            ]);
            $line->update(['status' => 'booked', 'voucher_id' => $voucher?->id, 'decided_by' => $by->id]);
        });
    }

    public function ignore(CardStatementLine $line, string $note, Account $by): void
    {
        $this->assertOpen($line);
        if (mb_strlen(trim($note)) < 3) {
            throw ValidationException::withMessages(['note' => __('Say why it is set aside.')]);
        }
        $line->update(['status' => 'ignored', 'note' => mb_substr(trim($note), 0, 255), 'decided_by' => $by->id]);
    }

    /**
     * Entries in the statement's days that say this card paid but have no
     * charge on any statement: worth a question to the crew.
     *
     * @return Collection<int, RoadEvent>
     */
    public function withoutCharge(CardStatement $statement): Collection
    {
        if (! $statement->period_from) {
            return collect();
        }
        $tz = $this->context->company()->timezone ?: 'UTC';
        $trucks = CardStatementLine::where('card_statement_id', $statement->id)->whereNotNull('truck_id')->distinct()->pluck('truck_id');

        return RoadEvent::with(['person:id,name', 'trip:id,trip_number,truck_id', 'trip.truck:id,reg_no'])->where('payment_mode', CardStatement::MODES[$statement->kind])->where('type', '!=', 'money_received')
            ->when($statement->vendor_party_id, fn ($q) => $q->where('data->vendor_party_id', $statement->vendor_party_id))
            ->whereNotIn('status', ['rejected', 'cancelled'])->whereNotNull('amount')
            ->whereHas('trip', fn ($t) => $t->whereIn('truck_id', $trucks))
            ->whereBetween('trusted_at', [CarbonImmutable::parse($statement->period_from->toDateString(), $tz)->startOfDay()->utc(), CarbonImmutable::parse($statement->period_to->toDateString(), $tz)->endOfDay()->utc()])
            ->whereNotIn('id', CardStatementLine::whereNotNull('road_event_id')->select('road_event_id'))->orderBy('trusted_at')->get();
    }

    // ---- Reading the file ------------------------------------------------------------

    /**
     * The charges in the provider's CSV: the header row is found among the
     * first rows; dates are read day first (India, UAE), in the company's
     * time zone; credits (recharges, refunds) are left out.
     *
     * @return list<array{at: CarbonImmutable, day: string, vehicle: string, place: string, reference: string, amount: float}>
     */
    public function read(string $path): array
    {
        $tz = $this->context->company()->timezone ?: 'UTC';
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
            $at = $this->moment($cell('date'), $cell('time'), $tz);
            if (! $at) {
                continue;
            }
            // A credit (recharge, refund, reversal) is no charge.
            if (preg_match('/^\s*(c|cr|credit)\b|recharge|top.?up|refund|reversal/i', $cell('type')) || $this->number($cell('credit')) > 0 && $this->number($cell('debit')) == 0.0) {
                continue;
            }
            $amount = isset($map['debit']) ? $this->number($cell('debit')) : $this->number($cell('amount'));
            if ($amount <= 0.0) {
                continue;
            }
            $rows[] = ['at' => $at->utc(), 'day' => $at->toDateString(), 'vehicle' => strtoupper($cell('vehicle')), 'place' => $cell('place'),
                'reference' => $cell('reference'), 'amount' => round($amount, 2)];
        }
        fclose($handle);

        return $rows;
    }

    /** "KL-07 AB.1234" and "kl07ab1234" are the same plate. */
    public static function plate(?string $reg): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $reg));
    }

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

        return isset($map['date']) && (isset($map['amount']) || isset($map['debit'])) ? $map : null;
    }

    /** The date (with its time, or the time column's) in the company's time zone. */
    private function moment(string $date, string $time, string $tz): ?CarbonImmutable
    {
        $value = trim(preg_replace('/\s+/', ' ', trim($date . ($time !== '' && ! preg_match('/\d{1,2}:\d{2}/', $date) ? ' ' . $time : ''))));
        foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'd-m-Y H:i:s', 'd-m-Y H:i', 'Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y h:i:s A', 'd/m/Y h:i A', 'd-m-Y h:i:s A', 'd-m-Y h:i A',
            'd M Y H:i:s', 'd M Y H:i', 'd-M-Y H:i:s', 'd-M-Y H:i', 'd/m/y H:i', 'd/m/Y', 'd-m-Y', 'Y-m-d', 'd M Y', 'd-M-Y'] as $format) {
            // PHP's own parser answers false on a mismatch (Carbon's throws).
            $d = \DateTimeImmutable::createFromFormat('!' . $format, $value, new \DateTimeZone($tz));
            if ($d && $d->format($format) === $value && (int) $d->format('Y') >= 2000) {
                return CarbonImmutable::instance($d);
            }
        }

        return null;
    }

    /** "1,250.00", "₹ 155", "(90.00)" → numbers (a bracketed amount is a credit: zero). */
    private function number(string $value): float
    {
        $v = trim($value);
        if ($v === '' || $v === '-' || str_starts_with($v, '(')) {
            return 0.0;
        }

        return abs((float) preg_replace('/[^0-9.]/', '', $v));
    }

    private function assertOpen(CardStatementLine $line): void
    {
        if ($line->status !== 'open') {
            throw ValidationException::withMessages(['line' => __('This charge is :status already.', ['status' => mb_strtolower(__(CardStatementLine::STATUSES[$line->status]))])]);
        }
    }
}
