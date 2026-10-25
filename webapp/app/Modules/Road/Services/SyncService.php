<?php

namespace App\Modules\Road\Services;

use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Services\Compliance;
use App\Modules\People\Models\Person;
use App\Modules\Road\Models\DrivingShift;
use App\Modules\Road\Models\DutySession;
use App\Modules\Road\Models\RoadEvent;
use App\Modules\Settings\Services\CompanySettings;
use App\Modules\Trips\Models\Consignment;
use App\Modules\Trips\Models\Trip;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * What the crew app needs to work offline (blueprint 01 §4.2): the person's
 * trips with their stops and loads, the entries on those trips (changed
 * since the phone last asked), the second approvals waiting for them, the
 * lists the forms use, and the truck's papers to show at a checkpost.
 *
 * Office-only facts never leave the server: the office note, the freight
 * (unless the owner lets the crew see it), other people's ID numbers, the
 * manager's flags.
 */
class SyncService
{
    /** Settings the phone works with. */
    private const APP_SETTINGS = [
        'dual_approval_enabled', 'single_driver_approver', 'helpers_record_events', 'driver_change_photo', 'fuel_photos_required',
        'app_lock_minutes', 'stop_prompt_minutes', 'freight_visible_to_crew', 'max_continuous_driving_minutes', 'max_daily_driving_minutes',
    ];

    /** Ended trips stay on the phone this long (late photos, second approvals, the settlement). */
    private const KEEP_ENDED_DAYS = 15;

    public function __construct(
        private CompanyContext $context,
        private CompanySettings $settings,
        private Compliance $compliance,
        private CrewAccess $access,
    ) {}

    public function pull(Person $person, ?CarbonImmutable $since = null): array
    {
        $company = $this->context->company();
        $now     = CarbonImmutable::now();
        $trips   = $this->trips($person);
        $tripIds = $trips->pluck('id');
        $freight = (bool) $this->settings->get('freight_visible_to_crew') || $this->access->can('trip.freight_view');

        $events = RoadEvent::with('photos:id,road_event_id,slot')->whereIn('trip_id', $tripIds)
            ->when($since, fn ($q) => $q->where('updated_at', '>', $since))
            ->orderBy('trusted_at')->limit(2000)->get();

        $waiting = RoadEvent::with('person:id,name')->whereIn('trip_id', $trips->filter(fn ($t) => in_array($t->crew->firstWhere('person_id', $person->id)?->role, ['driver', 'co_driver'], true))->pluck('id'))
            ->where('status', 'waiting_2nd')->where('person_id', '!=', $person->id)->orderBy('trusted_at')->get();

        $shift = DrivingShift::whereIn('trip_id', $tripIds)->whereNull('ended_at')->orderByDesc('started_at')->first();

        return [
            'server_time' => $now->toIso8601String(),
            'full'        => $since === null,
            'person'      => ['id' => $person->id, 'name' => $person->name, 'type' => $person->type, 'can_record' => $this->access->canRecord()],
            'company'     => ['id' => $company->uuid, 'name' => $company->name, 'country' => $company->country, 'currency' => $company->currency, 'timezone' => $company->timezone],
            'settings'    => collect($this->settings->all())->only(self::APP_SETTINGS)->all(),
            'duty'        => ($duty = DutySession::where('person_id', $person->id)->whereNull('ended_at')->first()) ? ['on' => true, 'since' => $duty->started_at->toIso8601String()] : ['on' => false],
            'driving'     => $shift ? ['trip' => $trips->firstWhere('id', $shift->trip_id)?->uuid, 'person_id' => $shift->person_id, 'since' => $shift->started_at->toIso8601String()] : null,
            'trips'       => $trips->map(fn (Trip $t) => $this->trip($t, $person, $freight))->values()->all(),
            'events'      => $events->map(fn (RoadEvent $e) => $this->event($e, $trips))->values()->all(),
            'waiting_for_me' => $waiting->map(fn (RoadEvent $e) => [
                'uuid' => $e->uuid, 'trip' => $trips->firstWhere('id', $e->trip_id)?->uuid, 'by' => $e->person?->name,
                'amount' => (float) $e->amount, 'at' => $e->trusted_at->toIso8601String(),
                'authority' => $e->data['authority'] ?? null, 'reason' => $e->data['reason'] ?? null, 'place' => $e->data['place'] ?? null,
            ])->values()->all(),
            'papers'      => $this->papers($person, $trips),
            'lists'       => $since === null ? $this->lists() : null,
        ];
    }

    /** Trips sent to them, running, or ended in the last 15 days (settled or not). */
    private function trips(Person $person): Collection
    {
        return Trip::with(['crew.person:id,name,mobile,type', 'truck', 'supplierVehicle', 'from', 'to', 'stops.place',
            'consignments' => fn ($q) => $q->where('status', '!=', 'cancelled')->with(['consignor:id,name,mobile', 'consignee:id,name,mobile', 'ewaybills']),
            'settlements' => fn ($q) => $q->current()->where('person_id', $person->id)])
            ->whereHas('crew', fn ($q) => $q->where('person_id', $person->id))
            ->where(fn ($q) => $q->whereIn('status', Trip::OPEN_STATUSES)
                ->orWhere(fn ($q) => $q->whereIn('status', ['ended', 'settled', 'closed'])->where('ended_at', '>=', now()->subDays(self::KEEP_ENDED_DAYS))))
            ->orderBy('trip_date')->limit(20)->get();
    }

    private function trip(Trip $t, Person $person, bool $freight): array
    {
        $me = $t->crew->firstWhere('person_id', $person->id);

        return [
            'uuid' => $t->uuid, 'number' => $t->trip_number, 'status' => $t->status, 'date' => $t->trip_date->toDateString(),
            'load_type' => $t->load_type, 'kind' => $t->trip_kind, 'my_role' => $me?->role,
            'accepted' => $me?->accepted_at !== null, 'rejected' => $me?->rejected_at !== null,
            'vehicle' => $t->vehicleLabel(),
            'truck' => $t->truck ? ['reg_no' => $t->truck->reg_no, 'make' => $t->truck->make, 'model' => $t->truck->model, 'tank_l' => $t->truck->fuel_tank_l, 'odometer_km' => $t->truck->odometer_km] : null,
            'from' => $t->from?->name, 'to' => $t->to?->name, 'planned_km' => $t->planned_km,
            'planned_start_at' => $t->planned_start_at?->toIso8601String(), 'expected_delivery_at' => $t->expected_delivery_at?->toIso8601String(),
            'started_at' => $t->started_at?->toIso8601String(), 'ended_at' => $t->ended_at?->toIso8601String(), 'start_odometer' => $t->start_odometer,
            'instructions' => $t->instructions,
            // My trip cash, once the office has settled it (blueprint 03 §8 step 4).
            'settlement' => ($st = $t->settlements->first()) ? [
                'advances' => (float) $st->advances, 'collected' => (float) $st->collected, 'spent' => (float) $st->spent, 'balance' => (float) $st->balance,
                'mode' => $st->mode, 'mode_label' => __(\App\Modules\Trips\Models\TripSettlement::MODES[$st->mode]), 'bata' => (float) $st->bata_amount,
                'settled_at' => $st->settled_at->toIso8601String(),
            ] : null,
            'budget' => ['fuel' => $t->budget_fuel, 'toll' => $t->budget_toll, 'bata' => $t->budget_bata],
            'crew' => $t->crew->map(fn ($c) => ['person_id' => $c->person_id, 'name' => $c->person?->name, 'role' => $c->role, 'mobile' => $c->person?->mobile])->values()->all(),
            'stops' => $t->stops->map(fn ($s) => [
                'id' => $s->id, 'seq' => $s->seq, 'kind' => $s->kind, 'place' => $s->place?->name, 'code' => $s->place?->short_code,
                'address' => $s->place?->address, 'lat' => $s->place?->lat, 'lng' => $s->place?->lng,
                'planned_at' => $s->planned_at?->toIso8601String(), 'arrived_at' => $s->arrived_at?->toIso8601String(), 'departed_at' => $s->departed_at?->toIso8601String(),
            ])->values()->all(),
            'loads' => $t->consignments->map(fn (Consignment $c) => [
                'id' => $c->id, 'lr_number' => $c->lr_number, 'status' => $c->status,
                'consignor' => $c->consignor ? ['name' => $c->consignor->name, 'mobile' => $c->consignor->mobile] : null,
                'consignee' => $c->consignee ? ['name' => $c->consignee->name, 'mobile' => $c->consignee->mobile] : null,
                'pickup_stop_id' => $c->pickup_stop_id, 'drop_stop_id' => $c->drop_stop_id,
                'material' => $c->material, 'description' => $c->description, 'packages' => $c->packages, 'package_type' => $c->package_type,
                'weight_mt' => $c->actual_weight_mt, 'seal_no' => $c->seal_no, 'container_no' => $c->container_no,
                'payment_type' => $c->payment_type, 'pod_status' => $c->pod_status,
                // "To pay" freight is collected by the driver at delivery: they must know it.
                'freight' => $freight || $c->payment_type === 'to_pay' ? (float) $c->totalCharges() : null,
                'ewaybills' => $c->ewaybills->map(fn ($e) => ['number' => $e->number, 'valid_until' => $e->valid_until?->toIso8601String()])->values()->all(),
            ])->values()->all(),
        ];
    }

    private function event(RoadEvent $e, Collection $trips): array
    {
        return [
            'uuid' => $e->uuid, 'trip' => $trips->firstWhere('id', $e->trip_id)?->uuid, 'person_id' => $e->person_id,
            'type' => $e->type, 'amount' => $e->amount !== null ? (float) $e->amount : null, 'payment_mode' => $e->payment_mode,
            'odometer' => $e->odometer, 'data' => $e->data, 'note' => $e->note, 'at' => $e->trusted_at->toIso8601String(),
            'status' => $e->status, 'second_by' => $e->second_by, 'second_reject_reason' => $e->second_reject_reason,
            'office_note' => in_array($e->status, ['queried', 'rejected'], true) ? $e->decision_note : null,
            'photos' => $e->photos->pluck('slot')->values()->all(), 'photos_expected' => $e->photos_expected,
            'updated_at' => $e->updated_at->toIso8601String(),
        ];
    }

    /**
     * The papers of the trucks on their trips, and their own (licence…), to
     * show when stopped. ID papers of other people never go to a phone.
     */
    private function papers(Person $person, Collection $trips): array
    {
        $truckIds = $trips->whereIn('status', Trip::OPEN_STATUSES)->pluck('truck_id')->filter()->unique();

        return Document::with('files')
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('owner_type', 'truck')->whereIn('owner_id', $truckIds))
                ->orWhere(fn ($q) => $q->where('owner_type', 'person')->where('owner_id', $person->id)))
            ->orderBy('owner_type')->orderBy('document_type')->get()
            ->reject(fn (Document $d) => $d->owner_type === 'truck' && $this->compliance->isSensitive($d->document_type))
            ->map(fn (Document $d) => [
                'id' => $d->id, 'owner' => $d->owner_type, 'truck' => $d->owner_type === 'truck' ? $trips->firstWhere('truck_id', $d->owner_id)?->truck?->reg_no : null,
                'type' => $d->document_type, 'name' => $this->compliance->typeName($d->document_type), 'number' => $d->number,
                'expires_on' => $d->expires_on?->toDateString(), 'state' => $this->compliance->state($d),
                'files' => $d->currentFiles()->map(fn ($f) => ['id' => $f->id, 'mime' => $f->mime])->values()->all(),
            ])->values()->all();
    }

    /** The lists the phone's forms show, sent on a full sync only. */
    private function lists(): array
    {
        return [
            'event_types' => collect(EventTypes::TYPES)->map(fn ($t, $code) => [
                'code' => $code, 'label' => __($t[0]), 'approval' => $t[2], 'photos' => $t[3], 'amount' => $t[4],
            ])->values()->all(),
            'payment_modes' => collect(EventTypes::PAYMENT_MODES)->map(fn ($m, $code) => ['code' => $code, 'label' => __($m[0]), 'own_pocket' => $m[1]])->values()->all(),
            'commands' => array_keys(CrewCommands::COMMANDS),
        ];
    }
}
