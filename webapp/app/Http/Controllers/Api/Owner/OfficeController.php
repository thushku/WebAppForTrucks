<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Services\Statements;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentFile;
use App\Modules\Documents\Services\Compliance;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Reports\Services\Dashboard;
use App\Modules\Reports\Services\LiveMap;
use App\Modules\Road\Models\RoadEvent;
use App\Modules\Trips\Models\Trip;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Settings\PlatformSettings;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * The owner app's operations screens (blueprint 05 §3, §6.2): the
 * dashboard, the live map, trips with their detail and route, trucks, and the
 * papers that expire. Read only; the same numbers and permissions as the web
 * pages, from the same services. Money is left out for people who may not see
 * it. Amounts are numbers in the company's currency; times are ISO 8601.
 */
class OfficeController extends Controller
{
    public function __construct(private CompanyContext $context) {}

    public function dashboard(Request $request, Dashboard $dashboard, LiveMap $map, PlatformSettings $settings): JsonResponse
    {
        $user = $request->user();
        $money = $user->canAny(['report.view', 'accounts.view', 'ledger.view']);
        $company = $this->context->company();

        return response()->json([
            'company' => ['name' => $company->name, 'currency' => $company->currency, 'read_only' => $company->isReadOnly()],
            'counts' => $dashboard->counts(),
            'profit' => $money ? $dashboard->profit() : null,
            'spending_today' => $user->can('trip.view') ? collect($dashboard->spendingToday())->map(fn ($amount, $label) => ['label' => $label, 'amount' => $amount])->values() : [],
            'queues' => $user->canAny(['invoice.view', 'lr.view']) ? $dashboard->queues() : null,
            'alerts' => collect($dashboard->alerts($user->can('map.view') ? $map->positions() : collect()))->map(fn ($a) => ['level' => $a['level'], 'text' => $a['text']])->values(),
            'announcement' => $settings->get('announcement'),
        ]);
    }

    public function map(LiveMap $map): JsonResponse
    {
        $positions = $map->positions();
        // The phone opens a trip by its public id, never the database number.
        $uuids = Trip::whereIn('id', $positions->pluck('trip_id'))->pluck('uuid', 'id');

        return response()->json(['positions' => $positions->map(fn ($p) => ['trip_uuid' => $uuids[$p['trip_id']] ?? null] + collect($p)->except(['url', 'trip_id'])->all())->values()]);
    }

    public function trips(Request $request): JsonResponse
    {
        $f = $request->validate([
            'status' => ['nullable', Rule::in(['planned', 'running', 'ended', 'settled', 'closed', 'cancelled'])],
            'q' => ['nullable', 'string', 'max:40'], 'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $statuses = ['planned' => ['assigned', 'accepted'], 'running' => ['running'], 'ended' => ['ended'], 'settled' => ['settled'], 'closed' => ['closed'], 'cancelled' => ['cancelled']];
        $trips = Trip::inBranch()->with(['truck:id,reg_no', 'supplierVehicle:id,reg_no', 'from:id,name', 'to:id,name', 'crew.person:id,name'])
            ->when($f['status'] ?? null, fn ($q, $s) => $q->whereIn('status', $statuses[$s]), fn ($q) => $q->where('status', '!=', 'draft'))
            ->when(filled($f['q'] ?? null), fn ($q) => $q->where(fn ($q) => $q->where('trip_number', 'like', '%' . addcslashes($f['q'], '%_\\') . '%')
                ->orWhereHas('truck', fn ($t) => $t->where('reg_no', 'like', '%' . addcslashes($f['q'], '%_\\') . '%'))))
            ->orderByDesc('trip_date')->orderByDesc('id')->paginate(30);

        return response()->json([
            'trips' => collect($trips->items())->map(fn (Trip $t) => $this->tripRow($t))->values(),
            'page' => $trips->currentPage(), 'more' => $trips->hasMorePages(),
        ]);
    }

    public function trip(Request $request, string $uuid, Statements $statements): JsonResponse
    {
        $trip = Trip::inBranch()->with(['truck:id,reg_no,ownership', 'supplierVehicle:id,reg_no', 'supplier:id,name', 'from:id,name', 'to:id,name', 'crew.person:id,name,mobile', 'stops.place:id,name',
            'consignments' => fn ($q) => $q->with(['billingParty:id,name', 'consignor:id,name', 'consignee:id,name'])])->where('uuid', $uuid)->firstOrFail();
        $user = $request->user();
        $freight = $user->can('trip.freight_view');
        $events = RoadEvent::where('trip_id', $trip->id)->whereNotIn('status', ['cancelled'])->get(['status', 'amount', 'type']);

        return response()->json($this->tripRow($trip) + [
            'instructions' => $trip->instructions,
            'planned_km' => $trip->planned_km, 'start_odometer' => $trip->start_odometer, 'end_odometer' => $trip->end_odometer,
            // Odometer against GPS (M34): ok | differs | thin | none; gap in % of the GPS distance.
            'gps_km' => $trip->gps_km !== null ? (float) $trip->gps_km : null, 'gps_coverage' => $trip->gps_coverage, 'km_check' => $trip->km_check,
            'km_gap' => \App\Modules\Road\Services\GpsDistance::gap($trip),
            'stops' => $trip->stops->map(fn ($s) => ['seq' => $s->seq, 'place' => $s->place?->name, 'kind' => $s->kind,
                'arrived_at' => $s->arrived_at?->toIso8601String(), 'departed_at' => $s->departed_at?->toIso8601String()])->values(),
            'loads' => $trip->consignments->map(fn ($c) => [
                'lr_number' => $c->lr_number, 'status' => $c->status, 'billing_party' => $c->billingParty?->name, 'consignor' => $c->consignor?->name, 'consignee' => $c->consignee?->name,
                'packages' => $c->packages, 'weight_mt' => $c->actual_weight_mt !== null ? (float) $c->actual_weight_mt : null, 'freight' => $freight ? (float) $c->freight : null,
            ])->values(),
            'crew' => $trip->crew->map(fn ($c) => ['name' => $c->person?->name, 'mobile' => $c->person?->mobile, 'role' => $c->role,
                'answer' => $c->rejected_at ? 'rejected' : ($c->accepted_at ? 'accepted' : 'waiting')])->values(),
            'entries' => [
                'count' => $events->count(),
                'to_check' => $events->whereIn('status', RoadEvent::OPEN)->count(),
                'spent' => round($events->where('status', '!=', 'rejected')->where('type', '!=', 'money_received')->sum(fn ($e) => (float) $e->amount), 2),
            ],
            'profit' => $user->canAny(['report.view', 'accounts.view', 'ledger.view']) ? $statements->tripProfit($trip->id) : null,
            // The truck's costs whether it runs or not for the trip's days (M37), an estimate: {days, per_day, share}; null for a hired truck.
            'fixed_costs' => $freight ? app(\App\Modules\Fleet\Services\TruckCosts::class)->tripShare($trip) : null,
            // Its tyres' wear for the trip's km (M38), an estimate: {km, tyres, amount}; null without odometer readings or tyres with a cost per km.
            'tyre_wear' => $freight ? app(\App\Modules\Fleet\Services\Tyres::class)->wearFor($trip) : null,
            'route_url' => $user->can('map.view') ? route('api.owner.trips.route', $trip->uuid) : null,
        ]);
    }

    public function route(string $uuid, LiveMap $map): JsonResponse
    {
        $trip = Trip::inBranch()->where('uuid', $uuid)->firstOrFail();

        return response()->json($map->route($trip, 1500));
    }

    public function trucks(): JsonResponse
    {
        $trucks = Truck::inBranch()->where('status', '!=', 'sold')->orderBy('reg_no')->get(['id', 'reg_no', 'truck_type', 'status', 'ownership']);
        $papers = Document::where('owner_type', 'truck')->whereIn('owner_id', $trucks->pluck('id'))->whereNotNull('expires_on')->get(['owner_id', 'document_type', 'expires_on'])->groupBy('owner_id');

        return response()->json(['trucks' => $trucks->map(function (Truck $truck) use ($papers) {
            $own = $papers[$truck->id] ?? collect();
            $next = $own->sortBy('expires_on')->first();

            return [
                'id' => $truck->id, 'reg_no' => $truck->reg_no, 'type' => $truck->truck_type, 'status' => $truck->status, 'status_label' => __(Truck::STATUSES[$truck->status] ?? $truck->status),
                'papers_expired' => $own->filter(fn ($d) => $d->daysLeft() < 0)->count(),
                'next_expiry' => $next ? ['type' => $next->document_type, 'on' => $next->expires_on->toDateString(), 'days_left' => $next->daysLeft()] : null,
            ];
        })->values()]);
    }

    /** Papers that have expired or expire within the days asked (30 by default), most urgent first. */
    public function papers(Request $request, Compliance $compliance): JsonResponse
    {
        $days = (int) ($request->validate(['days' => ['nullable', 'integer', 'min:1', 'max:365']])['days'] ?? 30);
        $sensitive = $request->user()->can('people.id_documents');
        $docs = Document::with('files')->whereNotNull('expires_on')
            ->where('expires_on', '<=', now()->addDays($days)->toDateString())->orderBy('expires_on')->limit(300)->get()
            // ID papers stay out of the list for people who may not see them, as on the web.
            ->reject(fn (Document $d) => ! $sensitive && $compliance->isSensitive($d->document_type))->values();
        $owners = $compliance->ownerLabels($docs);

        return response()->json(['papers' => $docs->map(fn (Document $d) => [
            'id' => $d->id, 'type' => $d->document_type, 'type_label' => $compliance->typeName($d->document_type), 'number' => $d->number,
            'owner_type' => $d->owner_type, 'owner' => $owners["{$d->owner_type}:" . ($d->owner_type === 'company' ? 0 : $d->owner_id)] ?? null,
            'expires_on' => $d->expires_on->toDateString(), 'days_left' => $d->daysLeft(), 'state' => $d->state(),
            'files' => $d->currentFiles()->map(fn ($f) => ['id' => $f->id, 'name' => $f->original_name, 'mime' => $f->mime, 'url' => route('api.owner.papers.file', [$d->id, $f->id])])->values(),
        ])->values()]);
    }

    public function paperFile(Request $request, Document $document, DocumentFile $file, Compliance $compliance, AuditRecorder $audit): Response
    {
        abort_unless($file->document_id === $document->id && Storage::disk('local')->exists($file->path), 404);
        // ID papers (Aadhaar, licence numbers) only for those who may see them, and every look is logged, as on the web.
        if ($compliance->isSensitive($document->document_type)) {
            abort_unless($request->user()->can('people.id_documents'), 403, __('Only people allowed to see ID papers can open this.'));
            $audit->record('document.file_viewed', "Opened a file of {$document->document_type} ({$document->owner_type} #{$document->owner_id}) in the owner app", 'document', (string) $document->id, $document->number, severity: 'sensitive');
        }

        return response(Storage::disk('local')->get($file->path), 200, ['Content-Type' => $file->mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** Routes (M33): what a trip on each lane earns, costs and makes, for trips ended in the period (default: the last 90 days). */
    public function routes(Request $request, \App\Modules\Reports\Services\Reports $reports): JsonResponse
    {
        $f = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $today = \Carbon\CarbonImmutable::now($this->context->company()->timezone ?: 'UTC')->startOfDay();
        $to = isset($f['to']) ? \Carbon\CarbonImmutable::parse($f['to']) : $today;
        $from = isset($f['from']) ? \Carbon\CarbonImmutable::parse($f['from']) : $to->subDays(90);

        return response()->json(['from' => $from->toDateString(), 'to' => $to->toDateString(), 'routes' => $reports->lanes($from, $to)->map(fn ($r) => [
            'route' => $r->route, 'empty' => $r->empty, 'trips' => $r->trips, 'income' => $r->income, 'cost' => $r->cost, 'profit' => $r->profit, 'fixed' => $r->fixed, 'tyres' => $r->tyres, 'net' => $r->net, 'margin' => $r->margin,
            'km' => $r->km, 'per_km' => $r->per_km, 'days' => $r->days, 'best' => $r->best, 'worst' => $r->worst, 'main_costs' => $r->main_costs,
            'last' => ['trip' => $r->last['trip']->trip_number, 'uuid' => $r->last['trip']->uuid, 'date' => $r->last['date']?->toDateString(), 'income' => $r->last['income']],
        ])->values()]);
    }

    private function tripRow(Trip $t): array
    {
        return [
            'uuid' => $t->uuid, 'number' => $t->trip_number, 'date' => $t->trip_date?->toDateString(), 'status' => $t->status, 'status_label' => __(Trip::STATUSES[$t->status] ?? $t->status),
            'load_type' => $t->load_type, 'vehicle' => $t->truck?->reg_no ?? $t->supplierVehicle?->reg_no, 'hired' => $t->truck_source === 'hired',
            'from' => $t->from?->name, 'to' => $t->to?->name,
            'driver' => $t->crew->firstWhere('role', 'driver')?->person?->name,
            'started_at' => $t->started_at?->toIso8601String(), 'ended_at' => $t->ended_at?->toIso8601String(),
        ];
    }
}
