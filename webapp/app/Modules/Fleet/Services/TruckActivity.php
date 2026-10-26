<?php

namespace App\Modules\Fleet\Services;

use App\Modules\Accounts\Services\Statements;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Road\Models\RoadEvent;
use App\Modules\Trips\Models\Trip;
use App\Platform\Tenancy\CompanyContext;
use App\Support\FiscalYear;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * One truck's work and money (M39), for its Trips, Fuel and Costs tabs and
 * the owner app: the trips it ran, its fills with the km per litre between
 * full tanks, and what it earned and cost in a period — the books by ledger
 * and month, and the estimates the books do not hold by truck (its papers,
 * its usual driver's salary, its tyres' wear).
 */
class TruckActivity
{
    public function __construct(private CompanyContext $context, private Statements $statements, private TruckCosts $costs, private Tyres $tyres) {}

    /** The period a tab opens on: this financial year to today. @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function defaultPeriod(): array
    {
        $company = $this->context->company();
        $today = $this->today();

        return [FiscalYear::containing($today, (int) ($company->fy_start_month ?: 4))->start, $today];
    }

    /**
     * The truck's trips, latest first, with their km and freight.
     *
     * @return Collection<int, object>
     */
    public function trips(Truck $truck, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Trip::with(['from:id,name', 'to:id,name', 'crew.person:id,name', 'consignments:id,trip_id,status,freight,loading_charge,unloading_charge,other_charges'])
            ->where('truck_id', $truck->id)->whereBetween('trip_date', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('trip_date')->orderByDesc('id')->limit(300)->get()
            ->map(fn (Trip $t) => (object) [
                'trip' => $t, 'route' => collect([$t->from?->name, $t->to?->name])->filter()->join(' → '),
                'driver' => $t->crew->firstWhere('role', 'driver')?->person?->name,
                'km' => $t->start_odometer && $t->end_odometer && $t->end_odometer > $t->start_odometer ? $t->end_odometer - $t->start_odometer : null,
                'freight' => round($t->consignments->where('status', '!=', 'cancelled')->sum(fn ($c) => $c->totalCharges()), 2),
            ]);
    }

    /**
     * The truck's fuel in the period: each fill (approved or waiting to be
     * checked), and at each full tank the km per litre since the full tank
     * before; with the totals and the km its trips ran.
     *
     * @return array{fills: Collection<int, object>, litres: float, cost: float, km: int, kmpl: ?float, standard: ?float, per_km: ?float}
     */
    public function fuel(Truck $truck, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $tz = $this->context->company()->timezone ?: 'UTC';
        $events = RoadEvent::with('trip:id,trip_number,uuid')->where('type', 'fuel')->whereIn('status', ['approved', 'waiting_check', 'info'])
            ->whereHas('trip', fn ($q) => $q->where('truck_id', $truck->id))
            ->where('trusted_at', '<=', CarbonImmutable::parse($to->toDateString(), $tz)->endOfDay()->utc())
            ->orderBy('trusted_at')->orderBy('id')->get();

        // Km per litre at each full tank: its odometer less the last full tank's, over the litres bought since (this fill's included).
        $lastFull = null;
        $since = 0.0;
        $rows = collect();
        foreach ($events as $e) {
            $litres = (float) ($e->data['litres'] ?? 0);
            $since += $litres;
            $kmpl = null;
            $km = null;
            $used = null;
            if (! empty($e->data['full_tank']) && $e->odometer) {
                if ($lastFull && $e->odometer > $lastFull->odometer && $since > 0) {
                    [$km, $used] = [$e->odometer - $lastFull->odometer, $since];
                    $kmpl = round($km / $used, 2);
                }
                $lastFull = $e;
                $since = 0.0;
            }
            if ($e->trusted_at->greaterThanOrEqualTo(CarbonImmutable::parse($from->toDateString(), $tz)->startOfDay()->utc())) {
                $rows->push((object) ['event' => $e, 'litres' => $litres, 'rate' => isset($e->data['rate']) ? (float) $e->data['rate'] : null,
                    'full' => ! empty($e->data['full_tank']), 'km' => $km, 'used' => $used, 'kmpl' => $kmpl, 'pump' => $e->data['pump'] ?? null]);
            }
        }

        $litres = round($rows->sum('litres'), 1);
        $cost = round($rows->sum(fn ($r) => (float) $r->event->amount), 2);
        $km = (int) Trip::where('truck_id', $truck->id)->whereNotNull('start_odometer')->whereNotNull('end_odometer')
            ->whereBetween('ended_at', [CarbonImmutable::parse($from->toDateString(), $tz)->startOfDay()->utc(), CarbonImmutable::parse($to->toDateString(), $tz)->endOfDay()->utc()])
            ->get(['start_odometer', 'end_odometer'])->sum(fn ($t) => max(0, $t->end_odometer - $t->start_odometer));
        $measured = $rows->whereNotNull('kmpl');

        return [
            'fills' => $rows->reverse()->values(), 'litres' => $litres, 'cost' => $cost, 'km' => $km,
            // Over the full tanks measured in the period: their km over their litres.
            'kmpl' => $measured->sum('used') > 0 ? round($measured->sum('km') / $measured->sum('used'), 2) : null,
            'standard' => $truck->mileage_loaded_kmpl ? (float) $truck->mileage_loaded_kmpl : null,
            'per_km' => $km > 0 ? round($cost / $km, 2) : null,
        ];
    }

    /**
     * What the truck earned and cost in the period: from the books (what is
     * booked to it, by ledger and by month) and, as estimates, its papers and
     * usual driver's salary for the period's days and its tyres' wear on the
     * trips that ended in it.
     */
    public function costs(Truck $truck, CarbonImmutable $from, CarbonImmutable $to): array
    {
        // Plain dates (the company's), to today at the latest.
        $from = CarbonImmutable::parse($from->toDateString());
        $to = CarbonImmutable::parse(min($to->toDateString(), $this->today()->toDateString()));
        $pl = $this->statements->profitAndLoss($from, $to, ['truck_id' => $truck->id]);
        $days = max(0, (int) $from->diffInDays($to) + 1);
        $perDay = $this->costs->counts($truck) ? $this->costs->perDay($truck) : null;
        $tz = $this->context->company()->timezone ?: 'UTC';
        $ended = Trip::where('truck_id', $truck->id)->whereBetween('ended_at', [CarbonImmutable::parse($from->toDateString(), $tz)->startOfDay()->utc(), CarbonImmutable::parse($to->toDateString(), $tz)->endOfDay()->utc()])
            ->get(['id', 'truck_id', 'start_odometer', 'end_odometer']);
        $wear = $this->tyres->wearForTrips($ended);

        $estimates = [
            'papers' => $perDay ? round($perDay['parts']['papers'] * $days, 2) : 0.0,
            'salary' => $perDay ? round($perDay['parts']['salary'] * $days, 2) : 0.0,
            'tyres' => round(array_sum(array_column($wear, 'amount')), 2),
        ];
        $km = (int) $ended->sum(fn ($t) => $t->start_odometer && $t->end_odometer ? max(0, $t->end_odometer - $t->start_odometer) : 0);
        $net = round($pl['profit'] - array_sum($estimates), 2);

        return [
            'from' => $from, 'to' => $to, 'days' => $days,
            'income' => $pl['income'], 'expense' => $pl['expense'], 'total_income' => $pl['total_income'], 'total_expense' => $pl['total_expense'], 'profit' => $pl['profit'],
            'months' => $this->statements->truckMonths($truck->id, $from->startOfMonth(), $to),
            'estimates' => $estimates, 'net' => $net, 'trips' => $ended->count(), 'km' => $km, 'per_km' => $km > 0 ? round($net / $km, 2) : null,
            'counts' => $perDay !== null,
        ];
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->context->company()->timezone ?: 'UTC')->startOfDay();
    }
}
