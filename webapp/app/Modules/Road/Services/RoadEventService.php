<?php

namespace App\Modules\Road\Services;

use App\Modules\Fleet\Services\FleetService;
use App\Modules\People\Models\Person;
use App\Modules\Road\Models\EventApproval;
use App\Modules\Road\Models\RoadEvent;
use App\Modules\Road\Models\RoadEventEdit;
use App\Modules\Settings\Services\CompanySettings;
use App\Modules\Trips\Models\Trip;
use App\Platform\Audit\AuditRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Saving what the crew send from the road (blueprint 01 §4.3, 03 §5–6).
 *
 *  · Each entry carries a UUID made by the phone: the same entry sent twice is
 *    saved once, and the second answer repeats the first.
 *  · Three clocks: the phone's time, its uptime counter (which the user
 *    cannot change) and GPS time. The server rebuilds a trusted time from the
 *    uptime when it can, and flags a phone clock more than 5 minutes off.
 *  · Where it starts in the approval flow: recorded (no approval), waiting for
 *    the manager's check, or — for a payment without receipt with dual
 *    approval on — waiting for the second driver (D5–D7, A4).
 *  · A repair on the road also opens a truck complaint.
 */
class RoadEventService
{
    /** Types the phone may push as plain records; state changes come as commands. */
    public const PUSHABLE = ['fuel', 'toll', 'weighbridge', 'fine_receipt', 'no_receipt', 'loading', 'parking', 'repair', 'food',
        'other_expense', 'money_received', 'rest_start', 'rest_end', 'incident', 'note'];

    public function __construct(
        private CompanySettings $settings,
        private EventChecks $checks,
        private FleetService $fleet,
        private AuditRecorder $audit,
        private \App\Platform\Notifications\Notices $notices,
    ) {}

    /**
     * Save one pushed entry.
     *
     * @param array<string, mixed> $input the event as the phone sent it
     * @param array{sent_uptime_ms?: int} $batch the batch's clock
     * @return array{uuid: string, result: string, id?: int, status?: string, flags?: array, errors?: array}
     */
    public function push(Person $person, array $input, array $batch = [], array $allowed = self::PUSHABLE): array
    {
        $uuid = (string) ($input['uuid'] ?? '');

        $existing = $uuid !== '' ? RoadEvent::withoutGlobalScopes()->where('uuid', $uuid)->first() : null;
        if ($existing) {
            return $existing->company_id === $person->company_id && $existing->person_id === $person->id
                ? ['uuid' => $uuid, 'result' => 'duplicate', 'id' => $existing->id, 'status' => $existing->status]
                : ['uuid' => $uuid, 'result' => 'rejected', 'errors' => ['uuid' => [__('This id is already used.')]]];
        }

        $validator = Validator::make($input, $this->rules($input['type'] ?? '', $allowed));
        if ($validator->fails()) {
            return ['uuid' => $uuid, 'result' => 'rejected', 'errors' => $validator->errors()->toArray()];
        }
        $data = $validator->validated();

        $trip = Trip::with(['crew', 'truck'])->where('uuid', $data['trip'])->first();
        if (! $trip || ! $trip->crew->contains('person_id', $person->id)) {
            return ['uuid' => $uuid, 'result' => 'rejected', 'errors' => ['trip' => [__('You are not on this trip.')]]];
        }
        if (in_array($trip->status, ['draft', 'settled', 'closed', 'cancelled'], true)) {
            return ['uuid' => $uuid, 'result' => 'rejected', 'errors' => ['trip' => [__('This trip is :status; entries can no longer be added.', ['status' => mb_strtolower(__(Trip::STATUSES[$trip->status]))])]]];
        }

        $event = DB::transaction(function () use ($person, $trip, $data, $batch) {
            $event = $this->build($person, $trip, $data, $batch);
            $event->save();
            $event->forceFill(['flags' => $this->checks->run($event, $trip) ?: null])->save();

            if ($event->type === 'repair' && $trip->truck) {
                $complaint = $this->fleet->raiseComplaint($trip->truck, [
                    'title' => mb_substr(__('Repair on the road: :what', ['what' => $data['data']['what'] ?? '']), 0, 150),
                    'description' => $event->note, 'priority' => 'normal', 'category' => null, 'odometer_km' => $event->odometer,
                    'trip_id' => $trip->id,
                ]);
                $event->forceFill(['complaint_id' => $complaint->id])->save();
            }

            return $event;
        });

        if ($event->type === 'incident') {
            $this->audit->record('road.incident', "Incident on {$trip->label}: " . ($event->data['kind'] ?? '') . ' — ' . ($event->data['description'] ?? ''), 'trip', (string) $trip->id, $trip->label, severity: 'notable');
        }
        $this->notices->entryRecorded($event);

        return ['uuid' => $event->uuid, 'result' => 'saved', 'id' => $event->id, 'status' => $event->status, 'flags' => $event->flags ?? []];
    }

    /** Build (not save) an event from validated input: times, money, status. */
    public function build(Person $person, Trip $trip, array $data, array $batch = []): RoadEvent
    {
        $type    = $data['type'];
        $eventAt = CarbonImmutable::parse($data['event_at'])->utc();
        $mode    = $data['payment_mode'] ?? null;

        return new RoadEvent([
            'uuid'            => $data['uuid'],
            'trip_id'         => $trip->id,
            'person_id'       => $person->id,
            'type'            => $type,
            'category'        => EventTypes::category($type),
            'amount'          => isset($data['amount']) ? round((float) $data['amount'], 2) : null,
            'payment_mode'    => $mode,
            'from_own_pocket' => $mode ? (EventTypes::PAYMENT_MODES[$mode][1] ?? false) : false,
            'odometer'        => $data['odometer'] ?? null,
            'data'            => EventTypes::onlyKnownData($type, $data['data'] ?? null),
            'note'            => $data['note'] ?? null,
            'event_at'        => $eventAt,
            'recorded_at'     => isset($data['recorded_at']) ? CarbonImmutable::parse($data['recorded_at'])->utc() : null,
            'trusted_at'      => $this->trustedTime($eventAt, $data, $batch),
            'uptime_ms'       => $data['uptime_ms'] ?? null,
            'gps_time'        => isset($data['gps_time']) ? CarbonImmutable::parse($data['gps_time'])->utc() : null,
            'lat'             => $data['lat'] ?? null,
            'lng'             => $data['lng'] ?? null,
            'accuracy_m'      => $data['accuracy_m'] ?? null,
            'mock_location'   => (bool) ($data['mock'] ?? false),
            'device_id'       => $data['device_id'] ?? null,
            'app_version'     => $data['app_version'] ?? null,
            'status'          => $this->startingStatus($type, $person, $trip, isset($data['amount']) ? (float) $data['amount'] : null),
            'photos_expected' => count(array_unique(array_intersect($data['photos'] ?? [], \App\Modules\Road\Models\EventPhoto::SLOTS))),
        ]);
    }

    /**
     * The crew member edits their own entry before it is checked. The old
     * values are kept; a payment without receipt goes back to the second
     * driver, since what they approved has changed.
     */
    public function edit(Person $person, RoadEvent $event, array $input): RoadEvent
    {
        $this->assertOpenTrip($event);
        if (! $event->isEditableBy($person->id)) {
            throw ValidationException::withMessages(['event' => __('This entry can no longer be changed.')]);
        }
        $data = Validator::make($input + ['type' => $event->type], [
            'amount'       => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'payment_mode' => ['nullable', Rule::in(array_keys(EventTypes::PAYMENT_MODES))],
            'odometer'     => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'note'         => ['nullable', 'string', 'max:500'],
        ] + EventTypes::dataRules($event->type))->validate();
        $before = $event->status;

        $event = DB::transaction(function () use ($person, $event, $data) {
            RoadEventEdit::create(['road_event_id' => $event->id, 'person_id' => $person->id,
                'before' => $event->only(['amount', 'payment_mode', 'odometer', 'note', 'data'])]);
            // Changing a queried entry is the crew's answer to the office's question.
            if ($event->status === 'queried') {
                EventApproval::create(['road_event_id' => $event->id, 'step' => 'crew', 'decision' => 'answer', 'person_id' => $person->id, 'note' => $data['note'] ?? null]);
            }

            $mode = $data['payment_mode'] ?? $event->payment_mode;
            $event->fill([
                'amount'          => array_key_exists('amount', $data) ? $data['amount'] : $event->amount,
                'payment_mode'    => $mode,
                'from_own_pocket' => $mode ? (EventTypes::PAYMENT_MODES[$mode][1] ?? false) : false,
                'odometer'        => array_key_exists('odometer', $data) ? $data['odometer'] : $event->odometer,
                'note'            => array_key_exists('note', $data) ? $data['note'] : $event->note,
                'data'            => EventTypes::onlyKnownData($event->type, array_merge($event->data ?? [], $data['data'] ?? [])),
            ]);
            if (EventTypes::approval($event->type) === 'dual' && $event->status !== 'waiting_check') {
                $event->forceFill(['status' => $this->startingStatus($event->type, $person, $event->trip, (float) $event->amount), 'second_by' => null, 'second_at' => null, 'second_reject_reason' => null]);
            } elseif ($event->status === 'queried') {
                $event->status = 'waiting_check';
            }
            $event->save();
            $event->forceFill(['flags' => $this->checks->run($event, $event->trip) ?: null])->save();

            return $event;
        });
        // An answered question goes back to the office; a changed entry back to the other driver.
        if ($event->status !== $before) {
            $this->notices->entryRecorded($event, false);
        }

        return $event;
    }

    public function cancel(Person $person, RoadEvent $event, string $reason): void
    {
        $this->assertOpenTrip($event);
        if (! $event->isEditableBy($person->id)) {
            throw ValidationException::withMessages(['event' => __('This entry can no longer be cancelled.')]);
        }
        $event->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason])->save();
    }

    /** The second driver approves or rejects a payment without receipt (D5–D6). */
    public function secondDecision(Person $approver, RoadEvent $event, bool $approve, ?string $reason = null): void
    {
        $event->loadMissing('trip.crew');
        $role = $event->trip->crew->firstWhere('person_id', $approver->id)?->role;

        if ($event->status !== 'waiting_2nd') {
            throw ValidationException::withMessages(['event' => __('This entry is not waiting for a second approval.')]);
        }
        if (in_array($event->trip->status, ['settled', 'closed'], true)) {
            throw ValidationException::withMessages(['event' => __('The trip is settled; the office decides on this entry now.')]);
        }
        if ($approver->id === $event->person_id) {
            throw ValidationException::withMessages(['event' => __('The second approval must come from the other driver.')]);
        }
        if (! in_array($role, ['driver', 'co_driver'], true)) {
            throw ValidationException::withMessages(['event' => __('Only a driver on this trip can give the second approval.')]);
        }
        if (! $approve && mb_strlen(trim((string) $reason)) < 3) {
            throw ValidationException::withMessages(['reason' => __('Say why you do not agree.')]);
        }

        DB::transaction(function () use ($event, $approver, $approve, $reason) {
            $event->forceFill($approve
                ? ['status' => 'waiting_check', 'second_by' => $approver->id, 'second_at' => now()]
                : ['status' => 'disputed', 'second_by' => $approver->id, 'second_at' => now(), 'second_reject_reason' => $reason])->save();
            EventApproval::create(['road_event_id' => $event->id, 'step' => 'second_driver', 'decision' => $approve ? 'approve' : 'reject', 'person_id' => $approver->id, 'note' => $approve ? null : $reason]);
        });
        $this->notices->approvalsWaiting($event);
    }

    /** Once a trip is settled, the crew's entries are locked (blueprint 03 §3, D8). */
    private function assertOpenTrip(RoadEvent $event): void
    {
        $event->loadMissing('trip');
        if (in_array($event->trip->status, ['settled', 'closed'], true)) {
            throw ValidationException::withMessages(['event' => __('The trip is settled; its entries are locked.')]);
        }
    }

    private function startingStatus(string $type, Person $person, Trip $trip, ?float $amount): string
    {
        $approval = EventTypes::approval($type);

        if ($approval === 'info') {
            return 'info';
        }
        if ($approval === 'dual' && $this->settings->get('dual_approval_enabled')) {
            $trip->loadMissing('crew');
            $otherDriver = $trip->crew->first(fn ($c) => in_array($c->role, ['driver', 'co_driver'], true) && $c->person_id !== $person->id);
            if ($otherDriver || $this->settings->get('single_driver_approver') === 'manager') {
                return 'waiting_2nd';
            }
        }
        $autoToll = (float) $this->settings->get('auto_approve_toll_below');
        if ($type === 'toll' && $autoToll > 0 && $amount !== null && $amount < $autoToll) {
            return 'approved';
        }

        return 'waiting_check';
    }

    /**
     * The phone's uptime cannot be changed by the user: event time = now −
     * (uptime when the batch was sent − uptime at the event). Without it (the
     * phone restarted in between) GPS time, then the phone clock.
     */
    public function trustedTime(CarbonImmutable $eventAt, array $data, array $batch): CarbonImmutable
    {
        $sent = $batch['sent_uptime_ms'] ?? null;
        $at   = $data['uptime_ms'] ?? null;
        if ($sent !== null && $at !== null && $at <= $sent) {
            return CarbonImmutable::now()->utc()->subMilliseconds((int) ($sent - $at));
        }
        if (! empty($data['gps_time'])) {
            return CarbonImmutable::parse($data['gps_time'])->utc();
        }

        return $eventAt;
    }

    private function rules(string $type, array $allowed): array
    {
        $needsAmount = EventTypes::TYPES[$type][4] ?? false;

        return [
            'uuid'         => ['required', 'uuid'],
            'trip'         => ['required', 'uuid'],
            'type'         => ['required', Rule::in($allowed)],
            'amount'       => [$needsAmount ? 'required' : 'nullable', 'numeric', 'min:0', 'max:9999999'],
            'payment_mode' => [$needsAmount ? 'required' : 'nullable', Rule::in(array_keys(EventTypes::PAYMENT_MODES))],
            'odometer'     => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'note'         => ['nullable', 'string', 'max:500'],
            'event_at'     => ['required', 'date'],
            'recorded_at'  => ['nullable', 'date'],
            'uptime_ms'    => ['nullable', 'integer', 'min:0'],
            'gps_time'     => ['nullable', 'date'],
            'lat'          => ['nullable', 'numeric', 'between:-90,90'],
            'lng'          => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy_m'   => ['nullable', 'integer', 'min:0', 'max:65000'],
            'mock'         => ['nullable', 'boolean'],
            'device_id'    => ['nullable', 'string', 'max:100'],
            'app_version'  => ['nullable', 'string', 'max:20'],
            'photos'       => ['nullable', 'array', 'max:6'],
            'photos.*'     => ['string', 'max:20'],
            'data'         => ['nullable', 'array'],
        ] + EventTypes::dataRules($type);
    }
}
