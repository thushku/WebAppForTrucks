<?php

namespace App\Modules\Trips\Services;

use App\Modules\Road\Models\RoadEvent;
use App\Modules\Trips\Models\Trip;
use App\Modules\Trips\Models\TripOfficeEntry;
use App\Modules\Trips\Models\TripSettlement;

/**
 * The trip's direct profit so far (blueprint 03 §9): income less the costs
 * that belong to this trip. Shared costs (salary share, EMI, tyre wear) come
 * with accounting and reports.
 *
 *   income = freight and charges of its loads + other charges billed
 *            + office income + commission earned
 *   costs  = approved road spending (every payment mode: fuel on the
 *            company card is a cost too) + office expenses + lorry hire
 *            + commission we pay + bata
 *
 * Entries still waiting for approval are shown apart, not counted.
 */
class TripProfit
{
    /** @return array<string, float> */
    public function forTrip(Trip $trip): array
    {
        $trip->loadMissing(['consignments', 'hireChallans', 'commissions']);

        $loads   = $trip->consignments->where('status', '!=', 'cancelled')->sum(fn ($c) => $c->totalCharges());
        $office  = TripOfficeEntry::where('trip_id', $trip->id)->live()->selectRaw('kind, sum(amount) as total')->groupBy('kind')->pluck('total', 'kind');
        $road    = RoadEvent::where('trip_id', $trip->id)->whereNotNull('amount')->where('type', '!=', 'money_received')
            ->whereIn('status', ['approved', ...RoadEvent::OPEN])->selectRaw('status, sum(amount) as total')->groupBy('status')->pluck('total', 'status');
        $earned  = $trip->commissions->where('direction', 'receivable')->sum('amount');
        $paid    = $trip->commissions->where('direction', 'payable')->sum('amount');
        $hire    = $trip->hireChallans->where('status', '!=', 'cancelled')->sum('hire_amount');
        $bata    = TripSettlement::where('trip_id', $trip->id)->current()->sum('bata_amount');

        $income = (float) $loads + (float) $trip->other_charges + (float) ($office['income'] ?? 0) + (float) $earned;
        $costs  = [
            'road'       => (float) ($road['approved'] ?? 0),
            'office'     => (float) ($office['expense'] ?? 0),
            'hire'       => (float) $hire,
            'commission' => (float) $paid,
            'bata'       => (float) $bata,
        ];

        return [
            'loads'         => round((float) $loads, 2),
            'other_charges' => round((float) $trip->other_charges, 2),
            'office_income' => round((float) ($office['income'] ?? 0), 2),
            'commission_earned' => round((float) $earned, 2),
            'income'        => round($income, 2),
            'road'          => round($costs['road'], 2),
            'office_expense' => round($costs['office'], 2),
            'hire'          => round($costs['hire'], 2),
            'commission_paid' => round($costs['commission'], 2),
            'bata'          => round($costs['bata'], 2),
            'costs'         => round(array_sum($costs), 2),
            'profit'        => round($income - array_sum($costs), 2),
            'waiting'       => round(collect(RoadEvent::OPEN)->sum(fn ($s) => (float) ($road[$s] ?? 0)), 2),
            'per_km'        => $trip->start_odometer && $trip->end_odometer && $trip->end_odometer > $trip->start_odometer
                ? round(($income - array_sum($costs)) / ($trip->end_odometer - $trip->start_odometer), 2) : 0.0,
        ];
    }
}
