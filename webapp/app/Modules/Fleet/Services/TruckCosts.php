<?php

namespace App\Modules\Fleet\Services;

use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Services\Compliance;
use App\Modules\Fleet\Models\Truck;
use App\Modules\People\Models\Person;
use App\Modules\Trips\Models\Trip;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * What a truck costs a day whether it runs or not (M37) — the costs a trip's
 * direct profit leaves out — and a trip's share of them:
 *
 *  · from the books: what was booked to the truck but to no trip in the last
 *    90 days (service and repairs, EMI interest, tyres…), a day's worth;
 *  · its papers: each current paper's cost (insurance, permits, road tax,
 *    fitness) over the days it is valid for;
 *  · its usual driver's monthly salary, a day's worth.
 *
 * A trip's share is that per day times its days on the road (from start to
 * end, at least one). An estimate, said so on every screen that shows it.
 */
class TruckCosts
{
    public const BOOK_DAYS = 90;

    public function __construct(private CompanyContext $context, private Compliance $compliance) {}

    /**
     * @return array{per_day: float, parts: array{book: float, papers: float, salary: float}, papers: list<array{name: string, per_day: float}>}
     */
    public function perDay(Truck $truck, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::now($this->context->company()->timezone ?: 'UTC')->startOfDay();
        $from = $today->subDays(self::BOOK_DAYS);

        $book = (float) DB::table('voucher_lines as l')->join('vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->join('ledger_accounts as a', 'a.id', '=', 'l.ledger_account_id')->join('account_groups as g', 'g.id', '=', 'a.account_group_id')
            ->where('l.company_id', $this->context->id())->where('l.truck_id', $truck->id)->whereNull('l.trip_id')->where('g.nature', 'expense')
            // A cancelled voucher and its reversal both left out: neither is a cost.
            ->where('v.status', '!=', 'cancelled')->whereNull('v.reverses_voucher_id')->whereBetween('v.voucher_date', [$from->toDateString(), $today->toDateString()])
            ->sum(DB::raw('l.debit - l.credit'));

        $papers = [];
        foreach (Document::where('owner_type', 'truck')->where('owner_id', $truck->id)->where('amount', '>', 0)->whereNotNull('expires_on')
            ->where('expires_on', '>=', $today->toDateString())->get(['document_type', 'amount', 'issued_on', 'valid_from', 'expires_on']) as $doc) {
            $start = CarbonImmutable::parse($doc->valid_from ?? $doc->issued_on ?? $doc->expires_on->copy()->subYear());
            if ($start->greaterThan($today)) {
                continue;
            }
            $days = max(1, (int) $start->diffInDays(CarbonImmutable::parse($doc->expires_on)) + 1);
            $name = $this->compliance->typeName($doc->document_type);
            $papers[$name] = round(($papers[$name] ?? 0) + (float) $doc->amount / $days, 2);
        }

        $salary = (float) Person::where('default_truck_id', $truck->id)->where('type', 'driver')->where('status', '!=', 'left')
            ->where('pay_basis', 'monthly')->sum('monthly_salary');

        $parts = [
            'book' => round(max(0, $book) / self::BOOK_DAYS, 2),
            'papers' => round(array_sum($papers), 2),
            'salary' => round($salary / 30, 2),
        ];

        return ['per_day' => round(array_sum($parts), 2), 'parts' => $parts,
            'papers' => collect($papers)->map(fn ($v, $k) => ['name' => $k, 'per_day' => $v])->values()->all()];
    }

    /** Calendar days a trip was on the road (start and end day counted), at least one. */
    public function tripDays(Trip $trip): int
    {
        if (! $trip->started_at) {
            return 0;
        }
        $tz = $this->context->company()->timezone ?: 'UTC';
        $from = CarbonImmutable::instance($trip->started_at)->setTimezone($tz)->startOfDay();
        $to = CarbonImmutable::instance($trip->ended_at ?? now())->setTimezone($tz)->startOfDay();

        return max(1, (int) $from->diffInDays($to) + 1);
    }

    /** Whether the truck's standing costs are ours: an attached truck's papers and driver are its owner's. */
    public function counts(Truck $truck): bool
    {
        $ownership = array_key_exists('ownership', $truck->getAttributes()) ? $truck->ownership : Truck::whereKey($truck->id)->value('ownership');

        return $ownership !== 'attached';
    }

    /**
     * A trip's share of its truck's fixed costs; null for a hired or attached
     * truck or a trip not started. $perDay is the truck's figure when the
     * caller has it already (a report over many trips of one truck).
     *
     * @return ?array{days: int, per_day: float, share: float}
     */
    public function tripShare(Trip $trip, ?float $perDay = null): ?array
    {
        $trip->loadMissing('truck');
        if (! $trip->truck || ! $trip->started_at || ! $this->counts($trip->truck)) {
            return null;
        }
        $days = $this->tripDays($trip);
        $perDay ??= $this->perDay($trip->truck)['per_day'];

        return ['days' => $days, 'per_day' => $perDay, 'share' => round($days * $perDay, 2)];
    }
}
