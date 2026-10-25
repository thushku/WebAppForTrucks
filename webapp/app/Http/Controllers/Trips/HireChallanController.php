<?php

namespace App\Http\Controllers\Trips;

use App\Http\Controllers\Controller;
use App\Modules\Parties\Models\Party;
use App\Modules\Trips\Models\HireChallan;
use App\Modules\Trips\Models\Trip;
use App\Modules\Trips\Services\HireService;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The lorry hire challan of a trip with a hired truck. */
class HireChallanController extends Controller
{
    public function __construct(private CompanyContext $context, private HireService $hire) {}

    public function create(Trip $trip): View
    {
        abort_unless($trip->truck_source === 'hired', 404);
        $trip->load(['supplier', 'supplierVehicle', 'consignments']);
        $tds = $this->hire->tdsFor($trip->supplier);

        $challan = new HireChallan([
            'challan_date'   => now($this->context->company()->timezone)->toDateString(),
            'vehicle_reg'    => $trip->supplierVehicle?->reg_no,
            'driver_name'    => $trip->supplierVehicle?->driver_name,
            'driver_mobile'  => $trip->supplierVehicle?->driver_mobile,
            'driver_licence' => $trip->supplierVehicle?->driver_licence,
            'hire_weight_mt' => $trip->consignments->where('status', '!=', 'cancelled')->sum(fn ($c) => $c->weight()) ?: null,
            'tds_section'    => $tds['section'],
            'tds_rate'       => $tds['rate'],
            'tds_nil_reason' => $tds['nil_reason'],
            'balance_days_after_pod' => $trip->supplier->payment_days,
        ]);

        return view('trips.hire-challan', $this->formData($trip, $challan));
    }

    public function store(Request $request, Trip $trip): RedirectResponse
    {
        abort_unless($trip->truck_source === 'hired', 404);
        $challan = $this->hire->save($trip, null, $this->validated($request) + ['supplier_vehicle_id' => $trip->supplier_vehicle_id]);

        return redirect()->route('trips.hire.edit', [$trip, $challan])->with('status', __('Hire challan saved as a draft.'));
    }

    public function edit(Trip $trip, HireChallan $challan): View
    {
        $this->own($trip, $challan);

        return view('trips.hire-challan', $this->formData($trip->load(['supplier', 'supplierVehicle']), $challan->load('lines')));
    }

    public function update(Request $request, Trip $trip, HireChallan $challan): RedirectResponse
    {
        $this->own($trip, $challan);
        $this->hire->save($trip, $challan, $this->validated($request));

        return back()->with('status', __('Saved.'));
    }

    public function issue(Trip $trip, HireChallan $challan): RedirectResponse
    {
        $this->own($trip, $challan);
        $this->hire->issue($challan);

        return back()->with('status', __('Hire challan :no issued.', ['no' => $challan->number]));
    }

    public function storeLine(Request $request, Trip $trip, HireChallan $challan): RedirectResponse
    {
        $this->own($trip, $challan);
        $data = $request->validate([
            'kind'      => ['required', Rule::in(array_keys(HireChallan::LINE_KINDS))],
            'amount'    => ['required', 'numeric', 'min:1', 'max:99999999'],
            'line_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'mode'      => ['nullable', 'required_if:kind,advance,payment', Rule::in(['cash', 'upi', 'bank'])],
            'paid_to'   => ['nullable', Rule::in(['owner', 'broker', 'driver'])],
            'reference' => ['nullable', 'string', 'max:60'],
            'note'      => ['nullable', 'required_if:kind,deduction', 'string', 'max:255'],
        ], ['note.required_if' => __('Say what the deduction is for.'), 'mode.required_if' => __('How was it paid?')]);

        $this->hire->addLine($challan, $data);

        return back()->with('status', __('Recorded.'));
    }

    private function own(Trip $trip, HireChallan $challan): void
    {
        abort_unless($challan->trip_id === $trip->id, 404);
    }

    private function formData(Trip $trip, HireChallan $challan): array
    {
        return [
            'trip'    => $trip,
            'challan' => $challan,
            'brokers' => Party::active()->withRole('broker')->orderBy('name')->pluck('name', 'id'),
        ];
    }

    private function validated(Request $request): array
    {
        $companyId = $this->context->id();

        $data = $request->validate([
            'challan_date'   => ['required', 'date_format:Y-m-d'],
            'vehicle_reg'    => ['required', 'string', 'max:20'],
            'driver_name'    => ['nullable', 'string', 'max:120'],
            'driver_mobile'  => ['nullable', 'string', 'max:20'],
            'driver_licence' => ['nullable', 'string', 'max:30'],
            'hire_basis'     => ['required', Rule::in(['fixed', 'tonne'])],
            'hire_amount'    => ['nullable', 'required_if:hire_basis,fixed', 'numeric', 'min:1', 'max:99999999'],
            'hire_rate'      => ['nullable', 'required_if:hire_basis,tonne', 'numeric', 'min:1', 'max:9999999'],
            'hire_weight_mt' => ['nullable', 'required_if:hire_basis,tonne', 'numeric', 'min:0.001', 'max:500'],
            'broker_party_id' => ['nullable', 'integer', Rule::exists('party_roles', 'party_id')->where('company_id', $companyId)->where('role', 'broker')],
            'commission_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'commission_mode'   => ['required', Rule::in(['deducted', 'paid'])],
            'office_charge'  => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'tds_section'    => ['nullable', 'string', 'max:10'],
            'tds_rate'       => ['nullable', 'numeric', 'min:0', 'max:30'],
            'tds_nil_reason' => ['nullable', 'string', 'max:150'],
            'balance_days_after_pod' => ['nullable', 'integer', 'min:0', 'max:180'],
            'terms'          => ['nullable', 'string', 'max:2000'],
        ]);

        foreach (['commission_amount', 'office_charge', 'tds_rate'] as $money) {
            $data[$money] = (float) ($data[$money] ?? 0);
        }
        $data['vehicle_reg'] = strtoupper($data['vehicle_reg']);

        return $data;
    }
}
