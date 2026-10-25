<?php

namespace App\Modules\Road\Services;

use App\Modules\Road\Models\GpsPoint;
use App\Modules\Road\Models\RoadEvent;
use App\Modules\Settings\Services\CompanySettings;
use App\Modules\Trips\Models\Trip;
use Carbon\CarbonImmutable;

/**
 * The automatic checks on a road event (blueprint 03 §5.2a). They never stop
 * the phone saving — a driver without signal must always be able to record —
 * they put flags on the entry for the manager.
 */
class EventChecks
{
    public const LABELS = [
        'odometer_order' => 'Odometer lower than an earlier reading',
        'tank_size'      => 'More litres than the tank holds',
        'fuel_mileage'   => 'Mileage since the last full tank is outside the normal range',
        'fuel_amount'    => 'Litres × rate does not match the amount',
        'far_from_track' => 'Entered far from where the truck was',
        'mock_location'  => 'The phone reported a fake location',
        'clock_changed'  => 'The phone clock looks changed',
        'late_entry'     => 'Entered long after the time it claims',
        'cash_limit'     => 'Cash to one payee in a day above the limit',
        'same_photo'     => 'The same photo was used before',
    ];

    public function __construct(private CompanySettings $settings) {}

    /** @return string[] flag codes */
    public function run(RoadEvent $event, Trip $trip): array
    {
        $flags = [];
        $data  = $event->data ?? [];
        $truck = $trip->truck;

        if ($event->odometer !== null) {
            $previous = RoadEvent::where('trip_id', $trip->id)->live()->whereNotNull('odometer')->where('trusted_at', '<', $event->trusted_at)->max('odometer');
            $floor = max((int) $previous, (int) ($trip->start_odometer ?? 0));
            if ($floor && $event->odometer < $floor) {
                $flags[] = 'odometer_order';
            }
        }

        if ($event->type === 'fuel') {
            $litres = (float) ($data['litres'] ?? 0);
            if ($truck?->fuel_tank_l && $litres > $truck->fuel_tank_l) {
                $flags[] = 'tank_size';
            }
            if (! empty($data['rate']) && $event->amount && abs($litres * (float) $data['rate'] - (float) $event->amount) > max(5, 0.02 * (float) $event->amount)) {
                $flags[] = 'fuel_amount';
            }
            if (! empty($data['full_tank']) && $event->odometer && $truck) {
                if ($this->mileageOutOfRange($event, $truck)) {
                    $flags[] = 'fuel_mileage';
                }
            }
        }

        if ($event->mock_location) {
            $flags[] = 'mock_location';
        }
        if ($event->event_at && abs($event->event_at->diffInSeconds($event->trusted_at, false)) > 300) {
            $flags[] = 'clock_changed';
        }
        if ($event->recorded_at && $event->event_at && $event->event_at->diffInMinutes($event->recorded_at, false) > 120) {
            $flags[] = 'late_entry';
        }

        if ($event->payment_mode === 'cash' && $event->amount) {
            $limit = (float) $this->settings->get('cash_limit_per_payee_day');
            if ($limit > 0) {
                $payee = $data['pump'] ?? $data['workshop'] ?? $data['paid_to'] ?? null;
                $day   = CarbonImmutable::instance($event->trusted_at)->setTimezone(app(\App\Platform\Tenancy\CompanyContext::class)->company()->timezone ?: 'UTC');
                $sum   = (float) $event->amount;
                if ($payee) {
                    $sum += (float) RoadEvent::where('trip_id', $trip->id)->live()->where('payment_mode', 'cash')->where('id', '!=', $event->id ?? 0)
                        ->whereBetween('trusted_at', [$day->startOfDay()->utc(), $day->endOfDay()->utc()])
                        ->get()->filter(fn ($e) => in_array($payee, [$e->data['pump'] ?? null, $e->data['workshop'] ?? null, $e->data['paid_to'] ?? null], true))
                        ->sum('amount');
                }
                if ($sum > $limit) {
                    $flags[] = 'cash_limit';
                }
            }
        }

        if ($event->lat !== null && in_array($event->type, ['fuel', 'toll', 'weighbridge', 'repair'], true) && $this->farFromTrack($event, $trip)) {
            $flags[] = 'far_from_track';
        }

        return array_values(array_unique($flags));
    }

    /**
     * km per litre between this full tank and the one before on the same
     * truck, against the truck's normal range (±25 %).
     */
    private function mileageOutOfRange(RoadEvent $event, $truck): bool
    {
        $low  = (float) min(array_filter([(float) $truck->mileage_loaded_kmpl, (float) $truck->mileage_empty_kmpl]) ?: [0]);
        $high = (float) max((float) $truck->mileage_loaded_kmpl, (float) $truck->mileage_empty_kmpl);
        if (! $low || ! $high) {
            return false;
        }

        $fuelEvents = RoadEvent::query()->live()->where('type', 'fuel')->whereNotNull('odometer')
            ->whereHas('trip', fn ($q) => $q->where('truck_id', $truck->id))
            ->where('trusted_at', '<', $event->trusted_at)->orderByDesc('trusted_at')->limit(20)->get();
        $previousFull = $fuelEvents->first(fn ($e) => ! empty($e->data['full_tank']));
        if (! $previousFull || $event->odometer <= $previousFull->odometer) {
            return false;
        }

        // Litres bought after the last full tank, this fill included.
        $litres = (float) ($event->data['litres'] ?? 0) + $fuelEvents->filter(fn ($e) => $e->trusted_at > $previousFull->trusted_at)->sum(fn ($e) => (float) ($e->data['litres'] ?? 0));
        if ($litres <= 0) {
            return false;
        }
        $kmpl = ($event->odometer - $previousFull->odometer) / $litres;

        return $kmpl < $low * 0.75 || $kmpl > $high * 1.25;
    }

    /** No GPS point of this trip within 300 m in the 10 minutes around the event (only when there are points then). */
    private function farFromTrack(RoadEvent $event, Trip $trip): bool
    {
        $points = GpsPoint::where('trip_id', $trip->id)
            ->whereBetween('recorded_at', [$event->trusted_at->copy()->subMinutes(10), $event->trusted_at->copy()->addMinutes(10)])
            ->limit(200)->get(['lat', 'lng']);
        if ($points->isEmpty()) {
            return false;
        }

        return $points->min(fn ($p) => self::metres($event->lat, $event->lng, $p->lat, $p->lng)) > 300;
    }

    public static function metres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }
}
