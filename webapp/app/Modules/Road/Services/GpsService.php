<?php

namespace App\Modules\Road\Services;

use App\Modules\People\Models\Person;
use App\Modules\Road\Models\GpsPoint;
use App\Modules\Trips\Models\Trip;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Location points from a crew phone, in batches (blueprint 03 §8): every
 * 30–60 s while moving, less when parked, kept on the phone without signal
 * and sent when it returns.
 *
 * A point is kept when it is plausible: inside the last week and not in the
 * future, real coordinates, and not a jump the truck cannot make (more than
 * 200 km/h from the point before). Each kept point is linked to the trip the
 * person was running at the time.
 */
class GpsService
{
    public const MAX_POINTS = 500;

    private const MAX_SPEED_KMH = 200;

    public function __construct(private CompanyContext $context) {}

    /**
     * @param array<int, array<string, mixed>> $points
     * @return array{kept: int, dropped: int, last_at: ?string}
     */
    public function store(Person $person, array $points): array
    {
        $now      = CarbonImmutable::now()->utc();
        $trips    = $this->tripsOf($person);
        $previous = GpsPoint::where('person_id', $person->id)->orderByDesc('recorded_at')->first(['recorded_at', 'lat', 'lng']);
        $rows     = [];
        $sent     = count($points);

        $points = collect($points)
            ->filter(fn ($p) => is_array($p) && isset($p['t'], $p['lat'], $p['lng']) && is_numeric($p['lat']) && is_numeric($p['lng']))
            ->map(function ($p) {
                try {
                    return $p + ['at' => CarbonImmutable::parse((string) $p['t'])->utc()];
                } catch (\Throwable) {
                    return null;
                }
            })
            ->filter()->sortBy(fn ($p) => $p['at']->getTimestamp())->values();

        $last = $previous ? ['at' => CarbonImmutable::instance($previous->recorded_at)->utc(), 'lat' => $previous->lat, 'lng' => $previous->lng] : null;

        foreach ($points as $p) {
            $lat = (float) $p['lat'];
            $lng = (float) $p['lng'];
            $at  = $p['at'];

            $plausible = abs($lat) <= 90 && abs($lng) <= 180 && ! ($lat == 0.0 && $lng == 0.0)
                && $at->gte($now->subDays(7)) && $at->lte($now->addMinutes(5));

            if ($plausible && $last && $at->gt($last['at'])) {
                $hours = $last['at']->diffInSeconds($at) / 3600;
                $km    = EventChecks::metres($last['lat'], $last['lng'], $lat, $lng) / 1000;
                $plausible = $hours <= 0 || $km < 1 || $km / $hours <= self::MAX_SPEED_KMH;
            } elseif ($plausible && $last && $at->lte($last['at'])) {
                // Already have this one (a batch sent twice) or an older one out of order.
                $plausible = ! GpsPoint::where('person_id', $person->id)->where('recorded_at', $at)->exists();
            }

            if (! $plausible) {
                continue;
            }

            $rows[] = [
                'company_id'  => $this->context->id(),
                'person_id'   => $person->id,
                'trip_id'     => $this->tripAt($trips, $at),
                'recorded_at' => $at,
                'lat'         => round($lat, 7),
                'lng'         => round($lng, 7),
                'accuracy_m'  => isset($p['acc']) ? min(65000, max(0, (int) $p['acc'])) : null,
                'speed_kmh'   => isset($p['spd']) ? min(9999, max(0, round((float) $p['spd'], 1))) : null,
                'heading'     => isset($p['hdg']) ? ((int) $p['hdg']) % 360 : null,
                'mock'        => (bool) ($p['mock'] ?? false),
                'received_at' => $now,
            ];
            if (! $last || $at->gt($last['at'])) {
                $last = ['at' => $at, 'lat' => $lat, 'lng' => $lng];
            }
        }

        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table('gps_points')->insert($chunk);
        }

        return ['kept' => count($rows), 'dropped' => $sent - count($rows), 'last_at' => $last ? $last['at']->toIso8601String() : null];
    }

    /** The person's trips that ran in the last week, with when. */
    private function tripsOf(Person $person)
    {
        return Trip::whereHas('crew', fn ($q) => $q->where('person_id', $person->id))
            ->whereNotNull('started_at')
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>=', now()->subDays(8)))
            ->get(['id', 'started_at', 'ended_at']);
    }

    private function tripAt($trips, CarbonImmutable $at): ?int
    {
        return $trips->first(fn ($t) => $at->gte($t->started_at) && ($t->ended_at === null || $at->lte($t->ended_at)))?->id;
    }
}
