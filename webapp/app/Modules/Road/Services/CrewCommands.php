<?php

namespace App\Modules\Road\Services;

use App\Modules\Fleet\Models\Complaint;
use App\Modules\Fleet\Services\FleetService;
use App\Modules\People\Models\Person;
use App\Modules\Road\Models\ConsignmentDelivery;
use App\Modules\Road\Models\DrivingShift;
use App\Modules\Road\Models\DutySession;
use App\Modules\Road\Models\RoadEvent;
use App\Modules\Trips\Models\Consignment;
use App\Modules\Trips\Models\Trip;
use App\Modules\Trips\Models\TripStop;
use App\Modules\Trips\Services\TripService;
use App\Modules\Settings\Services\CompanySettings;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Devices\ApiCommand;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * What the crew do with a tap (blueprint 01 §4.4, 03 §3–4, 7): accept or
 * reject a trip, start and end it, reach and leave a stop, loaded, delivered
 * (e-POD), the second driver's yes or no, "I am driving now", and duty on/off.
 *
 * Every command carries a UUID made by the phone. The first time it runs,
 * its answer is kept (api_commands); the same UUID again gets the same
 * answer and changes nothing — the phone retries freely on a bad network.
 * A refused command is kept too, so a retry cannot make it pass later by
 * accident; the crew member sees why and does it again.
 *
 * The moments of the road (start, stop reached, delivered…) are also saved
 * as road events, with their own time, GPS and photos, under the command's
 * UUID — the trip's timeline and the driving hours come from them.
 */
class CrewCommands
{
    /** command => [road event type it records (or null), permission] */
    public const COMMANDS = [
        'trip.accept'          => [null, 'trip.view_own'],
        'trip.reject'          => [null, 'trip.view_own'],
        'trip.start'           => ['start_trip', 'event.record'],
        'trip.end'             => ['end_trip', 'event.record'],
        'stop.reached'         => ['reached_stop', 'event.record'],
        'stop.left'            => ['left_stop', 'event.record'],
        'load.loaded'          => ['loaded', 'event.record'],
        'load.delivered'       => ['delivered', 'event.record'],
        'driver.change'        => ['driver_change', 'event.record'],
        'event.second_approve' => [null, 'event.second_approve'],
        'event.second_reject'  => [null, 'event.second_approve'],
        'duty.start'           => [null, 'trip.view_own'],
        'duty.stop'            => [null, 'trip.view_own'],
        'complaint.raise'      => [null, 'complaint.raise'],
        'leave.apply'          => [null, 'leave.apply'],
        'leave.cancel'         => [null, 'leave.apply'],
    ];

    public function __construct(
        private CompanyContext $context,
        private CrewAccess $access,
        private RoadEventService $events,
        private TripService $trips,
        private FleetService $fleet,
        private CompanySettings $settings,
        private AuditRecorder $audit,
    ) {}

    /**
     * Run one command (or replay its kept answer).
     *
     * @param array<string, mixed> $command {uuid, name, trip?, event?, …}
     * @param array{sent_uptime_ms?: int} $batch
     * @return array{uuid: string, name: string, status: int, replayed?: bool}
     */
    public function run(Person $person, array $command, array $batch = []): array
    {
        $uuid = (string) ($command['uuid'] ?? '');
        $name = (string) ($command['name'] ?? '');
        $head = ['uuid' => $uuid, 'name' => $name];

        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
            return $head + ['status' => 422, 'message' => __('The command has no valid id.')];
        }
        if (! isset(self::COMMANDS[$name])) {
            return $head + ['status' => 422, 'message' => __('Unknown command.')];
        }

        if ($kept = $this->kept($uuid)) {
            return $kept;
        }

        try {
            return DB::transaction(function () use ($person, $command, $batch, $uuid, $name, $head) {
                // The row is written first: a second copy of this command
                // arriving at the same moment waits on its unique key, then
                // finds the first one's answer.
                $row = ApiCommand::create([
                    'uuid' => $uuid, 'company_id' => $this->context->id(), 'account_id' => (int) auth()->id(),
                    'name' => $name, 'http_status' => 0, 'result' => [],
                ]);

                try {
                    $this->allow($name);
                    // Its own savepoint: a refusal halfway (the odometer check
                    // after the start event was written) undoes all of it.
                    $result = ['status' => 200] + DB::transaction(fn () => $this->{$this->method($name)}($person, $command, $batch));
                } catch (ValidationException $e) {
                    $result = ['status' => 422, 'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(), 'errors' => $e->errors()];
                }

                $row->forceFill(['http_status' => $result['status'], 'result' => $head + $result])->save();

                return $head + $result;
            });
        } catch (UniqueConstraintViolationException) {
            return $this->kept($uuid) ?? $head + ['status' => 409, 'message' => __('This command is already being handled. Send it again in a moment.')];
        }
    }

    /** The kept answer to a command already run by this login. */
    private function kept(string $uuid): ?array
    {
        $row = ApiCommand::where('uuid', $uuid)->first();
        if (! $row) {
            return null;
        }
        if ($row->company_id !== $this->context->id() || $row->account_id !== (int) auth()->id()) {
            return ['uuid' => $uuid, 'name' => $row->name, 'status' => 409, 'message' => __('This id is already used.')];
        }

        return $row->result + ['replayed' => true];
    }

    private function allow(string $name): void
    {
        $permission = self::COMMANDS[$name][1];
        $ok = $permission === 'event.record' ? $this->access->canRecord() : $this->access->can($permission);
        if (! $ok) {
            throw ValidationException::withMessages(['command' => __('You are not allowed to do this.')]);
        }
    }

    private function method(string $name): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace(['.', '_'], ' ', $name))));
    }

    // ---- Trip ------------------------------------------------------------------

    private function tripAccept(Person $person, array $c): array
    {
        $trip = $this->access->trip($person, $c['trip'] ?? null);
        if (! in_array($trip->status, ['assigned', 'accepted'], true)) {
            throw ValidationException::withMessages(['trip' => __('This trip is no longer waiting for an answer.')]);
        }

        $member = $trip->crew->firstWhere('person_id', $person->id);
        $member->forceFill(['accepted_at' => now(), 'rejected_at' => null, 'reject_reason' => null])->save();

        // The trip counts as accepted when its driver says yes (a helper's yes alone does not).
        if ($trip->status === 'assigned' && ($member->role === 'driver' || ! $trip->crew->contains('role', 'driver'))) {
            $trip->forceFill(['status' => 'accepted'])->save();
        }
        $this->audit->record('trip.crew_accepted', "{$person->name} accepted {$trip->label}", 'trip', (string) $trip->id, $trip->label);

        return ['trip' => $trip->uuid, 'trip_status' => $trip->status];
    }

    private function tripReject(Person $person, array $c): array
    {
        $trip   = $this->access->trip($person, $c['trip'] ?? null);
        $reason = trim((string) ($c['reason'] ?? ''));
        if (! in_array($trip->status, ['assigned', 'accepted'], true)) {
            throw ValidationException::withMessages(['trip' => __('This trip has started; call the office instead.')]);
        }
        if (mb_strlen($reason) < 3) {
            throw ValidationException::withMessages(['reason' => __('Say why you cannot take this trip.')]);
        }

        $trip->crew->firstWhere('person_id', $person->id)
            ->forceFill(['rejected_at' => now(), 'reject_reason' => mb_substr($reason, 0, 255), 'accepted_at' => null])->save();
        // The office re-plans: the trip goes back to "sent", waiting for a crew that can take it.
        if ($trip->status === 'accepted') {
            $trip->forceFill(['status' => 'assigned'])->save();
        }
        $this->audit->record('trip.crew_rejected', "{$person->name} cannot take {$trip->label}", 'trip', (string) $trip->id, $trip->label, reason: $reason, severity: 'notable');

        return ['trip' => $trip->uuid, 'trip_status' => $trip->status];
    }

    private function tripStart(Person $person, array $c, array $batch): array
    {
        $trip = $this->access->trip($person, $c['trip'] ?? null);
        $this->access->assertAtTheWheel($trip, $person);
        if (! in_array($trip->status, ['assigned', 'accepted'], true)) {
            throw ValidationException::withMessages(['trip' => $trip->status === 'running' ? __('The trip is already running.') : __('This trip cannot start now.')]);
        }

        $event = $this->moment($person, $trip, 'start_trip', $c, $batch);
        // Starting is a yes, even without pressing "Accept" first.
        $member = $trip->crew->firstWhere('person_id', $person->id);
        if (! $member->accepted_at) {
            $member->forceFill(['accepted_at' => now(), 'rejected_at' => null, 'reject_reason' => null])->save();
        }
        $this->trips->start($trip, $event->odometer, $event->trusted_at);

        // Whoever starts holds the wheel first (D11).
        DrivingShift::create([
            'trip_id' => $trip->id, 'person_id' => $person->id, 'started_at' => $event->trusted_at,
            'start_odometer' => $event->odometer, 'start_lat' => $event->lat, 'start_lng' => $event->lng,
        ]);

        return ['trip' => $trip->uuid, 'trip_status' => 'running', 'event' => $this->eventAnswer($event)];
    }

    private function tripEnd(Person $person, array $c, array $batch): array
    {
        $trip = $this->access->trip($person, $c['trip'] ?? null);
        $this->access->assertAtTheWheel($trip, $person);
        if ($trip->status !== 'running') {
            throw ValidationException::withMessages(['trip' => __('Only a running trip can end.')]);
        }

        $event = $this->moment($person, $trip, 'end_trip', $c, $batch);
        $this->trips->end($trip, $event->odometer, (bool) ($c['early'] ?? false), $event->trusted_at);
        DrivingShift::where('trip_id', $trip->id)->whereNull('ended_at')->get()
            ->each(fn (DrivingShift $s) => $s->forceFill(['ended_at' => max($s->started_at, $event->trusted_at), 'end_odometer' => $event->odometer])->save());

        return ['trip' => $trip->uuid, 'trip_status' => 'ended', 'event' => $this->eventAnswer($event)];
    }

    // ---- Stops and loads ---------------------------------------------------------

    private function stopReached(Person $person, array $c, array $batch): array
    {
        return $this->stopMoment($person, $c, $batch, 'reached_stop', 'arrived_at');
    }

    private function stopLeft(Person $person, array $c, array $batch): array
    {
        return $this->stopMoment($person, $c, $batch, 'left_stop', 'departed_at');
    }

    private function stopMoment(Person $person, array $c, array $batch, string $type, string $column): array
    {
        $trip = $this->running($person, $c);
        $stop = TripStop::where('trip_id', $trip->id)->whereKey((int) ($c['event']['data']['stop_id'] ?? 0))->first();
        if (! $stop) {
            throw ValidationException::withMessages(['stop' => __('That stop is not on this trip.')]);
        }

        $event = $this->moment($person, $trip, $type, $c, $batch);
        // The first report counts: the co-driver's phone sending it again changes nothing.
        if ($stop->{$column} === null) {
            $stop->forceFill([$column => $event->trusted_at])->save();
        }

        return ['stop' => $stop->id, $column => $stop->{$column}?->toIso8601String(), 'event' => $this->eventAnswer($event)];
    }

    private function loadLoaded(Person $person, array $c, array $batch): array
    {
        $trip = $this->onTheRoad($person, $c);
        $load = $this->load($trip, $c);
        if (! in_array($load->status, ['booked', 'picked_up', 'in_transit'], true)) {
            throw ValidationException::withMessages(['consignment' => __('This load is already :status.', ['status' => mb_strtolower(__(Consignment::STATUSES[$load->status]))])]);
        }

        $event = $this->moment($person, $trip, 'loaded', $c, $batch);
        $data  = $event->data ?? [];
        $load->forceFill(array_filter([
            'status'           => $trip->status === 'running' ? 'in_transit' : 'picked_up',
            'seal_no'          => $data['seal_no'] ?? null,
            'actual_weight_mt' => $load->actual_weight_mt === null && isset($data['weight_mt']) ? $data['weight_mt'] : null,
        ], fn ($v) => $v !== null))->save();

        return ['consignment' => $load->id, 'consignment_status' => $load->status, 'event' => $this->eventAnswer($event)];
    }

    /** e-POD: what was handed over, to whom, short or damaged (blueprint 12 §5). */
    private function loadDelivered(Person $person, array $c, array $batch): array
    {
        $trip = $this->running($person, $c);
        $load = $this->load($trip, $c);
        if (! in_array($load->status, ['booked', 'picked_up', 'in_transit'], true)) {
            throw ValidationException::withMessages(['consignment' => __('This load is already :status.', ['status' => mb_strtolower(__(Consignment::STATUSES[$load->status]))])]);
        }

        $event = $this->moment($person, $trip, 'delivered', $c, $batch);
        $d     = $event->data ?? [];
        $stopId = isset($d['stop_id']) ? TripStop::where('trip_id', $trip->id)->whereKey((int) $d['stop_id'])->value('id') : $load->drop_stop_id;

        ConsignmentDelivery::create([
            'consignment_id' => $load->id, 'road_event_id' => $event->id, 'trip_stop_id' => $stopId,
            'packages' => $d['packages'] ?? null, 'weight_mt' => $d['weight_mt'] ?? null,
            'short_packages' => (int) ($d['short_packages'] ?? 0), 'damaged_packages' => (int) ($d['damaged_packages'] ?? 0),
            'remarks' => $d['remarks'] ?? null, 'receiver_name' => $d['receiver_name'], 'receiver_mobile' => $d['receiver_mobile'] ?? null,
            'otp_verified' => (bool) ($d['otp_verified'] ?? false),
            'unloading_started_at' => isset($d['unloading_started_at']) ? CarbonImmutable::parse($d['unloading_started_at'])->utc() : null,
            'unloading_ended_at' => isset($d['unloading_ended_at']) ? CarbonImmutable::parse($d['unloading_ended_at'])->utc() : null,
            'delivered_at' => $event->trusted_at,
        ]);

        // A part delivery leaves the rest on board.
        if (empty($d['partial'])) {
            $load->forceFill(['status' => 'delivered', 'delivered_at' => $event->trusted_at])->save();
        }

        return ['consignment' => $load->id, 'consignment_status' => $load->status, 'event' => $this->eventAnswer($event)];
    }

    // ---- People at the wheel -----------------------------------------------------

    /**
     * "I am driving now" (P2, D11). The person taking the seat taps it on
     * their own phone; on a shared phone they type their PIN, so nobody books
     * driving hours in another's name.
     */
    private function driverChange(Person $person, array $c, array $batch): array
    {
        $trip     = $this->running($person, $c);
        $incoming = (int) ($c['event']['data']['incoming_person_id'] ?? 0);
        $member   = $trip->crew->firstWhere('person_id', $incoming);

        if (! $member || ! in_array($member->role, ['driver', 'co_driver'], true)) {
            throw ValidationException::withMessages(['incoming_person_id' => __('Only the driver or co-driver of this trip can take the wheel.')]);
        }
        $member->loadMissing('person.member.account');
        if ($incoming !== $person->id) {
            $pinHash = $member->person?->member?->account?->pin_hash;
            if (! $pinHash || ! Hash::check((string) ($c['pin'] ?? ''), $pinHash)) {
                throw ValidationException::withMessages(['pin' => __(':name must type their own PIN.', ['name' => $member->person->name])]);
            }
        }
        if ($this->settings->get('driver_change_photo') && ! in_array('odometer', $c['event']['photos'] ?? [], true)) {
            throw ValidationException::withMessages(['photos' => __('Take a photo of the odometer.')]);
        }

        $open = DrivingShift::where('trip_id', $trip->id)->whereNull('ended_at')->orderByDesc('started_at')->first();
        if ($open?->person_id === $incoming) {
            throw ValidationException::withMessages(['incoming_person_id' => __(':name is already driving.', ['name' => $member->person?->name ?? ''])]);
        }

        $event = $this->moment($person, $trip, 'driver_change', $c, $batch);
        $open?->forceFill(['ended_at' => max($open->started_at, $event->trusted_at), 'end_odometer' => $event->odometer])->save();
        $shift = DrivingShift::create([
            'trip_id' => $trip->id, 'person_id' => $incoming, 'started_at' => $event->trusted_at,
            'start_odometer' => $event->odometer, 'start_lat' => $event->lat, 'start_lng' => $event->lng,
        ]);

        return ['driving' => $incoming, 'shift' => $shift->id, 'event' => $this->eventAnswer($event)];
    }

    private function eventSecondApprove(Person $person, array $c): array
    {
        $event = $this->eventFor($person, $c);
        $this->events->secondDecision($person, $event, true);

        return ['event' => $this->eventAnswer($event)];
    }

    private function eventSecondReject(Person $person, array $c): array
    {
        $event = $this->eventFor($person, $c);
        $this->events->secondDecision($person, $event, false, (string) ($c['reason'] ?? ''));
        $this->audit->record('road.second_rejected', "{$person->name} disagreed with a payment without receipt on {$event->trip->label}", 'trip', (string) $event->trip_id, $event->trip->label, reason: (string) ($c['reason'] ?? ''), severity: 'notable');

        return ['event' => $this->eventAnswer($event)];
    }

    private function dutyStart(Person $person, array $c, array $batch): array
    {
        $at   = $this->commandTime($c, $batch);
        $open = DutySession::where('person_id', $person->id)->whereNull('ended_at')->first();

        $session = $open ?? DutySession::create(['person_id' => $person->id, 'started_at' => $at]);

        return ['duty' => 'on', 'since' => $session->started_at->toIso8601String()];
    }

    private function dutyStop(Person $person, array $c, array $batch): array
    {
        $at = $this->commandTime($c, $batch);
        DutySession::where('person_id', $person->id)->whereNull('ended_at')->get()
            ->each(fn (DutySession $s) => $s->forceFill(['ended_at' => max($s->started_at, $at)])->save());

        return ['duty' => 'off'];
    }

    /** A truck problem noticed on the road (not a repair already paid for: that is a "repair" entry). */
    private function complaintRaise(Person $person, array $c): array
    {
        $trip = $this->access->trip($person, $c['trip'] ?? null);
        if (! $trip->truck) {
            throw ValidationException::withMessages(['trip' => __('This trip has no truck of ours.')]);
        }
        $data = Validator::make($c, [
            'title'       => ['required', 'string', 'min:3', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'priority'    => ['nullable', 'in:' . implode(',', array_keys(Complaint::PRIORITIES))],
            'odometer'    => ['nullable', 'integer', 'min:0', 'max:9999999'],
        ])->validate();

        $complaint = $this->fleet->raiseComplaint($trip->truck, [
            'title' => $data['title'], 'description' => $data['description'] ?? null, 'priority' => $data['priority'] ?? 'normal',
            'odometer_km' => $data['odometer'] ?? null, 'trip_id' => $trip->id,
        ]);
        $complaint->forceFill(['uuid' => $c['uuid']])->save();

        return ['complaint' => $complaint->number];
    }

    // ---- Leave (M40) ---------------------------------------------------------------

    /** Ask for leave: {type, from, to, reason}; the request takes the command's UUID. */
    private function leaveApply(Person $person, array $c): array
    {
        $leave = app(\App\Modules\People\Services\Leave::class);
        $data = Validator::make(['leave_type' => $c['type'] ?? null, 'from_on' => $c['from'] ?? null, 'to_on' => $c['to'] ?? null, 'reason' => $c['reason'] ?? null],
            $leave->rules(), $leave->messages())->validate();
        $request = $leave->apply($person, $data, auth()->user(), 'app', $c['uuid']);

        return ['leave' => $request->uuid, 'status' => $request->status, 'days' => $request->days];
    }

    /** Cancel my waiting request: {leave: uuid}. */
    private function leaveCancel(Person $person, array $c): array
    {
        $request = \App\Modules\People\Models\LeaveRequest::where('uuid', (string) ($c['leave'] ?? ''))->where('person_id', $person->id)->first();
        if (! $request) {
            throw ValidationException::withMessages(['leave' => __('No such leave request of yours.')]);
        }

        return ['leave' => app(\App\Modules\People\Services\Leave::class)->cancel($request, false)->uuid, 'status' => 'cancelled'];
    }

    // ---- Helpers -----------------------------------------------------------------

    /**
     * Save the road event a command records, under the command's UUID. A
     * refused event refuses the command (and rolls it back).
     */
    private function moment(Person $person, Trip $trip, string $type, array $c, array $batch): RoadEvent
    {
        $payload = (array) ($c['event'] ?? []);
        $answer  = $this->events->push($person, ['uuid' => $c['uuid'], 'trip' => $trip->uuid, 'type' => $type] + $payload, $batch, [$type]);

        if ($answer['result'] === 'rejected') {
            throw ValidationException::withMessages($answer['errors'] ?? ['event' => __('The entry could not be saved.')]);
        }

        return RoadEvent::findOrFail($answer['id']);
    }

    private function running(Person $person, array $c): Trip
    {
        $trip = $this->access->trip($person, $c['trip'] ?? null);
        if ($trip->status !== 'running') {
            throw ValidationException::withMessages(['trip' => __('Start the trip first.')]);
        }

        return $trip;
    }

    /** Loading may happen before the trip starts (at the depot or the shipper's gate). */
    private function onTheRoad(Person $person, array $c): Trip
    {
        $trip = $this->access->trip($person, $c['trip'] ?? null);
        if (! in_array($trip->status, ['assigned', 'accepted', 'running'], true)) {
            throw ValidationException::withMessages(['trip' => __('This trip is not on the road.')]);
        }

        return $trip;
    }

    private function load(Trip $trip, array $c): Consignment
    {
        $load = Consignment::where('trip_id', $trip->id)->whereKey((int) ($c['event']['data']['consignment_id'] ?? 0))->first();
        if (! $load || $load->status === 'cancelled') {
            throw ValidationException::withMessages(['consignment' => __('That load is not on this trip.')]);
        }

        return $load;
    }

    private function eventFor(Person $person, array $c): RoadEvent
    {
        $event = RoadEvent::with('trip.crew')->where('uuid', (string) ($c['event_uuid'] ?? ''))->first();
        if (! $event || ! $event->trip->crew->contains('person_id', $person->id)) {
            throw ValidationException::withMessages(['event' => __('That entry is not on your trip.')]);
        }

        return $event;
    }

    private function commandTime(array $c, array $batch): CarbonImmutable
    {
        $at = isset($c['at']) ? CarbonImmutable::parse((string) $c['at'])->utc() : CarbonImmutable::now()->utc();

        return $this->events->trustedTime($at, $c, $batch);
    }

    private function eventAnswer(RoadEvent $event): array
    {
        return ['uuid' => $event->uuid, 'id' => $event->id, 'status' => $event->status];
    }
}
