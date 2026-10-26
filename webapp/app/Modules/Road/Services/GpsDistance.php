<?php

namespace App\Modules\Road\Services;

use App\Modules\Road\Models\GpsPoint;
use App\Modules\Trips\Models\Trip;

/**
 * A trip's distance by GPS against its odometer (M34). The crew phone's
 * points between the start and the end, without fake locations, poor fixes
 * (worse than 100 m) or jumps no truck can make (over 130 km/h), added up
 * point to point. Coverage is the share of the trip's time with a point at
 * least every 15 minutes; with 80 % or more, a gap of over 15 % between the
 * GPS and the odometer is flagged — a tampered odometer, or a run nobody
 * entered. Points can arrive late from a phone that was offline, so the
 * nightly run checks recent trips again (`fleet:gps-check`).
 */
class GpsDistance
{
    public const GAP_MINUTES = 15;

    public const MIN_COVERAGE = 80;

    public const TOLERANCE = 0.15;

    /** @return array{km: float, coverage: int, points: int} */
    public function measure(Trip $trip): array
    {
        if (! $trip->started_at) {
            return ['km' => 0.0, 'coverage' => 0, 'points' => 0];
        }
        $end = $trip->ended_at ?? now();
        $points = GpsPoint::where('trip_id', $trip->id)->where('mock', false)->where(fn ($q) => $q->whereNull('accuracy_m')->orWhere('accuracy_m', '<=', 100))
            ->whereBetween('recorded_at', [$trip->started_at, $end])->orderBy('recorded_at')->get(['lat', 'lng', 'recorded_at']);

        $metres = 0.0;
        $covered = 0;
        $prev = null;
        foreach ($points as $p) {
            if ($prev) {
                $seconds = max(1, $prev->recorded_at->diffInSeconds($p->recorded_at));
                $step = EventChecks::metres((float) $prev->lat, (float) $prev->lng, (float) $p->lat, (float) $p->lng);
                if ($step / $seconds * 3.6 > 130) {
                    continue; // a jump: keep the earlier point and go on
                }
                $metres += $step;
                if ($seconds <= self::GAP_MINUTES * 60) {
                    $covered += $seconds;
                }
            }
            $prev = $p;
        }
        $duration = max(1, $trip->started_at->diffInSeconds($end));

        return ['km' => round($metres / 1000, 1), 'coverage' => (int) min(100, round($covered / $duration * 100)), 'points' => $points->count()];
    }

    /** Measure an ended trip and keep the result on it. */
    public function check(Trip $trip): Trip
    {
        if (! $trip->ended_at) {
            return $trip;
        }
        $gps = $this->measure($trip);
        $odometer = $trip->start_odometer && $trip->end_odometer && $trip->end_odometer > $trip->start_odometer ? $trip->end_odometer - $trip->start_odometer : null;
        $check = match (true) {
            $gps['points'] === 0 => 'none',
            $gps['coverage'] < self::MIN_COVERAGE || $gps['km'] < 1 => 'thin',
            $odometer === null => 'thin',
            abs($odometer - $gps['km']) / $gps['km'] > self::TOLERANCE => 'differs',
            default => 'ok',
        };
        $trip->forceFill(['gps_km' => $gps['points'] ? $gps['km'] : null, 'gps_coverage' => $gps['points'] ? $gps['coverage'] : null, 'km_check' => $check])->saveQuietly();

        return $trip;
    }

    /** How far apart the two are, as a share of the GPS distance (for the screens). */
    public static function gap(Trip $trip): ?float
    {
        $odometer = $trip->start_odometer && $trip->end_odometer ? $trip->end_odometer - $trip->start_odometer : null;

        return $odometer !== null && (float) $trip->gps_km > 0 ? round(($odometer - (float) $trip->gps_km) / (float) $trip->gps_km * 100, 1) : null;
    }
}
