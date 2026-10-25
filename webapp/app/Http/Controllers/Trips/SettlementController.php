<?php

namespace App\Http\Controllers\Trips;

use App\Http\Controllers\Controller;
use App\Modules\People\Models\Person;
use App\Modules\Trips\Models\Trip;
use App\Modules\Trips\Models\TripSettlement;
use App\Modules\Trips\Services\SettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Settle each crew member's trip cash, close the trip, and (the owner) reopen it. */
class SettlementController extends Controller
{
    public function __construct(private SettlementService $settlements) {}

    public function settle(Request $request, Trip $trip, Person $person): RedirectResponse
    {
        $data = $request->validate([
            'mode'        => ['required', Rule::in(array_keys(TripSettlement::MODES))],
            'reference'   => ['nullable', 'string', 'max:60'],
            'note'        => ['nullable', 'string', 'max:255'],
            'bata_amount' => ['nullable', 'numeric', 'min:0', 'max:999999'],
        ]);
        $this->settlements->settle($trip, $person, $data);

        $trip->refresh();

        return redirect()->to(route('trips.show', $trip) . '#settlement')->with('status', $trip->status === 'settled'
            ? __('Everybody is settled. The trip can be closed.')
            : __(':name is settled.', ['name' => $person->name]));
    }

    public function settleWithoutCrew(Trip $trip): RedirectResponse
    {
        $this->settlements->settleWithoutCrew($trip);

        return redirect()->to(route('trips.show', $trip) . '#settlement')->with('status', __('Trip settled.'));
    }

    public function close(Trip $trip): RedirectResponse
    {
        $this->settlements->close($trip);

        return redirect()->route('trips.show', $trip)->with('status', __('Trip :no is closed. The crew’s entries are locked; office entries can still be added.', ['no' => $trip->label]));
    }

    public function reopen(Request $request, Trip $trip): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->settlements->reopen($trip, $data['reason']);

        return redirect()->route('trips.show', $trip)->with('status', __('Trip reopened. Settle it again when it is ready.'));
    }
}
