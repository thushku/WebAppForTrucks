<?php

namespace App\Http\Controllers\Trips;

use App\Http\Controllers\Controller;
use App\Modules\Parties\Models\Party;
use App\Modules\Settings\Models\MasterItem;
use App\Modules\Trips\Models\Consignment;
use App\Modules\Trips\Models\Trip;
use App\Modules\Trips\Models\TripStop;
use App\Modules\Trips\Services\ConsignmentService;
use App\Modules\Trips\Services\TripService;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Loads (consignments / LRs) and stops on a trip page. A load or stop of
 * another trip in the URL is a 404.
 */
class ConsignmentController extends Controller
{
    use Concerns\ValidatesLoads;

    public function __construct(private CompanyContext $context, private ConsignmentService $consignments, private TripService $trips) {}

    public function create(Trip $trip): View
    {
        $stops = $trip->stops()->with('place')->get();

        return view('trips.consignment', $this->formData($trip, new Consignment([
            'pickup_stop_id' => $stops->first()?->id,
            'drop_stop_id'   => $stops->last()?->id,
            'payment_type'   => 'to_be_billed',
            'freight_basis'  => 'trip',
            'gst_payable_by' => $this->context->company()->country === 'IN' ? 'consignor' : null,
        ])));
    }

    public function store(Request $request, Trip $trip): RedirectResponse
    {
        $load = $this->validatedLoad($request, '', withStops: true);
        $ewaybills = $this->ewaybillsFrom($load);
        $consignment = $this->consignments->save($trip, null, $load, $ewaybills);

        return redirect()->route('trips.show', $trip)->with('status', $consignment->lr_number ? __('Load added as LR :lr.', ['lr' => $consignment->lr_number]) : __('Load added.'));
    }

    public function edit(Trip $trip, Consignment $consignment): View
    {
        $this->own($trip, $consignment);

        return view('trips.consignment', $this->formData($trip, $consignment->load('ewaybills')));
    }

    public function update(Request $request, Trip $trip, Consignment $consignment): RedirectResponse
    {
        $this->own($trip, $consignment);
        $load = $this->validatedLoad($request, '', withStops: true);
        $ewaybills = $this->ewaybillsFrom($load);
        $this->consignments->save($trip, $consignment, $load, $ewaybills);

        return redirect()->route('trips.show', $trip)->with('status', __('Load saved.'));
    }

    public function cancel(Request $request, Trip $trip, Consignment $consignment): RedirectResponse
    {
        $this->own($trip, $consignment);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:200']]);
        $this->consignments->cancel($trip, $consignment, $data['reason']);

        return redirect()->route('trips.show', $trip)->with('status', __('Load cancelled.'));
    }

    // ---- Stops ---------------------------------------------------------------------------

    public function storeStop(Request $request, Trip $trip): RedirectResponse
    {
        $data = $request->validate([
            'place_id'  => ['required', 'integer', Rule::exists('places', 'id')->where('company_id', $this->context->id())],
            'kind'      => ['required', Rule::in(array_keys(TripStop::KINDS))],
            'after_seq' => ['nullable', 'integer', 'min:1'],
        ]);
        $this->trips->addStop($trip, (int) $data['place_id'], $data['kind'], isset($data['after_seq']) ? (int) $data['after_seq'] : null);
        if ($trip->load_type === 'ftl') {
            $trip->forceFill(['load_type' => 'ptl'])->save();
        }

        return back()->with('status', __('Stop added.'));
    }

    public function destroyStop(Trip $trip, TripStop $stop): RedirectResponse
    {
        abort_unless($stop->trip_id === $trip->id, 404);
        $this->trips->removeStop($trip, $stop);

        return back()->with('status', __('Stop removed.'));
    }

    private function own(Trip $trip, Consignment $consignment): void
    {
        abort_unless($consignment->trip_id === $trip->id, 404);
    }

    private function formData(Trip $trip, Consignment $consignment): array
    {
        return [
            'trip'        => $trip->load(['stops.place', 'truck']),
            'consignment' => $consignment,
            'clients'     => Party::active()->withRole('client')->orderBy('name')->get(['id', 'name', 'short_code']),
            'partiesAll'  => Party::active()->orderBy('name')->get(['id', 'name', 'short_code']),
            'materials'   => MasterItem::ofList('material')->where('active', true)->pluck('name', 'code'),
        ];
    }
}
