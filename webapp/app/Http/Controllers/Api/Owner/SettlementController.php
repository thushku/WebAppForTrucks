<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Api\Owner\Concerns\RunsOnce;
use App\Http\Controllers\Controller;
use App\Modules\People\Models\Person;
use App\Modules\Trips\Models\Trip;
use App\Modules\Trips\Models\TripSettlement;
use App\Modules\Trips\Services\SettlementService;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Settling a trip from the owner app (M42): each crew member's trip cash —
 * advances, money collected, spent from their pocket, the balance and the
 * suggested bata —, what still stands in the way, then settle each person
 * and close the trip. The web's rules (`SettlementService`); every write
 * once per Idempotency-Key (§12).
 */
class SettlementController extends Controller
{
    use RunsOnce;

    public function __construct(protected CompanyContext $context, private SettlementService $settlements) {}

    public function show(string $uuid): JsonResponse
    {
        return response()->json($this->state($this->trip($uuid)));
    }

    public function settle(Request $request, string $uuid, Person $person): JsonResponse
    {
        $trip = $this->trip($uuid);

        return $this->once($request, 'trip.settle', function () use ($request, $trip, $person) {
            $data = $request->validate([
                'mode' => ['required', Rule::in(array_keys(TripSettlement::MODES))], 'reference' => ['nullable', 'string', 'max:60'],
                'note' => ['nullable', 'string', 'max:255'], 'bata_amount' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            ]);
            $this->settlements->settle($trip, $person, $data);

            return $this->state($trip->fresh());
        });
    }

    /** A trip with no crew of ours (a hired truck): settled with nothing to count. */
    public function settleAll(Request $request, string $uuid): JsonResponse
    {
        $trip = $this->trip($uuid);

        return $this->once($request, 'trip.settle_all', function () use ($trip) {
            $this->settlements->settleWithoutCrew($trip);

            return $this->state($trip->fresh());
        });
    }

    public function close(Request $request, string $uuid): JsonResponse
    {
        $trip = $this->trip($uuid);

        return $this->once($request, 'trip.close', function () use ($trip) {
            $this->settlements->close($trip);

            return $this->state($trip->fresh());
        });
    }

    private function trip(string $uuid): Trip
    {
        return Trip::inBranch()->where('uuid', $uuid)->firstOrFail();
    }

    private function state(Trip $trip): array
    {
        $blockers = $trip->status === 'ended' ? $this->settlements->blockers($trip) : [];

        return [
            'trip' => ['uuid' => $trip->uuid, 'number' => $trip->trip_number, 'status' => $trip->status, 'status_label' => __(Trip::STATUSES[$trip->status] ?? $trip->status)],
            'can_settle' => $trip->status === 'ended' && ! $blockers, 'can_close' => $trip->status === 'settled',
            'blockers' => $blockers ? $this->settlements->blockerText($blockers) : null,
            'modes' => collect(TripSettlement::MODES)->map(fn ($label, $code) => ['code' => $code, 'label' => __($label)])->values(),
            'crew' => in_array($trip->status, ['ended', 'settled', 'closed'], true) ? $this->settlements->accounts($trip)->map(fn ($a) => [
                'person' => ['id' => $a['person']->id, 'name' => $a['person']->name], 'role' => $a['role'],
                'advances' => $a['advances'], 'collected' => $a['collected'], 'received' => $a['received'], 'spent' => $a['spent'],
                // Positive: they return it; negative: the company pays them.
                'balance' => $a['balance'], 'waiting' => $a['waiting'], 'bata_days' => $a['bata_days'], 'bata' => $a['bata'],
                'settlement' => $a['settlement'] ? ['mode' => $a['settlement']->mode, 'mode_label' => __(TripSettlement::MODES[$a['settlement']->mode] ?? $a['settlement']->mode),
                    'bata' => (float) $a['settlement']->bata_amount, 'reference' => $a['settlement']->reference, 'at' => $a['settlement']->settled_at?->toIso8601String()] : null,
            ])->values() : [],
        ];
    }
}
