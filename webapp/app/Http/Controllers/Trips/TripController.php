<?php

namespace App\Http\Controllers\Trips;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Parties\Models\Party;
use App\Modules\Parties\Models\SupplierVehicle;
use App\Modules\People\Models\Person;
use App\Modules\Road\Models\EventPhoto;
use App\Modules\Road\Models\RoadEvent;
use App\Modules\Road\Services\DrivingHours;
use App\Modules\Settings\Models\Branch;
use App\Modules\Settings\Models\MasterItem;
use App\Modules\Settings\Models\Place;
use App\Modules\Settings\Services\CompanySettings;
use App\Modules\Trips\Models\Consignment;
use App\Modules\Trips\Models\Trip;
use App\Modules\Trips\Services\ConsignmentService;
use App\Modules\Trips\Models\TripOfficeEntry;
use App\Modules\Trips\Services\LoadPlan;
use App\Modules\Trips\Services\SettlementService;
use App\Modules\Trips\Services\TripProfit;
use App\Modules\Trips\Services\TripService;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Trips: the list with its filters and totals (13 §A #4), the new-trip form
 * (full load quick form, or the start of a part-load trip), the trip page,
 * and the steps of its life (send, start, end, cancel).
 */
class TripController extends Controller
{
    use Concerns\ValidatesTrips;
    use Concerns\ValidatesLoads;

    public function __construct(
        private CompanyContext $context,
        private TripService $trips,
        private ConsignmentService $consignments,
        private LoadPlan $loadPlan,
        private SettlementService $settlements,
    ) {}

    public function index(Request $request): View
    {
        [$query, $f] = $this->filtered($request);

        // The totals follow the filters: N trips · freight + other charges.
        $ids   = (clone $query)->pluck('id');
        $money = Consignment::whereIn('trip_id', $ids)->where('status', '!=', 'cancelled')
            ->selectRaw('coalesce(sum(freight),0) as freight, coalesce(sum(other_charges + loading_charge + unloading_charge),0) as extra')->first();

        $trips = $query->with(['truck', 'supplierVehicle', 'from', 'to', 'crew.person', 'consignments.billingParty'])
            ->orderByDesc('trip_date')->orderByDesc('id')
            ->paginate(50)->withQueryString();

        return view('trips.index', [
            'trips'   => $trips,
            'filters' => $f,
            'totals'  => ['count' => $ids->count(), 'freight' => (float) $money->freight, 'other' => (float) $money->extra + (float) Trip::whereIn('id', $ids)->sum('other_charges')],
            'places'  => Place::active()->orderBy('name')->pluck('name', 'id'),
            'trucks'  => Truck::inService()->orderBy('reg_no')->pluck('reg_no', 'id'),
            'parties' => Party::active()->withRole('client')->orderBy('name')->pluck('name', 'id'),
            'counts'  => Trip::inBranch()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    /**
     * The trip list's filters (13-parity #4), shared by the list and its
     * report: status, dates, a place anywhere on the route, truck, client,
     * number, and billing — not invoiced, invoiced (unpaid) or paid.
     *
     * @return array{0: \Illuminate\Database\Eloquent\Builder, 1: array<string, mixed>}
     */
    private function filtered(Request $request): array
    {
        $f = $request->validate([
            'status'  => ['nullable', 'string', 'max:12'],
            'from'    => ['nullable', 'date_format:Y-m-d'],
            'to'      => ['nullable', 'date_format:Y-m-d'],
            'place'   => ['nullable', 'integer'],
            'truck'   => ['nullable', 'integer'],
            'party'   => ['nullable', 'integer'],
            'q'       => ['nullable', 'string', 'max:40'],
            'billing' => ['nullable', Rule::in(['not_invoiced', 'invoiced', 'paid'])],
            'km'      => ['nullable', Rule::in(['differs'])],
        ]);
        $f['status'] = $f['status'] ?? 'active';
        $status = $f['status'];
        $unbilled = fn ($c) => $c->where('status', '!=', 'cancelled')->whereNull('invoice_id');

        $query = Trip::query()->inBranch()
            ->when($status === 'active', fn ($q) => $q->whereNotIn('status', ['closed', 'cancelled']))
            ->when(isset(Trip::STATUSES[$status]), fn ($q) => $q->where('status', $status))
            ->when($f['from'] ?? null, fn ($q, $d) => $q->where('trip_date', '>=', $d))
            ->when($f['to'] ?? null, fn ($q, $d) => $q->where('trip_date', '<=', $d))
            ->when($f['truck'] ?? null, fn ($q, $id) => $q->where('truck_id', $id))
            // A place matches the origin, the destination or any stop.
            ->when($f['place'] ?? null, fn ($q, $id) => $q->where(fn ($w) => $w->where('from_place_id', $id)->orWhere('to_place_id', $id)->orWhereHas('stops', fn ($s) => $s->where('place_id', $id))))
            ->when($f['party'] ?? null, fn ($q, $id) => $q->whereHas('consignments', fn ($c) => $c->where('billing_party_id', $id)->orWhere('consignor_party_id', $id)->orWhere('consignee_party_id', $id)))
            ->when(filled($f['q'] ?? null), fn ($q) => $q->where('trip_number', 'like', '%' . addcslashes(strtoupper($f['q']), '%_\\') . '%'))
            // Odometer and GPS apart (M34).
            ->when(($f['km'] ?? null) === 'differs', fn ($q) => $q->where('km_check', 'differs'))
            // Billing: a load still to bill; every load billed with something unpaid; every load billed and paid.
            ->when(($f['billing'] ?? null) === 'not_invoiced', fn ($q) => $q->whereHas('consignments', $unbilled))
            ->when(($f['billing'] ?? null) === 'invoiced', fn ($q) => $q->whereDoesntHave('consignments', $unbilled)
                ->whereHas('consignments', fn ($c) => $c->whereHas('invoice', fn ($i) => $i->whereIn('status', \App\Modules\Invoicing\Models\Invoice::OPEN_STATUSES))))
            ->when(($f['billing'] ?? null) === 'paid', fn ($q) => $q->whereHas('consignments', fn ($c) => $c->whereHas('invoice', fn ($i) => $i->where('status', 'paid')))
                ->whereDoesntHave('consignments', fn ($c) => $c->where('status', '!=', 'cancelled')->where(fn ($w) => $w->whereNull('invoice_id')->orWhereHas('invoice', fn ($i) => $i->where('status', '!=', 'paid')))));

        return [$query, $f];
    }

    /**
     * The trip report of the filtered list (13-parity #5): on the letterhead
     * with when it was made and the filters, one row per trip; to print or
     * save as PDF, or as a spreadsheet (CSV, opens in Excel).
     */
    public function report(Request $request, \App\Modules\Invoicing\Services\DocumentData $data, \App\Modules\Invoicing\Services\DocumentRenderer $renderer): Response|\Symfony\Component\HttpFoundation\StreamedResponse|RedirectResponse
    {
        [$query, $f] = $this->filtered($request);
        $trips = $query->with(['truck:id,reg_no', 'supplierVehicle:id,reg_no', 'from:id,name', 'to:id,name', 'crew.person:id,name', 'consignments.billingParty:id,name'])
            ->orderBy('trip_date')->orderBy('id')->limit(2000)->get();
        $rows = $trips->map(function (Trip $t) {
            $loads = $t->consignments->where('status', '!=', 'cancelled');
            $freight = round((float) $loads->sum('freight'), 2);
            $other = round((float) $t->other_charges + (float) $loads->sum(fn ($c) => (float) $c->other_charges + (float) $c->loading_charge + (float) $c->unloading_charge), 2);

            return [
                'date' => $t->trip_date?->format('d-m-Y'), 'number' => $t->label, 'route' => ($t->from?->name ?? '') . ' → ' . ($t->to?->name ?? ''),
                'truck' => $t->truck?->reg_no ?? $t->supplierVehicle?->reg_no, 'driver' => $t->crew->firstWhere('role', 'driver')?->person?->name,
                'client' => $loads->pluck('billingParty.name')->filter()->unique()->join(', '), 'freight' => $freight, 'other' => $other, 'total' => round($freight + $other, 2),
                'note' => $t->office_note,
            ];
        });
        $company = $this->context->company();
        $label = __('Trips') . '-' . ($f['from'] ?? $trips->first()?->trip_date?->toDateString() ?? 'all') . '-' . ($f['to'] ?? $trips->last()?->trip_date?->toDateString() ?? 'all');

        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, [__('Date'), __('Trip no.'), __('Route'), __('Truck'), __('Driver'), __('Client'), __('Freight'), __('Other charges'), __('Total'), __('Office note')], ',', '"', '');
                foreach ($rows as $r) {
                    // A cell that would run as a spreadsheet formula is made plain text.
                    fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, array_values($r)), ',', '"', '');
                }
                fclose($out);
            }, \Illuminate\Support\Str::slug($label) . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $html = view('documents.trip-report', [
            'rows' => $rows, 'seller' => $data->letterhead(), 'filters' => $this->filterWords($f), 'tz' => $company->timezone ?: 'UTC',
            'totals' => ['freight' => $rows->sum('freight'), 'other' => $rows->sum('other'), 'total' => $rows->sum('total')],
            'period' => trim(($f['from'] ?? '') . ' – ' . ($f['to'] ?? ''), ' –'),
        ])->render();
        if ($request->query('format') === 'pdf') {
            return $renderer->canPdf() ? \App\Modules\Invoicing\Services\DocumentRenderer::pdfResponse($renderer->pdf($html), \Illuminate\Support\Str::slug($label))
                : redirect()->route('trips.report', collect($f)->except('format')->all());
        }

        return response($html);
    }

    /** The filters in words, for the report's heading. */
    private function filterWords(array $f): array
    {
        return array_values(array_filter([
            ($f['status'] ?? 'active') === 'active' ? null : __('Status: :s', ['s' => __(Trip::STATUSES[$f['status']] ?? $f['status'])]),
            isset($f['place']) ? __('Place: :p', ['p' => Place::whereKey($f['place'])->value('name')]) : null,
            isset($f['truck']) ? __('Truck: :t', ['t' => Truck::whereKey($f['truck'])->value('reg_no')]) : null,
            isset($f['party']) ? __('Client: :c', ['c' => Party::whereKey($f['party'])->value('name')]) : null,
            isset($f['billing']) ? ['not_invoiced' => __('Not invoiced'), 'invoiced' => __('Invoiced, unpaid'), 'paid' => __('Paid')][$f['billing']] : null,
            isset($f['km']) ? __('Odometer and GPS apart') : null,
            filled($f['q'] ?? null) ? __('Number: :q', ['q' => $f['q']]) : null,
        ]));
    }

    public function create(Request $request): View
    {
        $trip = new Trip([
            'trip_date'    => CarbonImmutable::now($this->context->company()->timezone)->toDateString(),
            'load_type'    => in_array($request->query('load'), array_keys(Trip::LOAD_TYPES), true) ? $request->query('load') : 'ftl',
            'truck_source' => $request->query('source') === 'hired' ? 'hired' : 'own',
            'branch_id'    => app(\App\Platform\Tenancy\BranchContext::class)->defaultBranch(),
        ]);

        // From a quotation's route (M21): the route, the client and the quoted rate filled in.
        $prefill = null;
        $line = $request->filled('quotation_line') ? \App\Modules\Invoicing\Models\QuotationLine::with('quotation')->find((int) $request->query('quotation_line')) : null;
        if ($line && in_array($line->quotation->status, ['issued', 'accepted'], true)) {
            $trip->from_place_id = $line->from_place_id;
            $trip->to_place_id = $line->to_place_id;
            $prefill = new Consignment([
                'billing_party_id' => $line->quotation->party_id, 'material' => $line->material, 'freight_basis' => $line->basis, 'rate' => $line->rate,
                'detention_free_hours' => $line->detention_free_hours, 'detention_rate_per_day' => $line->detention_rate_per_day,
                'loading_charge' => $line->loading_charge, 'unloading_charge' => $line->unloading_charge, 'payment_type' => 'to_be_billed', 'quotation_id' => $line->quotation_id,
            ]);
        }

        return view('trips.form', $this->formData($trip, true) + ['prefill' => $prefill]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$fields, $crew] = $this->validatedTrip($request);
        $consignment = $fields['trip_kind'] === 'empty' ? null : $this->validatedLoad($request, 'load.');
        $ewaybills   = $consignment ? $this->ewaybillsFrom($consignment) : [];

        if ($consignment) {
            // Freight needs the trip's km for a per-km rate; work it out with the trip's fields.
            $consignment['freight'] = $this->consignments->freight(new Trip($fields), $consignment);
        }
        try {
            $trip = $this->trips->create($fields, $crew, $consignment, $ewaybills);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // The load's own fields sit under "load." on this form (a typed LR number, M21).
            throw \Illuminate\Validation\ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $k) => [$k === 'lr_number' ? 'load.lr_number' : $k => $m])->all());
        }

        return redirect()->route('trips.show', $trip)->with('status', __('Trip saved as a draft. Check it, then send it to the crew.'));
    }

    public function show(Trip $trip): View
    {
        $trip->load(['truck', 'supplier', 'supplierVehicle', 'from', 'to', 'branch', 'returnOf', 'crew.person', 'stops.place',
            'consignments' => fn ($q) => $q->with(['billingParty', 'consignor', 'consignee', 'pickupStop.place', 'dropStop.place', 'ewaybills']),
            'advances.person', 'hireChallans.lines', 'commissions.broker']);

        return view('trips.show', [
            'trip'      => $trip,
            'events'    => RoadEvent::with(['person:id,name', 'photos:id,road_event_id,slot,duplicate_of', 'approvals.account:id,name', 'approvals.person:id,name'])
                ->where('trip_id', $trip->id)->orderBy('trusted_at')->orderBy('id')->get()->each->setRelation('trip', $trip),
            'driving'   => app(DrivingHours::class)->forTrip($trip),
            'accounts'  => in_array($trip->status, ['ended', 'settled', 'closed'], true) ? $this->settlements->accounts($trip) : collect(),
            'blockers'  => $trip->status === 'ended' ? $this->settlements->blockers($trip) : [],
            'profit'    => app(TripProfit::class)->forTrip($trip),
            'fixed'     => app(\App\Modules\Fleet\Services\TruckCosts::class)->tripShare($trip),
            'office'    => TripOfficeEntry::with('party:id,name')->where('trip_id', $trip->id)->orderBy('entry_date')->orderBy('id')->get(),
            'expenseCategories' => MasterItem::ofList('expense_category')->where('active', true)->pluck('name', 'code'),
            'legs'      => $this->loadPlan->legs($trip),
            'check'     => $trip->isDraft() ? $this->trips->dispatchCheck($trip) : null,
            'materials' => MasterItem::ofList('material')->pluck('name', 'code'),
            'places'    => Place::active()->orderBy('name')->get(['id', 'name', 'short_code']),
            'canMoney'  => auth()->user()->can('trip.freight_view'),
        ]);
    }

    /** A photo the crew sent with an entry on this trip. */
    public function photo(Trip $trip, EventPhoto $photo): Response
    {
        abort_unless(RoadEvent::whereKey($photo->road_event_id)->where('trip_id', $trip->id)->exists(), 404);

        return response(Storage::disk('local')->get($photo->path), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=3600']);
    }

    public function edit(Trip $trip): View
    {
        abort_unless($trip->isPlanning(), 403, __('The trip has started; its plan can no longer change.'));

        return view('trips.form', $this->formData($trip->load('crew'), false));
    }

    public function update(Request $request, Trip $trip): RedirectResponse
    {
        [$fields, $crew] = $this->validatedTrip($request, $trip);
        $this->trips->update($trip, $fields, $crew);

        return redirect()->route('trips.show', $trip)->with('status', __('Saved.'));
    }

    public function assign(Request $request, Trip $trip): RedirectResponse
    {
        $data = $request->validate(['override_reason' => ['nullable', 'string', 'max:255']]);
        $this->trips->assign($trip, $data['override_reason'] ?? null);

        return redirect()->route('trips.show', $trip)->with('status', __('Trip :no sent to the crew.', ['no' => $trip->trip_number]));
    }

    public function start(Request $request, Trip $trip): RedirectResponse
    {
        $data = $request->validate(['start_odometer' => ['nullable', 'integer', 'min:0', 'max:9999999']]);
        $this->trips->start($trip, isset($data['start_odometer']) ? (int) $data['start_odometer'] : null);

        return back()->with('status', __('Trip started.'));
    }

    public function end(Request $request, Trip $trip): RedirectResponse
    {
        $data = $request->validate(['end_odometer' => ['nullable', 'integer', 'min:0', 'max:9999999'], 'ended_early' => ['nullable', 'boolean']]);
        $this->trips->end($trip, isset($data['end_odometer']) ? (int) $data['end_odometer'] : null, $request->boolean('ended_early'));

        return back()->with('status', __('Trip ended. Check the crew entries, then settle.'));
    }

    public function cancel(Request $request, Trip $trip): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']]);
        $this->trips->cancel($trip, $data['reason']);

        return redirect()->route('trips.show', $trip)->with('status', __('Trip cancelled.'));
    }

    /** The agreed rate for the load being typed (fills the form). */
    public function rate(Request $request): JsonResponse
    {
        $companyId = $this->context->id();
        $data = $request->validate([
            'party'    => ['required', 'integer', Rule::exists('parties', 'id')->where('company_id', $companyId)],
            'from'     => ['required', 'integer', Rule::exists('places', 'id')->where('company_id', $companyId)],
            'to'       => ['required', 'integer', Rule::exists('places', 'id')->where('company_id', $companyId)],
            'material' => ['nullable', 'string', 'max:40'],
            'truck'    => ['nullable', 'integer'],
            'date'     => ['nullable', 'date_format:Y-m-d'],
        ]);
        $truckType = ! empty($data['truck']) ? Truck::whereKey($data['truck'])->value('truck_type') : null;

        return response()->json(['rate' => $this->consignments->suggestRate((int) $data['party'], (int) $data['from'], (int) $data['to'], $data['material'] ?? null, $truckType,
            CarbonImmutable::parse($data['date'] ?? 'today'))]);
    }

    // ---- Helpers ------------------------------------------------------------------------

    private function formData(Trip $trip, bool $creating): array
    {
        $crew = $trip->exists ? $trip->crew : collect();

        return [
            'trip'      => $trip,
            'creating'  => $creating,
            'crewNow'   => [
                'driver'    => $crew->firstWhere('role', 'driver')?->person_id,
                'co_driver' => $crew->firstWhere('role', 'co_driver')?->person_id,
                'helpers'   => $crew->where('role', 'helper')->pluck('person_id')->all(),
            ],
            'trucks'    => Truck::inService()->orderBy('reg_no')->get(['id', 'reg_no', 'truck_type', 'status', 'payload_mt']),
            'busyTrucks' => Trip::open()->where('id', '!=', $trip->id ?? 0)->whereNotNull('truck_id')->pluck('trip_number', 'truck_id'),
            'suppliers' => Party::active()->withRole('supplier')->orderBy('name')->pluck('name', 'id'),
            'vehicles'  => SupplierVehicle::where('active', true)->orderBy('reg_no')->get(['id', 'party_id', 'reg_no', 'capacity_mt']),
            'drivers'   => Person::working()->whereIn('type', ['driver'])->orderBy('name')->get(['id', 'name', 'licence_expiry', 'licence_no', 'type']),
            'helpers'   => Person::working()->whereIn('type', ['helper', 'driver', 'other'])->orderBy('name')->get(['id', 'name', 'type']),
            'places'    => Place::active()->orderBy('name')->get(['id', 'name', 'short_code']),
            'clients'   => Party::active()->withRole('client')->orderBy('name')->get(['id', 'name', 'short_code']),
            'partiesAll' => Party::active()->orderBy('name')->get(['id', 'name', 'short_code']),
            'materials' => MasterItem::ofList('material')->where('active', true)->pluck('name', 'code'),
            'branches'  => app(\App\Platform\Tenancy\BranchContext::class)->limited() ? app(\App\Platform\Tenancy\BranchContext::class)->choices() : Branch::where('active', true)->orderBy('name')->pluck('name', 'id'),
            'returnable' => Trip::whereIn('status', ['running', 'ended', 'settled', 'closed'])->where('id', '!=', $trip->id ?? 0)->orderByDesc('trip_date')->limit(30)->pluck('trip_number', 'id'),
            'gstDefault' => $this->context->company()->country === 'IN' ? 'consignor' : null,
        ];
    }

}
