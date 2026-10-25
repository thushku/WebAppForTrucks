<?php

namespace App\Modules\Road\Services;

use App\Modules\Road\Models\DrivingShift;
use App\Modules\Road\Models\RoadEvent;
use App\Modules\Trips\Models\Trip;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Time at the wheel per person (decision D11, P2): from "I am driving now"
 * to the next driver change, less the rests the crew recorded in between
 * (a night halt is not driving). The base of the driving-limit alerts and of
 * the hours on the trip page and in reports.
 */
class DrivingHours
{
    /**
     * @return Collection<int, array{name: string, minutes: int, now: bool}> by person id
     */
    public function forTrip(Trip $trip): Collection
    {
        $shifts = DrivingShift::with('person:id,name')->where('trip_id', $trip->id)->orderBy('started_at')->get();
        if ($shifts->isEmpty()) {
            return collect();
        }
        $rests = $this->rests($trip);

        return $shifts->groupBy('person_id')->map(fn (Collection $own) => [
            'name'    => $own->first()->person->name,
            'minutes' => (int) $own->sum(fn (DrivingShift $s) => $this->minutes($s, $rests)),
            'now'     => $own->contains(fn (DrivingShift $s) => $s->ended_at === null),
        ]);
    }

    /** Minutes of one shift, without the rests inside it. */
    public function minutes(DrivingShift $shift, Collection $rests): int
    {
        $from = CarbonImmutable::instance($shift->started_at);
        $to   = CarbonImmutable::instance($shift->ended_at ?? now());
        $total = max(0, $from->diffInMinutes($to));

        foreach ($rests as [$restFrom, $restTo]) {
            $start = $restFrom->max($from);
            $end   = $restTo->min($to);
            if ($end->gt($start)) {
                $total -= $start->diffInMinutes($end);
            }
        }

        return (int) max(0, round($total));
    }

    /**
     * Rest periods on the trip: each "rest started" until the next "rest
     * ended" (or now, while still resting).
     *
     * @return Collection<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public function rests(Trip $trip): Collection
    {
        $events = RoadEvent::where('trip_id', $trip->id)->live()->whereIn('type', ['rest_start', 'rest_end'])->orderBy('trusted_at')->get(['type', 'trusted_at']);
        $rests  = collect();
        $open   = null;

        foreach ($events as $e) {
            $at = CarbonImmutable::instance($e->trusted_at);
            if ($e->type === 'rest_start') {
                $open ??= $at;
            } elseif ($open) {
                $rests->push([$open, $at]);
                $open = null;
            }
        }
        if ($open) {
            $rests->push([$open, CarbonImmutable::now()]);
        }

        return $rests;
    }
}
