<?php

namespace App\Modules\Trips\Services;

use App\Modules\Documents\Services\Compliance;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Fleet\Services\FleetService;
use App\Modules\People\Models\Person;
use App\Modules\Settings\Models\Place;
use App\Modules\Settings\Services\DocumentNumbering;
use App\Modules\Trips\Models\Consignment;
use App\Modules\Trips\Models\Trip;
use App\Modules\Trips\Models\TripCrew;
use App\Modules\Trips\Models\TripStop;
use App\Platform\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The life of a trip (blueprint 03 §3), each step in one transaction:
 *
 *   draft ──send to crew──► assigned ──start──► running ──end──► ended
 *     └────────── cancel (with a reason, before it runs) ──────────┘
 *
 * "Send to crew" is the dispatch gate: the truck and every crew member must
 * be free and have no expired dispatch-blocking paper — or a manager writes
 * why it goes anyway (kept on the trip and in the audit log). It also fixes
 * the trip number (P1) and numbers the LRs.
 *
 * Accept / reject and the crew's start and end come from the phone
 * (Road\Services\CrewCommands); the office can start and end a trip itself
 * when the crew has no app.
 */
class TripService
{
    public function __construct(
        private DocumentNumbering $numbering,
        private Compliance $compliance,
        private FleetService $fleet,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param array<string, mixed>              $fields trip columns
     * @param array<int, array{person_id: int, role: string}> $crew
     */
    public function create(array $fields, array $crew, ?array $consignment = null, array $ewaybills = []): Trip
    {
        return DB::transaction(function () use ($fields, $crew, $consignment, $ewaybills) {
            $trip = Trip::create($fields + ['created_by' => auth()->id(), 'status' => 'draft']);
            $this->syncCrew($trip, $crew);

            // The route's two ends are the first stops; part loads add more between them.
            $from = TripStop::create(['trip_id' => $trip->id, 'seq' => 1, 'place_id' => $trip->from_place_id, 'kind' => 'load']);
            $to   = TripStop::create(['trip_id' => $trip->id, 'seq' => 2, 'place_id' => $trip->to_place_id, 'kind' => 'unload']);

            if ($consignment) {
                $load = Consignment::create($consignment + ['trip_id' => $trip->id, 'pickup_stop_id' => $from->id, 'drop_stop_id' => $to->id, 'created_by' => auth()->id()]);
                foreach ($ewaybills as $e) {
                    $load->ewaybills()->create($e);
                }
            }

            return $trip;
        });
    }

    /** Change the plan (route, truck, crew, budget) while the trip has not started. */
    public function update(Trip $trip, array $fields, array $crew): void
    {
        $this->assertPlanning($trip);

        DB::transaction(function () use ($trip, $fields, $crew) {
            $trip->fill($fields)->save();
            $this->syncCrew($trip, $crew);

            // Keep the first and last stops on the route's ends.
            $stops = $trip->stops()->get();
            $stops->first()?->forceFill(['place_id' => $trip->from_place_id])->save();
            if ($stops->count() > 1) {
                $stops->last()->forceFill(['place_id' => $trip->to_place_id])->save();
            }
        });
    }

    /** @param array<int, array{person_id: int, role: string}> $crew */
    public function syncCrew(Trip $trip, array $crew): void
    {
        $byPerson = collect($crew)->keyBy('person_id');
        if ($byPerson->count() !== count($crew)) {
            throw ValidationException::withMessages(['crew' => __('The same person is on the crew twice.')]);
        }
        if ($trip->truck_source === 'own' && ! $byPerson->contains('role', 'driver')) {
            throw ValidationException::withMessages(['crew' => __('Choose the driver.')]);
        }
        if ($byPerson->where('role', 'driver')->count() > 1) {
            throw ValidationException::withMessages(['crew' => __('One driver; make the other one the co-driver.')]);
        }

        TripCrew::where('trip_id', $trip->id)->whereNotIn('person_id', $byPerson->keys())->delete();
        foreach ($byPerson as $personId => $row) {
            TripCrew::updateOrCreate(['trip_id' => $trip->id, 'person_id' => $personId], ['role' => $row['role']]);
        }
    }

    /** Add a stop between the first and the last (part loads). */
    public function addStop(Trip $trip, int $placeId, string $kind, ?int $afterSeq = null): TripStop
    {
        $this->assertPlanning($trip);

        return DB::transaction(function () use ($trip, $placeId, $kind, $afterSeq) {
            $stops   = $trip->stops()->lockForUpdate()->get();
            $lastSeq = (int) $stops->max('seq');
            $after   = $afterSeq === null ? $lastSeq - 1 : min(max(1, $afterSeq), $lastSeq - 1);

            TripStop::where('trip_id', $trip->id)->where('seq', '>', $after)->orderByDesc('seq')->get()
                ->each(fn (TripStop $s) => $s->forceFill(['seq' => $s->seq + 1])->save());

            return TripStop::create(['trip_id' => $trip->id, 'seq' => $after + 1, 'place_id' => $placeId, 'kind' => $kind]);
        });
    }

    public function removeStop(Trip $trip, TripStop $stop): void
    {
        $this->assertPlanning($trip);
        $stops = $trip->stops()->get();
        if ($stop->seq === 1 || $stop->seq === (int) $stops->max('seq')) {
            throw ValidationException::withMessages(['stop' => __('The first and last stops are the route itself. Change the route instead.')]);
        }
        if (Consignment::where('trip_id', $trip->id)->where(fn ($q) => $q->where('pickup_stop_id', $stop->id)->orWhere('drop_stop_id', $stop->id))->where('status', '!=', 'cancelled')->exists()) {
            throw ValidationException::withMessages(['stop' => __('A consignment loads or unloads here. Move it to another stop first.')]);
        }

        DB::transaction(function () use ($trip, $stop) {
            $seq = $stop->seq;
            $stop->delete();
            TripStop::where('trip_id', $trip->id)->where('seq', '>', $seq)->orderBy('seq')->get()
                ->each(fn (TripStop $s) => $s->forceFill(['seq' => $s->seq - 1])->save());
        });
    }

    /**
     * What stops this trip being sent: the truck and crew are busy on another
     * trip, or have an expired or missing paper that stops dispatch.
     *
     * @return array{busy: string[], papers: array<int, array{who: string, name: string, reason: string}>}
     */
    public function dispatchCheck(Trip $trip): array
    {
        $trip->loadMissing(['truck', 'crew.person']);
        $busy   = [];
        $papers = [];

        if ($trip->truck) {
            $other = Trip::open()->where('truck_id', $trip->truck_id)->where('id', '!=', $trip->id)->first();
            if ($other) {
                $busy[] = __(':truck is on trip :trip.', ['truck' => $trip->truck->reg_no, 'trip' => $other->label]);
            }
            if (in_array($trip->truck->status, ['workshop', 'breakdown', 'out_of_service', 'sold'], true)) {
                $busy[] = __(':truck is :status.', ['truck' => $trip->truck->reg_no, 'status' => mb_strtolower(__(Truck::STATUSES[$trip->truck->status]))]);
            }
            foreach ($this->compliance->blockers($trip->truck) as $b) {
                $papers[] = ['who' => $trip->truck->reg_no] + $b;
            }
        }

        foreach ($trip->crew as $member) {
            $person = $member->person;
            $other  = Trip::open()->where('id', '!=', $trip->id)->whereHas('crew', fn ($q) => $q->where('person_id', $person->id))->first();
            if ($other) {
                $busy[] = __(':name is on trip :trip.', ['name' => $person->name, 'trip' => $other->label]);
            }
            if ($person->status !== 'active') {
                $busy[] = __(':name is :status.', ['name' => $person->name, 'status' => mb_strtolower(__(Person::STATUSES[$person->status]))]);
            }
            // Helpers do not drive: only the people at the wheel need a licence.
            foreach ($this->compliance->blockers($person) as $b) {
                if ($b['type'] !== 'licence' || $member->role !== 'helper') {
                    $papers[] = ['who' => $person->name] + $b;
                }
            }
        }

        return ['busy' => $busy, 'papers' => $papers];
    }

    /** Send to the crew: the dispatch gate, the trip number and the LR numbers. */
    public function assign(Trip $trip, ?string $overrideReason = null): void
    {
        if ($trip->status !== 'draft') {
            throw ValidationException::withMessages(['trip' => __('Only a draft trip can be sent.')]);
        }
        $trip->loadMissing(['consignments.billingParty', 'consignments.consignor', 'from', 'to']);
        if ($trip->trip_kind !== 'empty' && $trip->consignments->where('status', '!=', 'cancelled')->isEmpty()) {
            throw ValidationException::withMessages(['trip' => __('Add the load (a consignment) first, or mark the trip as an empty run.')]);
        }
        if ($trip->truck_source === 'own' && ! $trip->truck_id) {
            throw ValidationException::withMessages(['trip' => __('Choose the truck.')]);
        }

        $check = $this->dispatchCheck($trip);
        if ($check['busy']) {
            throw ValidationException::withMessages(['trip' => implode(' ', $check['busy'])]);
        }
        if ($check['papers'] && mb_strlen(trim((string) $overrideReason)) < 10) {
            throw ValidationException::withMessages(['override_reason' => __('Papers stop this trip. Renew them, or write why it must go anyway (at least 10 letters).')]);
        }

        DB::transaction(function () use ($trip, $check, $overrideReason) {
            $first = $trip->consignments->firstWhere('status', '!=', 'cancelled');
            $shipper = $first?->consignor?->short_code ?: $first?->billingParty?->short_code ?: ($trip->trip_kind === 'empty' ? 'MTY' : 'XXX');

            $trip->forceFill([
                'trip_number' => $this->numbering->assign('trip', $trip->trip_date->copy()->setTime(12, 0), [
                    'SHP' => $shipper, 'FRM' => $trip->from->short_code, 'TO' => $trip->to->short_code,
                ]),
                'status'      => 'assigned',
                'assigned_at' => now(),
                'dispatch_override_reason' => $check['papers'] ? trim((string) $overrideReason) : null,
                'dispatch_override_by'     => $check['papers'] ? auth()->id() : null,
            ])->save();

            foreach ($trip->consignments->where('status', '!=', 'cancelled')->whereNull('lr_number') as $c) {
                $this->numberLr($c);
            }
        });

        if ($check['papers']) {
            $this->audit->record('trip.dispatch_override', "Sent {$trip->trip_number} despite: " . collect($check['papers'])->map(fn ($p) => "{$p['who']} {$p['name']} {$p['reason']}")->join('; '),
                'trip', (string) $trip->id, $trip->trip_number, reason: $overrideReason, severity: 'sensitive');
        }
        $this->audit->record('trip.assigned', "Sent {$trip->trip_number} to the crew", 'trip', (string) $trip->id, $trip->trip_number);
    }

    /** Give an LR its number (on sending the trip, or when a load is added to a sent trip). */
    public function numberLr(Consignment $consignment): void
    {
        $consignment->forceFill([
            'lr_number' => $this->numbering->assign('lr', $consignment->lr_date?->copy()->setTime(12, 0) ?? now()),
            'lr_date'   => $consignment->lr_date ?? now()->toDateString(),
        ])->save();
    }

    /**
     * Start the trip: the office does it, or the crew app (at the moment the
     * driver tapped "Start", which may be hours before the phone had signal).
     */
    public function start(Trip $trip, ?int $odometer = null, ?\Carbon\CarbonInterface $at = null): void
    {
        if (! in_array($trip->status, ['assigned', 'accepted'], true)) {
            throw ValidationException::withMessages(['trip' => __('Send the trip to the crew first.')]);
        }
        $trip->loadMissing('truck');
        if ($trip->truck && $odometer !== null && $trip->truck->odometer_km !== null && $odometer < $trip->truck->odometer_km) {
            throw ValidationException::withMessages(['start_odometer' => __('The truck already showed :km km. Check the reading.', ['km' => number_format($trip->truck->odometer_km)])]);
        }

        DB::transaction(function () use ($trip, $odometer, $at) {
            $trip->forceFill(['status' => 'running', 'started_at' => $at ?? now(), 'start_odometer' => $odometer])->save();
            if ($trip->truck) {
                $trip->truck->forceFill(['status' => 'on_trip'])->save();
                if ($odometer) {
                    $this->fleet->moveOdometer($trip->truck, $odometer);
                }
            }
            Consignment::where('trip_id', $trip->id)->where('status', 'booked')->update(['status' => 'in_transit', 'updated_at' => now()]);
        });
    }

    /** End the trip (office or crew app). Loads still on board are marked delivered then. */
    public function end(Trip $trip, ?int $odometer = null, bool $early = false, ?\Carbon\CarbonInterface $at = null): void
    {
        if ($trip->status !== 'running') {
            throw ValidationException::withMessages(['trip' => __('Only a running trip can end.')]);
        }
        if ($odometer !== null && $trip->start_odometer !== null && $odometer < $trip->start_odometer) {
            throw ValidationException::withMessages(['end_odometer' => __('The end reading is below the start reading (:km).', ['km' => number_format($trip->start_odometer)])]);
        }

        DB::transaction(function () use ($trip, $odometer, $early, $at) {
            $at ??= now();
            $trip->forceFill(['status' => 'ended', 'ended_at' => $at, 'end_odometer' => $odometer, 'ended_early' => $early])->save();
            $trip->loadMissing('truck');
            if ($trip->truck) {
                $trip->truck->forceFill(['status' => 'idle'])->save();
                if ($odometer) {
                    $this->fleet->moveOdometer($trip->truck, $odometer);
                }
            }
            Consignment::where('trip_id', $trip->id)->whereIn('status', ['booked', 'picked_up', 'in_transit'])
                ->update(['status' => 'delivered', 'delivered_at' => $at, 'updated_at' => now()]);
        });
    }

    public function cancel(Trip $trip, string $reason): void
    {
        if (! $trip->isCancellable()) {
            throw ValidationException::withMessages(['trip' => __('A trip that has started cannot be cancelled. End it early instead.')]);
        }

        DB::transaction(function () use ($trip, $reason) {
            $trip->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason])->save();
            // LRs keep their numbers (never reused) and are cancelled with the trip.
            Consignment::where('trip_id', $trip->id)->where('status', '!=', 'cancelled')->update(['status' => 'cancelled', 'updated_at' => now()]);
        });
        $this->audit->record('trip.cancelled', "Cancelled {$trip->label}", 'trip', (string) $trip->id, $trip->label, reason: $reason, severity: 'notable');
    }

    private function assertPlanning(Trip $trip): void
    {
        if (! $trip->isPlanning()) {
            throw ValidationException::withMessages(['trip' => __('The trip has started; its plan can no longer change.')]);
        }
    }
}
