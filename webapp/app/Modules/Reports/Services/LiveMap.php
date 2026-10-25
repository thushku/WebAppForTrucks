<?php

namespace App\Modules\Reports\Services;

use App\Modules\Road\Models\GpsPoint;
use App\Modules\Road\Models\RoadEvent;
use App\Modules\Settings\Services\CompanySettings;
use App\Modules\Trips\Models\Trip;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Where the trucks are (blueprint 04 §2 Live map, 05): the last position the
 * crew's phones sent for every running trip, how long ago, and whether the
 * phone has gone quiet for longer than the company allows; and a trip's
 * whole route for the replay.
 *
 * Positions come from the phones only (decision A2); a truck's own GPS
 * device can feed the same table later.
 */
class LiveMap
{
    public function __construct(private CompanyContext $context, private CompanySettings $settings) {}

    /** @return Collection<int, array<string, mixed>> one row per running trip */
    public function positions(): Collection
    {
        $trips = Trip::with(['truck:id,reg_no', 'supplierVehicle:id,reg_no', 'crew.person:id,name,mobile', 'stops.place:id,name'])->where('status', 'running')->get()->keyBy('id');
        if ($trips->isEmpty()) {
            return collect();
        }

        $company = $this->context->id();
        $latest = DB::table('gps_points as g')
            ->joinSub(DB::table('gps_points')->where('company_id', $company)->whereIn('trip_id', $trips->keys())->groupBy('trip_id')->selectRaw('trip_id, max(recorded_at) as at'), 'x',
                fn ($join) => $join->on('x.trip_id', '=', 'g.trip_id')->on('x.at', '=', 'g.recorded_at'))
            ->where('g.company_id', $company)
            ->get(['g.trip_id', 'g.person_id', 'g.lat', 'g.lng', 'g.speed_kmh', 'g.heading', 'g.recorded_at'])
            ->keyBy('trip_id');

        $quiet = (int) $this->settings->get('gps_silence_alert_minutes');
        $now = CarbonImmutable::now();

        return $trips->map(function (Trip $trip) use ($latest, $quiet, $now) {
            $point = $latest[$trip->id] ?? null;
            $driver = $trip->crew->firstWhere('role', 'driver')?->person;
            $at = $point ? CarbonImmutable::parse($point->recorded_at, 'UTC') : null;
            $minutes = $at ? (int) $at->diffInMinutes($now) : null;

            return [
                'trip_id' => $trip->id, 'trip' => $trip->trip_number, 'vehicle' => $trip->vehicleLabel(), 'driver' => $driver?->name, 'mobile' => $driver?->mobile,
                'from' => $trip->stops->first()?->place?->name, 'to' => $trip->stops->last()?->place?->name,
                'lat' => $point ? (float) $point->lat : null, 'lng' => $point ? (float) $point->lng : null,
                'speed' => $point && $point->speed_kmh !== null ? (float) $point->speed_kmh : null,
                'at' => $at?->toIso8601String(), 'minutes' => $minutes,
                'quiet' => $minutes === null || ($quiet > 0 && $minutes > $quiet),
                'url' => route('trips.show', $trip->id),
            ];
        })->sortBy([['quiet', 'desc'], ['minutes', 'desc']])->values();
    }

    /**
     * A trip's route: every point in order (thinned to at most $max, keeping
     * the first and last), and the road entries that carry a place.
     */
    public function route(Trip $trip, int $max = 2000): array
    {
        $points = GpsPoint::where('trip_id', $trip->id)->orderBy('recorded_at')->get(['lat', 'lng', 'speed_kmh', 'recorded_at']);
        $count = $points->count();
        if ($count > $max) {
            $step = $count / $max;
            $points = collect(range(0, $max - 1))->map(fn ($i) => $points[(int) floor($i * $step)])->push($points->last());
        }

        $events = RoadEvent::where('trip_id', $trip->id)->live()->whereNotNull('lat')->orderBy('event_at')
            ->get(['id', 'type', 'amount', 'lat', 'lng', 'event_at', 'status']);

        return [
            'points' => $points->map(fn ($p) => [(float) $p->lat, (float) $p->lng, CarbonImmutable::parse($p->recorded_at, 'UTC')->toIso8601String(), $p->speed_kmh !== null ? (float) $p->speed_kmh : null])->values()->all(),
            'events' => $events->map(fn ($e) => [
                'lat' => (float) $e->lat, 'lng' => (float) $e->lng, 'label' => \App\Modules\Road\Services\EventTypes::label($e->type),
                'amount' => $e->amount !== null ? \App\Support\Money::company($e->amount) : null, 'at' => CarbonImmutable::parse($e->event_at, 'UTC')->toIso8601String(), 'status' => $e->status,
            ])->values()->all(),
            'total' => $count,
        ];
    }
}
