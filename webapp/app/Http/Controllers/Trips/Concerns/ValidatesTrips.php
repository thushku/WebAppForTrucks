<?php

namespace App\Http\Controllers\Trips\Concerns;

use App\Modules\Parties\Models\SupplierVehicle;
use App\Modules\Trips\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The trip plan's fields as the web form and the owner app send them (M23):
 * route, truck or hired truck, crew, budget, branch. The class using it has
 * `$this->context`.
 */
trait ValidatesTrips
{
    /** @return array{0: array<string, mixed>, 1: array<int, array{person_id: int, role: string}>} */
    protected function validatedTrip(Request $request, ?Trip $trip = null): array
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
            // A branch-limited person plans trips only for their branches (M22).
            'branch_id'            => app(\App\Platform\Tenancy\BranchContext::class)->limited()
                ? ['required', 'integer', Rule::in(app(\App\Platform\Tenancy\BranchContext::class)->allowed())]
                : ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
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
