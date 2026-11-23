<?php

namespace App\Modules\Fleet\Services;

use App\Modules\Fleet\Models\ServiceSchedule;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Trips\Models\Trip;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Service due, and when (M50, blueprint 08 phase 4 "maintenance
 * predictions"). Each schedule falls due at a km or on a date (M4); here
 * the km one becomes a date too, from the truck's own pace — the km its
 * trips ran over the last 60 days —, so the office sees "engine oil around
 * 12 Dec" and can plan the workshop between trips. A trip that would run
 * past a service is warned about when it is sent, never stopped.
 */
class Maintenance
{
    /** The days the truck's pace is measured over. */
    public const PACE_DAYS = 60;

    /** What counts as coming up. */
    public const SOON_DAYS = 30;

    public function __construct(private CompanyContext $context) {}

    /**
     * Km a day over the last 60 days, from the odometer readings of the
     * trips that ran in them; null when it has not run (no pace to go by).
     *
     * @param  ?Collection<int, int>  $truckIds  several trucks at once
     * @return array<int, float> truck id => km a day
     */
    public function paces(Collection $truckIds): array
    {
        $since = $this->today()->subDays(self::PACE_DAYS);
        $km = Trip::whereIn('truck_id', $truckIds)->whereNotNull('start_odometer')->whereNotNull('end_odometer')->where('started_at', '>=', $since)
            ->whereColumn('end_odometer', '>', 'start_odometer')->where('status', '!=', 'cancelled')
            ->selectRaw('truck_id, sum(end_odometer - start_odometer) as km')->groupBy('truck_id')->pluck('km', 'truck_id');

        return $km->map(fn ($k) => round((float) $k / self::PACE_DAYS, 1))->filter(fn ($p) => $p > 0)->all();
    }

    /**
     * One schedule's state with its expected date: the date it is due, or
     * the day the truck reaches its km at its pace, whichever is first.
     *
     * @return array{schedule: ServiceSchedule, state: string, due_km: ?int, km_left: ?int, due_on: ?CarbonImmutable, expected_on: ?CarbonImmutable, by: ?string, pace: ?float}
     */
    public function forecast(ServiceSchedule $schedule, Truck $truck, ?float $pace): array
    {
        $today = $this->today();
        $dueKm = $schedule->dueKm();
        $kmLeft = $dueKm !== null && $truck->odometer_km !== null ? $dueKm - (int) $truck->odometer_km : null;
        $byKm = $kmLeft !== null && $pace ? $today->addDays(max(0, (int) floor($kmLeft / $pace))) : null;
        $dueOn = $schedule->dueOn();
        $expected = collect([$byKm, $dueOn])->filter()->sort()->first();

        return [
            'schedule' => $schedule, 'state' => $schedule->state($truck->odometer_km), 'due_km' => $dueKm, 'km_left' => $kmLeft, 'due_on' => $dueOn,
            'expected_on' => $expected, 'by' => $expected === null ? null : ($byKm && $expected->equalTo($byKm) ? 'km' : 'date'), 'pace' => $pace,
        ];
    }

    /**
     * Every active schedule of these trucks that is overdue or expected
     * within the days given, soonest first.
     *
     * @param  Collection<int, Truck>  $trucks
     * @return Collection<int, array<string, mixed>>
     */
    public function due(Collection $trucks, int $withinDays = self::SOON_DAYS): Collection
    {
        if ($trucks->isEmpty()) {
            return collect();
        }
        $paces = $this->paces($trucks->pluck('id'));
        $limit = $this->today()->addDays($withinDays);
        $byId = $trucks->keyBy('id');

        return ServiceSchedule::where('active', true)->whereIn('truck_id', $trucks->pluck('id'))->orderBy('name')->get()
            ->map(fn (ServiceSchedule $s) => $this->forecast($s, $byId[$s->truck_id], $paces[$s->truck_id] ?? null) + ['truck' => $byId[$s->truck_id]])
            // Overdue always; else the likely date decides, and only without one (no pace, no date) the schedule's own "soon".
            ->filter(fn ($r) => $r['state'] === 'overdue' || ($r['expected_on'] ? $r['expected_on']->lte($limit) : $r['state'] === 'soon'))
            ->sortBy(fn ($r) => [$r['state'] === 'overdue' ? 0 : 1, $r['expected_on']?->toDateString() ?? '9999'])->values();
    }

    /**
     * The services a trip would run past (shown when it is sent): due at a
     * km the truck reaches within the trip's planned km, or due by date
     * before it is likely back.
     *
     * @return string[]
     */
    public function tripWarnings(Trip $trip): array
    {
        $truck = $trip->truck;
        if (! $truck || $trip->truck_source === 'hired' || $truck->ownership === 'attached') {
            return [];
        }
        $odo = (int) ($truck->odometer_km ?? 0);
        $end = $odo + (int) ($trip->planned_km ?? 0);
        $warnings = [];
        foreach (ServiceSchedule::where('truck_id', $truck->id)->where('active', true)->orderBy('name')->get() as $s) {
            $dueKm = $s->dueKm();
            if ($dueKm !== null && $truck->odometer_km !== null && $dueKm <= $odo) {
                $warnings[] = __(':service on :truck was due at :km km — it is overdue.', ['service' => $s->name, 'truck' => $truck->reg_no, 'km' => number_format($dueKm)]);
            } elseif ($dueKm !== null && $truck->odometer_km !== null && $trip->planned_km && $dueKm <= $end) {
                $warnings[] = __(':service on :truck is due at :km km; this trip runs to about :end km.', ['service' => $s->name, 'truck' => $truck->reg_no, 'km' => number_format($dueKm), 'end' => number_format($end)]);
            } elseif (($on = $s->dueOn()) && $on->lte(CarbonImmutable::parse(($trip->trip_date ?? now())->toDateString())->addDays(3))) {
                $warnings[] = __(':service on :truck is due by :date.', ['service' => $s->name, 'truck' => $truck->reg_no, 'date' => $on->format('d M Y')]);
            }
        }

        return $warnings;
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->context->company()->timezone ?: 'UTC')->startOfDay();
    }
}
