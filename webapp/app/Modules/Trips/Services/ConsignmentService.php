<?php

namespace App\Modules\Trips\Services;

use App\Modules\Parties\Services\RateFinder;
use App\Modules\Trips\Models\Consignment;
use App\Modules\Trips\Models\ConsignmentEwaybill;
use App\Modules\Trips\Models\Trip;
use App\Modules\Trips\Models\TripStop;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Loads on a trip. Freight is always worked out here from the basis, the
 * rate and the weight / km / packages — a total typed in the form is not
 * trusted — with the rate contract's minimum guaranteed weight applied.
 *
 * A load can be added to a trip that is already running (a part load picked
 * up on the way); it gets its LR number at once. Once a trip has started, a
 * load's money and route are fixed here; corrections go through the LR /
 * invoice (M9).
 */
class ConsignmentService
{
    public function __construct(private RateFinder $rates, private TripService $trips) {}

    /** @param array<string, mixed> $data */
    public function save(Trip $trip, ?Consignment $consignment, array $data, array $ewaybills = []): Consignment
    {
        self::assertNotBilled($consignment);
        if (in_array($trip->status, ['ended', 'settled', 'closed', 'cancelled'], true)) {
            throw ValidationException::withMessages(['consignment' => __('This trip is :status; its loads can no longer change.', ['status' => mb_strtolower(__(Trip::STATUSES[$trip->status]))])]);
        }
        if ($consignment && $trip->status === 'running' && $consignment->exists) {
            throw ValidationException::withMessages(['consignment' => __('The trip is running: this load is fixed. Correct it on the LR or bill.')]);
        }
        $this->assertStops($trip, $data);

        return DB::transaction(function () use ($trip, $consignment, $data, $ewaybills) {
            $data['freight'] = $this->freight($trip, $data);

            $consignment ??= new Consignment(['trip_id' => $trip->id, 'created_by' => auth()->id()]);
            $consignment->fill($data)->save();

            $consignment->ewaybills()->delete();
            foreach ($ewaybills as $e) {
                ConsignmentEwaybill::create(['consignment_id' => $consignment->id] + $e);
            }

            // Loads added after the trip was sent get their LR at once.
            if (! $consignment->lr_number && ! $trip->isDraft()) {
                $this->trips->numberLr($consignment);
                if ($trip->status === 'running') {
                    $consignment->forceFill(['status' => 'in_transit'])->save();
                }
            }

            return $consignment;
        });
    }

    public function cancel(Trip $trip, Consignment $consignment, string $reason): void
    {
        self::assertNotBilled($consignment);
        if (! $trip->isPlanning()) {
            throw ValidationException::withMessages(['consignment' => __('The trip has started; cancel the LR from the LR screen (M9).')]);
        }
        $consignment->forceFill(['status' => 'cancelled', 'remarks' => trim(($consignment->remarks ? $consignment->remarks . "\n" : '') . __('Cancelled: :reason', ['reason' => $reason]))])->save();
    }

    /**
     * Freight from the basis. Weight-based freight uses the charged weight
     * (else the actual), and at least the contract's minimum weight.
     */
    public function freight(Trip $trip, array $data): float
    {
        $rate   = (float) ($data['rate'] ?? 0);
        $weight = (float) ($data['charged_weight_mt'] ?? $data['actual_weight_mt'] ?? 0);
        $min    = 0.0;
        if (! empty($data['rate_contract_line_id'])) {
            $min = (float) (\App\Modules\Parties\Models\RateContractLine::find($data['rate_contract_line_id'])?->min_weight_mt ?? 0);
        }

        return round(match ($data['freight_basis'] ?? 'trip') {
            'tonne'   => $rate * max($weight, $min),
            'kg'      => $rate * max($weight, $min) * 1000,
            'km'      => $rate * (float) ($trip->planned_km ?? 0),
            'package' => $rate * (int) ($data['packages'] ?? 0),
            default   => $rate,
        }, 2);
    }

    /** The agreed rate for this party, route, material and truck type, for the form to fill in. */
    public function suggestRate(int $partyId, int $fromPlaceId, int $toPlaceId, ?string $material, ?string $truckType, \Carbon\CarbonInterface $on): ?array
    {
        $line = $this->rates->find($partyId, $fromPlaceId, $toPlaceId, $material, $truckType, $on);

        return $line ? [
            'line_id'    => $line->id,
            'basis'      => $line->basis,
            'rate'       => (float) $line->rate,
            'min_weight' => $line->min_weight_mt !== null ? (float) $line->min_weight_mt : null,
            'detention_free_hours'   => $line->detention_free_hours,
            'detention_rate_per_day' => $line->detention_rate_per_day !== null ? (float) $line->detention_rate_per_day : null,
            'contract'   => $line->contract->label,
        ] : null;
    }

    private function assertStops(Trip $trip, array $data): void
    {
        $stops = TripStop::where('trip_id', $trip->id)->pluck('seq', 'id');
        $pick  = $stops[$data['pickup_stop_id'] ?? 0] ?? null;
        $drop  = $stops[$data['drop_stop_id'] ?? 0] ?? null;

        if ($pick === null || $drop === null) {
            throw ValidationException::withMessages(['pickup_stop_id' => __('Choose where the load is picked up and dropped (stops of this trip).')]);
        }
        if ($drop <= $pick) {
            throw ValidationException::withMessages(['drop_stop_id' => __('The drop must come after the pickup.')]);
        }
    }

    /**
     * An LR on an issued bill is fixed: client, freight, charges, date and
     * route (13 §A #6). Changes go through a credit or debit note, a
     * supplementary bill, or voiding the bill.
     */
    public static function assertNotBilled(?Consignment $consignment): void
    {
        if ($consignment?->invoice_id) {
            $number = \App\Modules\Invoicing\Models\Invoice::whereKey($consignment->invoice_id)->value('number');
            throw ValidationException::withMessages(['consignment' => __('LR :lr is on bill :number: it can no longer change. Use a credit or debit note, a supplementary bill, or void the bill.', ['lr' => $consignment->lr_number, 'number' => $number])]);
        }
    }
}
