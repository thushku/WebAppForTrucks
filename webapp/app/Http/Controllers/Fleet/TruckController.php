<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Models\Complaint;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Fleet\Services\FleetService;
use App\Modules\Parties\Models\Party;
use App\Modules\Settings\Models\Branch;
use App\Modules\Settings\Models\MasterItem;
use App\Platform\Tenancy\CompanyContext;
use App\Support\Regions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Trucks: the list, the profile form and the truck page (blueprint 04 §3.6).
 * A sold truck stays on file (its trips and costs are history).
 */
class TruckController extends Controller
{
    public function __construct(private CompanyContext $context, private FleetService $fleet) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $search = trim((string) $request->query('q', ''));

        $trucks = Truck::query()
            ->withCount(['complaints as open_complaints_count' => fn ($q) => $q->whereNotIn('status', ['solved', 'closed'])])
            ->when(isset(Truck::STATUSES[$status]), fn ($q) => $q->where('status', $status), fn ($q) => $q->where('status', '!=', 'sold'))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('reg_key', 'like', '%' . Truck::key($search) . '%')->orWhere('make', 'like', '%' . addcslashes($search, '%_\\') . '%')->orWhere('model', 'like', '%' . addcslashes($search, '%_\\') . '%')))
            ->orderBy('reg_no')
            ->paginate(50)->withQueryString();

        $counts = Truck::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('trucks.index', [
            'trucks' => $trucks, 'status' => $status, 'search' => $search, 'counts' => $counts,
            'types'  => MasterItem::ofList('truck_type')->pluck('name', 'code'),
        ]);
    }

    public function create(): View
    {
        return view('trucks.form', $this->formData(new Truck(['registration_state' => $this->context->company()->state_code])));
    }

    public function store(Request $request): RedirectResponse
    {
        $truck = Truck::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return redirect()->route('trucks.show', $truck)->with('status', __(':reg added.', ['reg' => $truck->reg_no]));
    }

    public function show(Request $request, Truck $truck): View
    {
        $tab = in_array($request->query('tab'), ['overview', 'complaints', 'service'], true) ? $request->query('tab') : 'overview';
        $truck->load(['owner', 'branch']);
        $compliance = app(\App\Modules\Documents\Services\Compliance::class);
        $documents  = \App\Modules\Documents\Models\Document::where('owner_type', 'truck')->where('owner_id', $truck->id)->whereNotNull('expires_on')->orderBy('expires_on')->get();

        return view('trucks.show', [
            'truck'      => $truck,
            'tab'        => $tab,
            'types'      => MasterItem::ofList('truck_type')->pluck('name', 'code'),
            'bodies'     => MasterItem::ofList('body_type')->pluck('name', 'code'),
            'categories' => MasterItem::ofList('complaint_category')->where('active', true)->pluck('name', 'code'),
            'complaints' => $truck->complaints()->with('raisedBy')->orderByRaw("status in ('solved','closed')")->orderByDesc('id')->limit($tab === 'complaints' ? 200 : 5)->get(),
            'schedules'  => $truck->serviceSchedules()->where('active', true)->orderBy('name')->get(),
            'jobs'       => $truck->serviceJobs()->with('vendor')->limit($tab === 'service' ? 200 : 5)->get(),
            'compliance' => $compliance,
            'documents'  => $documents,
            'missing'    => $compliance->missing($truck),
            'health'     => $compliance->health($truck),
        ]);
    }

    public function edit(Truck $truck): View
    {
        return view('trucks.form', $this->formData($truck));
    }

    public function update(Request $request, Truck $truck): RedirectResponse
    {
        $truck->update($this->validated($request, $truck));

        return redirect()->route('trucks.show', $truck)->with('status', __('Saved.'));
    }

    /** Free / workshop / breakdown / out of service by hand. "On trip" is set by trips only (M5). */
    public function status(Request $request, Truck $truck): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['idle', 'workshop', 'breakdown', 'out_of_service', 'sold'])], 'odometer_km' => ['nullable', 'integer', 'min:0', 'max:9999999']]);

        if ($truck->status === 'on_trip') {
            throw ValidationException::withMessages(['status' => __('The truck is on a trip. Its status changes when the trip ends.')]);
        }
        $truck->forceFill(['status' => $data['status'], 'sold_on' => $data['status'] === 'sold' ? ($truck->sold_on ?? now()) : null])->save();
        if (! empty($data['odometer_km'])) {
            $this->fleet->moveOdometer($truck, (int) $data['odometer_km']);
        }

        return back()->with('status', __(':reg is now :status.', ['reg' => $truck->reg_no, 'status' => __(Truck::STATUSES[$data['status']])]));
    }

    private function formData(Truck $truck): array
    {
        $country = $this->context->company()->country;

        return [
            'truck'    => $truck,
            'country'  => $country,
            'regions'  => Regions::for($country),
            'types'    => MasterItem::ofList('truck_type')->where('active', true)->get(['code', 'name', 'meta']),
            'bodies'   => MasterItem::ofList('body_type')->where('active', true)->pluck('name', 'code'),
            'branches' => Branch::where('active', true)->orderBy('name')->pluck('name', 'id'),
            'owners'   => Party::active()->withRole('supplier')->orderBy('name')->pluck('name', 'id'),
        ];
    }

    private function validated(Request $request, ?Truck $truck = null): array
    {
        $companyId = $this->context->id();
        $country   = $this->context->company()->country;

        $data = $request->validate([
            'reg_no'             => ['required', 'string', 'min:4', 'max:20', 'regex:/^[A-Za-z0-9\s\-]+$/'],
            'registration_state' => ['nullable', Rule::in(array_keys(Regions::for($country)))],
            'registered_rto'     => ['nullable', 'string', 'max:80'],
            'registration_date'  => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'rc_owner_name'      => ['required', 'string', 'min:2', 'max:150'],
            'vehicle_class'      => ['nullable', 'string', 'max:60'],
            'make'               => ['nullable', 'string', 'max:60'],
            'model'              => ['nullable', 'string', 'max:80'],
            'manufacture_year'   => ['nullable', 'integer', 'min:1980', 'max:' . (now()->year + 1)],
            'chassis_no'         => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9]+$/'],
            'engine_no'          => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9]+$/'],
            'emission_norm'      => ['nullable', Rule::in(Truck::NORMS)],
            'fuel_type'          => ['required', Rule::in(array_keys(Truck::FUELS))],
            'truck_type'         => ['nullable', Rule::exists('master_items', 'code')->where('company_id', $companyId)->where('list', 'truck_type')],
            'body_type'          => ['nullable', Rule::exists('master_items', 'code')->where('company_id', $companyId)->where('list', 'body_type')],
            'wheels'             => ['nullable', 'integer', 'min:3', 'max:40'],
            'axle_config'        => ['nullable', 'string', 'max:20'],
            'lift_axle'          => ['nullable', 'boolean'],
            'deck_length_ft'     => ['nullable', 'numeric', 'min:0', 'max:80'],
            'rate_card_label'    => ['nullable', 'string', 'max:40'],
            'gvw_kg'             => ['nullable', 'integer', 'min:0', 'max:100000'],
            'unladen_kg'         => ['nullable', 'integer', 'min:0', 'max:100000', Rule::when($request->filled('gvw_kg'), ['lte:gvw_kg'])],
            'payload_mt'         => ['nullable', 'numeric', 'min:0', 'max:200'],
            'fuel_tank_l'        => ['nullable', 'integer', 'min:0', 'max:2000'],
            'mileage_loaded_kmpl' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'mileage_empty_kmpl' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'ownership'          => ['required', Rule::in(array_keys(Truck::OWNERSHIP))],
            'owner_party_id'     => ['nullable', 'required_if:ownership,attached', 'integer', Rule::exists('parties', 'id')->where('company_id', $companyId)],
            'financier'          => ['nullable', 'string', 'max:120'],
            'emi_amount'         => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'emi_day'            => ['nullable', 'integer', 'min:1', 'max:31'],
            'loan_ends_on'       => ['nullable', 'date_format:Y-m-d'],
            'purchase_date'      => ['nullable', 'date_format:Y-m-d'],
            'purchase_price'     => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'odometer_km'        => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'fastag_no'          => ['nullable', 'string', 'max:40'],
            'tracker_device_id'  => ['nullable', 'string', 'max:60'],
            'branch_id'          => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'notes'              => ['nullable', 'string', 'max:2000'],
        ], [
            'reg_no.regex'  => __('Use letters, digits, spaces and dashes only.'),
            'unladen_kg.lte' => __('Unladen weight cannot be more than the gross weight.'),
            'owner_party_id.required_if' => __('Choose the owner of an attached truck (add them as a supplier first).'),
        ]);

        $data['reg_no'] = strtoupper(trim(preg_replace('/\s+/', ' ', $data['reg_no'])));
        $other = Truck::where('reg_key', Truck::key($data['reg_no']))->where('id', '!=', $truck?->id ?? 0)->first();
        if ($other) {
            throw ValidationException::withMessages(['reg_no' => __(':reg is already in your fleet.', ['reg' => $other->reg_no])]);
        }

        foreach (['chassis_no', 'engine_no'] as $f) {
            $data[$f] = isset($data[$f]) ? strtoupper($data[$f]) : null;
        }
        $data['lift_axle'] = $request->boolean('lift_axle');
        if ($data['ownership'] !== 'attached') {
            $data['owner_party_id'] = null;
        }
        // The odometer is typed once here; after that trips and jobs move it forward.
        if ($truck && $truck->odometer_km !== null) {
            unset($data['odometer_km']);
        } elseif (isset($data['odometer_km'])) {
            $data['odometer_at'] = now();
        }

        return $data;
    }
}
