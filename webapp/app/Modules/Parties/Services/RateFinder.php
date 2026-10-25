<?php

namespace App\Modules\Parties\Services;

use App\Modules\Parties\Models\RateContractLine;
use Carbon\CarbonInterface;

/**
 * Finds the agreed rate for a trip or LR: the party, the route, the material
 * and the truck type, on the trip's date (blueprint 09 §2.1 "Rates fill the
 * trip/LR automatically").
 *
 * Only approved contracts valid on that date count. A line for the exact
 * material and truck type beats a line for "any"; among equals, the newest
 * contract version wins.
 */
class RateFinder
{
    public function find(int $partyId, int $fromPlaceId, int $toPlaceId, ?string $material, ?string $truckType, CarbonInterface $on): ?RateContractLine
    {
        $date = $on->toDateString();

        $lines = RateContractLine::query()
            ->with(['contract'])
            ->where('from_place_id', $fromPlaceId)
            ->where('to_place_id', $toPlaceId)
            ->whereHas('contract', fn ($q) => $q->where('party_id', $partyId)->where('status', 'approved')
                ->where('valid_from', '<=', $date)
                ->where(fn ($w) => $w->whereNull('valid_to')->orWhere('valid_to', '>=', $date)))
            ->where(fn ($q) => $q->whereNull('material')->orWhere('material', $material))
            ->where(fn ($q) => $q->whereNull('truck_type')->orWhere('truck_type', $truckType))
            ->get();

        return $lines->sortByDesc(fn (RateContractLine $l) => [
            ($l->material !== null ? 2 : 0) + ($l->truck_type !== null ? 1 : 0),
            $l->contract->valid_from->timestamp,
            $l->contract->version,
        ])->first();
    }

    /**
     * The freight a line gives for a load. Weight-based rates charge at least
     * the minimum guaranteed weight.
     */
    public function freight(RateContractLine $line, ?float $weightMt = null, ?float $km = null, ?int $packages = null): ?float
    {
        $rate = (float) $line->rate;

        return match ($line->basis) {
            'trip'    => $rate,
            'tonne'   => $weightMt === null ? null : round($rate * max($weightMt, (float) $line->min_weight_mt), 2),
            'kg'      => $weightMt === null ? null : round($rate * max($weightMt, (float) $line->min_weight_mt) * 1000, 2),
            'km'      => $km === null ? null : round($rate * $km, 2),
            'package' => $packages === null ? null : round($rate * $packages, 2),
            default   => null,
        };
    }
}
