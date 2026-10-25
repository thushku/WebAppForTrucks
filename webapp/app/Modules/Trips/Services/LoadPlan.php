<?php

namespace App\Modules\Trips\Services;

use App\Modules\Trips\Models\Trip;

/**
 * The capacity check (blueprint 12 §3.2): how much is on board between each
 * pair of stops, against the truck's payload — "Kochi → Thrissur: 7.2 / 9 MT,
 * 80 %". Over the payload is a warning before dispatch, not a block.
 */
class LoadPlan
{
    /** @return array<int, array{from: string, to: string, weight: float, payload: ?float, percent: ?int, loads: int}> */
    public function legs(Trip $trip): array
    {
        $trip->loadMissing(['stops.place', 'consignments', 'truck', 'supplierVehicle']);
        $stops   = $trip->stops->values();
        $payload = $trip->truck_source === 'hired' ? (float) ($trip->supplierVehicle?->capacity_mt ?? 0) : (float) ($trip->truck?->payload_mt ?? 0);
        $seqOf   = $stops->pluck('seq', 'id');
        $loads   = $trip->consignments->where('status', '!=', 'cancelled');

        $legs = [];
        for ($i = 0; $i < $stops->count() - 1; $i++) {
            $from = $stops[$i];
            $to   = $stops[$i + 1];
            $onBoard = $loads->filter(fn ($c) => ($seqOf[$c->pickup_stop_id] ?? 1) <= $from->seq && ($seqOf[$c->drop_stop_id] ?? PHP_INT_MAX) >= $to->seq);
            $weight  = round($onBoard->sum(fn ($c) => $c->weight()), 3);

            $legs[] = [
                'from'    => $from->place->name,
                'to'      => $to->place->name,
                'weight'  => $weight,
                'payload' => $payload ?: null,
                'percent' => $payload ? (int) round($weight / $payload * 100) : null,
                'loads'   => $onBoard->count(),
            ];
        }

        return $legs;
    }

    public function overloaded(Trip $trip): bool
    {
        return collect($this->legs($trip))->contains(fn ($leg) => $leg['percent'] !== null && $leg['percent'] > 100);
    }
}
