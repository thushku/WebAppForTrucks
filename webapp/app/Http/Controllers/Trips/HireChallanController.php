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
use Illuminate\View\View;

/** The lorry hire challan of a trip with a hired truck. */
class HireChallanController extends Controller
{
    public function __construct(private CompanyContext $context, private HireService $hire) {}

    public function create(Trip $trip): View
    {
        abort_unless($trip->truck_source === 'hired', 404);
        $challan = new HireChallan($this->hire->draftFor($trip));

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
        $data = $request->validate($this->hire->lineRules(), $this->hire->lineMessages());

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
        return $this->hire->prepare($request->validate($this->hire->rules()));
    }
}
