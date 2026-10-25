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
use App\Modules\Trips\Services\LoadPlan;
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
    use Concerns\ValidatesLoads;

    public function __construct(
        private CompanyContext $context,
        private TripService $trips,
        private ConsignmentService $consignments,
        private LoadPlan $loadPlan,
    ) {}

    public function index(Request $request): View
    {
        $f = $request->validate([
            'status' => ['nullable', 'string', 'max:12'],
            'from'   => ['nullable', 'date_format:Y-m-d'],
            'to'     => ['nullable', 'date_format:Y-m-d'],
            'place'  => ['nullable', 'integer'],
            'truck'  => ['nullable', 'integer'],
            'party'  => ['nullable', 'integer'],
            'q'      => ['nullable', 'string', 'max:40'],
        ]);
        $status = $f['status'] ?? 'active';

        $query = Trip::query()
            ->when($status === 'active', fn ($q) => $q->whereNotIn('status', ['closed', 'cancelled']))
            ->when(isset(Trip::STATUSES[$status]), fn ($q) => $q->where('status', $status))
            ->when($f['from'] ?? null, fn ($q, $d) => $q->where('trip_date', '>=', $d))
            ->when($f['to'] ?? null, fn ($q, $d) => $q->where('trip_date', '<=', $d))
            ->when($f['truck'] ?? null, fn ($q, $id) => $q->where('truck_id', $id))
            // A place matches the origin, the destination or any stop.
            ->when($f['place'] ?? null, fn ($q, $id) => $q->where(fn ($w) => $w->where('from_place_id', $id)->orWhere('to_place_id', $id)->orWhereHas('stops', fn ($s) => $s->where('place_id', $id))))
            ->when($f['party'] ?? null, fn ($q, $id) => $q->whereHas('consignments', fn ($c) => $c->where('billing_party_id', $id)->orWhere('consignor_party_id', $id)->orWhere('consignee_party_id', $id)))
            ->when(filled($f['q'] ?? null), fn ($q) => $q->where('trip_number', 'like', '%' . addcslashes(strtoupper($f['q']), '%_\\') . '%'));

        // The totals follow the filters: N trips · freight + other charges.
        $ids   = (clone $query)->pluck('id');
        $money = Consignment::whereIn('trip_id', $ids)->where('status', '!=', 'cancelled')
            ->selectRaw('coalesce(sum(freight),0) as freight, coalesce(sum(other_charges + loading_charge + unloading_charge),0) as extra')->first();

        $trips = $query->with(['truck', 'supplierVehicle', 'from', 'to', 'crew.person', 'consignments.billingParty'])
            ->orderByDesc('trip_date')->orderByDesc('id')
            ->paginate(50)->withQueryString();

        return view('trips.index', [
            'trips'   => $trips,
            'filters' => $f + ['status' => $status],
            'totals'  => ['count' => $ids->count(), 'freight' => (float) $money->freight, 'other' => (float) $money->extra + (float) Trip::whereIn('id', $ids)->sum('other_charges')],
            'places'  => Place::active()->orderBy('name')->pluck('name', 'id'),
            'trucks'  => Truck::inService()->orderBy('reg_no')->pluck('reg_no', 'id'),
            'parties' => Party::active()->withRole('client')->orderBy('name')->pluck('name', 'id'),
            'counts'  => Trip::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function create(Request $request): View
    {
        $trip = new Trip([
            'trip_date'    => CarbonImmutable::now($this->context->company()->timezone)->toDateString(),
            'load_type'    => in_array($request->query('load'), array_keys(Trip::LOAD_TYPES), true) ? $request->query('load') : 'ftl',
            'truck_source' => $request->query('source') === 'hired' ? 'hired' : 'own',
        ]);

        return view('trips.form', $this->formData($trip, true));
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
        $trip = $this->trips->create($fields, $crew, $consignment, $ewaybills);

        return redirect()->route('trips.show', $trip)->with('status', __('Trip saved as a draft. Check it, then send it to the crew.'));
    }

    public function show(Trip $trip): View
    {
        $trip->load(['truck', 'supplier', 'supplierVehicle', 'from', 'to', 'branch', 'returnOf', 'crew.person', 'stops.place',
            'consignments' => fn ($q) => $q->with(['billingParty', 'consignor', 'consignee', 'pickupStop.place', 'dropStop.place', 'ewaybills']),
            'advances.person', 'hireChallans.lines', 'commissions.broker']);

        return view('trips.show', [
            'trip'      => $trip,
            'events'    => RoadEvent::with(['person:id,name', 'photos:id,road_event_id,slot,duplicate_of'])->where('trip_id', $trip->id)->orderBy('trusted_at')->orderBy('id')->get(),
            'driving'   => app(DrivingHours::class)->forTrip($trip),
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
            'branches'  => Branch::where('active', true)->orderBy('name')->pluck('name', 'id'),
            'returnable' => Trip::whereIn('status', ['running', 'ended', 'settled', 'closed'])->where('id', '!=', $trip->id ?? 0)->orderByDesc('trip_date')->limit(30)->pluck('trip_number', 'id'),
            'gstDefault' => $this->context->company()->country === 'IN' ? 'consignor' : null,
        ];
    }

    /** @return array{0: array<string, mixed>, 1: array<int, array{person_id: int, role: string}>} */
    private function validatedTrip(Request $request, ?Trip $trip = null): array
    {
        $companyId = $this->context->id();
        $place = Rule::exists('places', 'id')->where('company_id', $companyId);
        $person = Rule::exists('people', 'id')->where('company_id', $companyId)->where('status', 'active');

        $data = $request->validate([
            'trip_date'            => ['required', 'date_format:Y-m-d', 'after:-1 year', 'before:+60 days'],
            'load_type'            => ['required', Rule::in(array_keys(Trip::LOAD_TYPES))],
            'trip_kind'            => ['required', Rule::in(array_keys(Trip::KINDS))],
            'truck_source'         => ['required', Rule::in(['own', 'hired'])],
            'truck_id'             => ['nullable', 'required_if:truck_source,own', 'integer', Rule::exists('trucks', 'id')->where('company_id', $companyId)->where('status', '!=', 'sold')],
            'supplier_party_id'    => ['nullable', 'required_if:truck_source,hired', 'integer', Rule::exists('parties', 'id')->where('company_id', $companyId)],
            'supplier_vehicle_id'  => ['nullable', 'required_if:truck_source,hired', 'integer', Rule::exists('supplier_vehicles', 'id')->where('company_id', $companyId)],
            'driver_id'            => ['nullable', 'required_if:truck_source,own', 'integer', $person],
            'co_driver_id'         => ['nullable', 'integer', $person, 'different:driver_id'],
            'helper_ids'           => ['nullable', 'array', 'max:4'],
            'helper_ids.*'         => ['integer', $person],
            'from_place_id'        => ['required', 'integer', $place],
            'to_place_id'          => ['required', 'integer', $place, 'different:from_place_id'],
            'planned_km'           => ['nullable', 'integer', 'min:1', 'max:20000'],
            'planned_start_at'     => ['nullable', 'date'],
            'expected_delivery_at' => ['nullable', 'date', 'after_or_equal:planned_start_at'],
            'budget_fuel'          => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'budget_toll'          => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'budget_bata'          => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'other_charges'        => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'cost_allocation_method' => ['nullable', Rule::in(['freight', 'weight', 'tonne_km'])],
            'instructions'         => ['nullable', 'string', 'max:2000'],
            'office_note'          => ['nullable', 'string', 'max:2000'],
            'branch_id'            => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'return_of_trip_id'    => ['nullable', 'integer', Rule::exists('trips', 'id')->where('company_id', $companyId)],
        ], [
            'truck_id.required_if'   => __('Choose the truck.'),
            'driver_id.required_if'  => __('Choose the driver.'),
            'to_place_id.different'  => __('From and to must be different places.'),
            'co_driver_id.different' => __('The co-driver must be someone else.'),
            'supplier_vehicle_id.required_if' => __('Choose the hired truck (add it under the supplier first).'),
        ]);

        if ($data['truck_source'] === 'hired') {
            $vehicle = SupplierVehicle::findOrFail($data['supplier_vehicle_id']);
            if ($vehicle->party_id !== (int) $data['supplier_party_id']) {
                throw ValidationException::withMessages(['supplier_vehicle_id' => __('That truck belongs to another supplier.')]);
            }
            $data['truck_id'] = null;
        } else {
            $data['supplier_party_id'] = $data['supplier_vehicle_id'] = null;
        }

        $crew = [];
        if (! empty($data['driver_id'])) {
            $crew[] = ['person_id' => (int) $data['driver_id'], 'role' => 'driver'];
        }
        if (! empty($data['co_driver_id'])) {
            $crew[] = ['person_id' => (int) $data['co_driver_id'], 'role' => 'co_driver'];
        }
        foreach (array_unique($data['helper_ids'] ?? []) as $id) {
            if (! in_array((int) $id, [(int) ($data['driver_id'] ?? 0), (int) ($data['co_driver_id'] ?? 0)], true)) {
                $crew[] = ['person_id' => (int) $id, 'role' => 'helper'];
            }
        }

        $fields = collect($data)->except(['driver_id', 'co_driver_id', 'helper_ids'])->all();
        $fields['other_charges'] = (float) ($fields['other_charges'] ?? 0);

        return [$fields, $crew];
    }
}
