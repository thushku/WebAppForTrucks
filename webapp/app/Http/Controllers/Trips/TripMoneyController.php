<?php

namespace App\Http\Controllers\Trips;

use App\Http\Controllers\Controller;
use App\Modules\Trips\Models\BrokerCommission;
use App\Modules\Trips\Models\Trip;
use App\Modules\Trips\Models\TripAdvance;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Money on a trip page that is not crew entries: cash advances to the crew
 * and broker commissions (load brokers, or our own brokerage). Posted to the
 * books with accounting (M11); settled against crew entries in M7.
 */
class TripMoneyController extends Controller
{
    public function __construct(private CompanyContext $context) {}

    public function storeAdvance(Request $request, Trip $trip): RedirectResponse
    {
        if (in_array($trip->status, ['settled', 'closed', 'cancelled'], true)) {
            throw ValidationException::withMessages(['advance' => __('This trip is :status.', ['status' => mb_strtolower(__(Trip::STATUSES[$trip->status]))])]);
        }
        $crewIds = $trip->crew()->pluck('person_id')->all();
        $data = $request->validate([
            'person_id' => ['required', 'integer', Rule::in($crewIds)],
            'amount'    => ['required', 'numeric', 'min:1', 'max:9999999'],
            'mode'      => ['required', Rule::in(array_keys(TripAdvance::MODES))],
            'given_on'  => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reference' => ['nullable', 'required_unless:mode,cash', 'string', 'max:60'],
            'note'      => ['nullable', 'string', 'max:255'],
        ], ['person_id.in' => __('Advances go to the crew of this trip.'), 'reference.required_unless' => __('Give the UPI or bank reference.')]);

        DB::transaction(fn () => TripAdvance::create($data + ['trip_id' => $trip->id, 'created_by' => $request->user()->id]));

        return back()->with('status', __('Advance recorded.'));
    }

    public function storeCommission(Request $request, Trip $trip): RedirectResponse
    {
        $companyId = $this->context->id();
        $data = $request->validate([
            'broker_party_id' => ['required', 'integer', Rule::exists('party_roles', 'party_id')->where('company_id', $companyId)->where('role', 'broker')],
            'direction'       => ['required', Rule::in(['payable', 'receivable'])],
            'basis'           => ['required', Rule::in(['fixed', 'percent'])],
            'percent'         => ['nullable', 'required_if:basis,percent', 'numeric', 'min:0.01', 'max:50'],
            'amount'          => ['nullable', 'required_if:basis,fixed', 'numeric', 'min:1', 'max:9999999'],
            'consignment_id'  => ['nullable', 'integer', Rule::exists('consignments', 'id')->where('trip_id', $trip->id)],
            'note'            => ['nullable', 'string', 'max:255'],
        ], ['broker_party_id.exists' => __('Choose a party with the broker role.')]);

        if ($data['basis'] === 'percent') {
            // A percentage of the load's freight, or of the whole trip's freight.
            $freight = $trip->consignments()->where('status', '!=', 'cancelled')
                ->when($data['consignment_id'] ?? null, fn ($q, $id) => $q->whereKey($id))->sum('freight');
            $data['amount'] = round((float) $freight * (float) $data['percent'] / 100, 2);
        }

        BrokerCommission::create($data + ['trip_id' => $trip->id]);

        return back()->with('status', __('Commission recorded.'));
    }

    public function destroyCommission(Trip $trip, BrokerCommission $commission): RedirectResponse
    {
        abort_unless($commission->trip_id === $trip->id && $commission->hire_challan_id === null, 404);
        if (in_array($trip->status, ['closed', 'cancelled'], true)) {
            throw ValidationException::withMessages(['commission' => __('The trip is closed.')]);
        }
        $commission->delete();

        return back()->with('status', __('Commission removed.'));
    }
}
