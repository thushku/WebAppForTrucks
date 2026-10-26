<?php

namespace App\Modules\Reports\Services;

use App\Modules\Fleet\Models\Truck;
use App\Modules\Road\Models\DrivingShift;
use App\Modules\Road\Models\RoadEvent;
use App\Modules\Road\Services\EventTypes;
use App\Modules\Trips\Models\Trip;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The owner's reports (blueprint 04 §2 Reports): trip profit from the book,
 * road spending by category and by who paid, payments without receipt by
 * person and route, fuel and mileage per truck against its standard, and
 * each driver's trips, kilometres, hours at the wheel and rejected entries.
 * Every report is for a period and downloads as CSV.
 */
class Reports
{
    public function __construct(private CompanyContext $context) {}

    /** Trips that ended in the period, with income, cost and profit from the book, and km run. */
    public function tripProfit(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $trips = Trip::with(['truck:id,reg_no', 'supplierVehicle:id,reg_no', 'stops.place:id,name'])->whereIn('status', ['ended', 'settled', 'closed'])
            ->whereBetween('ended_at', [$this->startUtc($from), $this->endUtc($to)])->orderBy('ended_at')->get();
        if ($trips->isEmpty()) {
            return collect();
        }
        $book = DB::table('voucher_lines as l')->join('ledger_accounts as a', 'a.id', '=', 'l.ledger_account_id')->join('account_groups as g', 'g.id', '=', 'a.account_group_id')
            ->where('l.company_id', $this->context->id())->whereIn('l.trip_id', $trips->pluck('id'))->whereIn('g.nature', ['income', 'expense'])
            ->groupBy('l.trip_id')
            ->selectRaw("l.trip_id, sum(case when g.nature = 'income' then l.credit - l.debit else 0 end) as income, sum(case when g.nature = 'expense' then l.debit - l.credit else 0 end) as cost")
            ->get()->keyBy('trip_id');

        return $trips->map(function (Trip $trip) use ($book) {
            $income = round((float) ($book[$trip->id]->income ?? 0), 2);
            $cost = round((float) ($book[$trip->id]->cost ?? 0), 2);
            $km = $trip->start_odometer && $trip->end_odometer && $trip->end_odometer > $trip->start_odometer ? $trip->end_odometer - $trip->start_odometer : null;

            return (object) [
                'trip' => $trip, 'route' => collect([$trip->stops->first()?->place?->name, $trip->stops->last()?->place?->name])->filter()->join(' → '),
                'income' => $income, 'cost' => $cost, 'profit' => round($income - $cost, 2), 'km' => $km,
                'per_km' => $km ? round(($income - $cost) / $km, 2) : null, 'margin' => $income > 0 ? round(($income - $cost) / $income * 100, 1) : null,
            ];
        });
    }

    /**
     * Money spent on the road in the period (approved entries), by category
     * and by who paid — the crew's cash, a fuel card, FASTag, pump credit.
     */
    public function roadSpending(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $events = RoadEvent::where('status', 'approved')->whereNotNull('amount')->where('type', '!=', 'money_received')
            ->whereBetween('event_at', [$this->startUtc($from), $this->endUtc($to)])->get(['type', 'amount', 'payment_mode']);
        $modes = array_keys(EventTypes::PAYMENT_MODES);
        $rows = $events->groupBy(fn ($e) => EventTypes::label($e->type))->map(function (Collection $set, string $label) use ($modes) {
            $row = ['category' => $label, 'count' => $set->count(), 'total' => round($set->sum(fn ($e) => (float) $e->amount), 2)];
            foreach ($modes as $mode) {
                $row[$mode] = round($set->where('payment_mode', $mode)->sum(fn ($e) => (float) $e->amount), 2);
            }

            return $row;
        })->sortByDesc('total')->values();

        return ['rows' => $rows, 'modes' => collect($modes)->filter(fn ($m) => $rows->sum($m) > 0)->values()->all(), 'total' => round($rows->sum('total'), 2)];
    }

    /** Payments without receipt (police, RTO, check posts) by person and by route; rejected ones counted apart. */
    public function withoutReceipt(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $events = RoadEvent::with(['person:id,name', 'trip.stops.place:id,name'])->where('type', 'no_receipt')->whereNotIn('status', ['cancelled'])
            ->whereBetween('event_at', [$this->startUtc($from), $this->endUtc($to)])->orderBy('event_at')->get();
        $sum = fn (Collection $set, ?string $status = null) => round($set->when($status, fn ($s) => $s->where('status', $status))->sum(fn ($e) => (float) $e->amount), 2);
        $route = fn (RoadEvent $e) => collect([$e->trip?->stops->first()?->place?->name, $e->trip?->stops->last()?->place?->name])->filter()->join(' → ') ?: '—';

        return [
            'events' => $events,
            'by_person' => $events->groupBy(fn ($e) => $e->person?->name ?? '—')->map(fn ($set, $name) => ['name' => $name, 'count' => $set->count(), 'approved' => $sum($set, 'approved'), 'rejected' => $sum($set, 'rejected'), 'total' => $sum($set)])->sortByDesc('total')->values(),
            'by_route' => $events->groupBy($route)->map(fn ($set, $name) => ['route' => $name, 'count' => $set->count(), 'approved' => $sum($set, 'approved'), 'total' => $sum($set), 'trips' => $set->pluck('trip_id')->unique()->count()])->sortByDesc('total')->values(),
            'by_authority' => $events->groupBy(fn ($e) => $e->data['authority'] ?? 'other')->map(fn ($set) => $sum($set))->sortDesc()->all(),
            'total' => $sum($events), 'approved' => $sum($events, 'approved'),
        ];
    }

    /** Per truck: litres and cost of fuel entered, km run on trips that ended, km per litre against its standard. */
    public function fuel(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $fuel = RoadEvent::where('type', 'fuel')->whereIn('status', ['approved', 'waiting_check', 'info'])
            ->whereBetween('event_at', [$this->startUtc($from), $this->endUtc($to)])->with('trip:id,truck_id')->get(['trip_id', 'amount', 'data']);
        $byTruck = $fuel->filter(fn ($e) => $e->trip?->truck_id)->groupBy(fn ($e) => $e->trip->truck_id);
        $km = Trip::whereNotNull('truck_id')->whereNotNull('end_odometer')->whereNotNull('start_odometer')
            ->whereBetween('ended_at', [$this->startUtc($from), $this->endUtc($to)])->get(['truck_id', 'start_odometer', 'end_odometer'])
            ->groupBy('truck_id')->map(fn ($set) => ['km' => (int) $set->sum(fn ($t) => max(0, $t->end_odometer - $t->start_odometer)), 'trips' => $set->count()]);

        return Truck::whereIn('id', $byTruck->keys()->merge($km->keys())->unique())->orderBy('reg_no')->get()->map(function (Truck $truck) use ($byTruck, $km) {
            $set = $byTruck[$truck->id] ?? collect();
            $litres = round($set->sum(fn ($e) => (float) ($e->data['litres'] ?? 0)), 1);
            $run = $km[$truck->id]['km'] ?? 0;
            $kmpl = $litres > 0 && $run > 0 ? round($run / $litres, 2) : null;
            $standard = $truck->mileage_loaded_kmpl ? (float) $truck->mileage_loaded_kmpl : null;

            return (object) [
                'truck' => $truck, 'litres' => $litres, 'cost' => round($set->sum(fn ($e) => (float) $e->amount), 2), 'fills' => $set->count(),
                'km' => $run, 'trips' => $km[$truck->id]['trips'] ?? 0, 'kmpl' => $kmpl, 'standard' => $standard,
                'short' => $kmpl && $standard && $kmpl < $standard * 0.95, 'per_km' => $run > 0 ? round($set->sum(fn ($e) => (float) $e->amount) / $run, 2) : null,
            ];
        });
    }

    /** Per driver: trips, km, hours at the wheel, entries rejected and payments without receipt. */
    public function drivers(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $shifts = DrivingShift::with('person:id,name')->whereBetween('started_at', [$this->startUtc($from), $this->endUtc($to)])->get();
        $events = RoadEvent::whereBetween('event_at', [$this->startUtc($from), $this->endUtc($to)])->whereNotNull('person_id')
            ->get(['person_id', 'type', 'status', 'amount', 'trip_id']);

        return $shifts->groupBy('person_id')->map(function (Collection $own) use ($events) {
            $mine = $events->where('person_id', $own->first()->person_id);
            $minutes = (int) $own->sum(fn (DrivingShift $s) => $s->ended_at ? $s->minutes() : 0);

            return (object) [
                'person' => $own->first()->person, 'trips' => $own->pluck('trip_id')->unique()->count(),
                'km' => (int) $own->sum(fn ($s) => $s->end_odometer && $s->start_odometer ? max(0, $s->end_odometer - $s->start_odometer) : 0),
                'minutes' => $minutes, 'entries' => $mine->whereNotNull('amount')->count(), 'rejected' => $mine->where('status', 'rejected')->count(),
                'without_receipt' => round($mine->where('type', 'no_receipt')->whereNotIn('status', ['rejected', 'cancelled'])->sum(fn ($e) => (float) $e->amount), 2),
            ];
        })->sortByDesc('km')->values();
    }

    /**
     * Routes (lanes, M33): the trips that ended in the period grouped by from
     * → to (an empty run apart), with what a trip on the lane usually earns
     * (its LRs' freight and charges, or the bill when that is more), costs (the
     * book) and makes, the km, the days on the road, the main costs and the
     * last trip — what to quote the next client on it.
     */
    public function lanes(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $rows = $this->tripProfit($from, $to);
        if ($rows->isEmpty()) {
            return collect();
        }
        $places = \App\Modules\Settings\Models\Place::whereIn('id', $rows->flatMap(fn ($r) => [$r->trip->from_place_id, $r->trip->to_place_id])->filter()->unique())->pluck('name', 'id');
        // Income is the freight on the trip's LRs, billed or not (the book has it only once billed).
        $freight = \App\Modules\Trips\Models\Consignment::whereIn('trip_id', $rows->pluck('trip.id'))->where('status', '!=', 'cancelled')
            ->groupBy('trip_id')->selectRaw('trip_id, sum(coalesce(freight, 0) + coalesce(loading_charge, 0) + coalesce(unloading_charge, 0) + coalesce(other_charges, 0)) as amount')->pluck('amount', 'trip_id');
        $rows = $rows->map(function ($r) use ($freight) {
            $r->income = round(max($r->income, (float) ($freight[$r->trip->id] ?? 0)), 2);
            $r->profit = round($r->income - $r->cost, 2);

            return $r;
        });
        // The costs of each trip by ledger (fuel, toll, bata…), for the lane's main costs.
        $costs = DB::table('voucher_lines as l')->join('ledger_accounts as a', 'a.id', '=', 'l.ledger_account_id')->join('account_groups as g', 'g.id', '=', 'a.account_group_id')
            ->where('l.company_id', $this->context->id())->whereIn('l.trip_id', $rows->pluck('trip.id'))->where('g.nature', 'expense')
            ->groupBy('l.trip_id', 'a.name')->selectRaw('l.trip_id, a.name, sum(l.debit - l.credit) as amount')->get()->groupBy('trip_id');

        return $rows->groupBy(fn ($r) => $r->trip->from_place_id . '-' . $r->trip->to_place_id . ($r->trip->trip_kind === 'empty' ? '-empty' : ''))
            ->map(function (Collection $set) use ($places, $costs) {
                $trip = $set->first()->trip;
                $n = $set->count();
                $income = $set->sum('income');
                $cost = $set->sum('cost');
                $withKm = $set->filter(fn ($r) => $r->km);
                $days = $set->filter(fn ($r) => $r->trip->started_at && $r->trip->ended_at)->map(fn ($r) => $r->trip->started_at->diffInMinutes($r->trip->ended_at) / 1440);
                $last = $set->sortBy(fn ($r) => $r->trip->ended_at)->last();
                $main = $set->flatMap(fn ($r) => $costs[$r->trip->id] ?? collect())->groupBy('name')
                    ->map(fn ($c) => round($c->sum(fn ($x) => (float) $x->amount) / $n, 2))->sortDesc()->take(3);

                return (object) [
                    'route' => collect([$places[$trip->from_place_id] ?? null, $places[$trip->to_place_id] ?? null])->filter()->join(' → ') . ($trip->trip_kind === 'empty' ? ' (' . __('empty') . ')' : ''),
                    'empty' => $trip->trip_kind === 'empty', 'trips' => $n,
                    'income' => round($income / $n, 2), 'cost' => round($cost / $n, 2), 'profit' => round(($income - $cost) / $n, 2),
                    'margin' => $income > 0 ? round(($income - $cost) / $income * 100, 1) : null,
                    'km' => $withKm->isNotEmpty() ? (int) round($withKm->avg('km')) : null,
                    'per_km' => $withKm->sum('km') > 0 ? round($withKm->sum('income') / $withKm->sum('km'), 2) : null,
                    'days' => $days->isNotEmpty() ? round($days->avg(), 1) : null,
                    'main_costs' => $main->all(),
                    'last' => ['date' => $last->trip->ended_at, 'income' => $last->income, 'trip' => $last->trip],
                    'best' => round($set->max('profit'), 2), 'worst' => round($set->min('profit'), 2),
                ];
            })->sortByDesc('trips')->values();
    }

    private function startUtc(CarbonImmutable $day): CarbonImmutable
    {
        return CarbonImmutable::parse($day->toDateString(), $this->context->company()->timezone ?: 'UTC')->startOfDay()->utc();
    }

    private function endUtc(CarbonImmutable $day): CarbonImmutable
    {
        return CarbonImmutable::parse($day->toDateString(), $this->context->company()->timezone ?: 'UTC')->endOfDay()->utc();
    }
}
