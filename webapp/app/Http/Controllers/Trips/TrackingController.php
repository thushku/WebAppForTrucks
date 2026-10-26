<?php

namespace App\Http\Controllers\Trips;

use App\Http\Controllers\Controller;
use App\Modules\Trips\Models\Consignment;
use App\Modules\Trips\Models\Trip;
use App\Modules\Trips\Services\Tracking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * The client's tracking link for an LR (M45): the public page /t/{token}
 * (no sign-in, never indexed), and the trip page's "Tracking link" that
 * makes it once and offers it to copy or send on WhatsApp.
 */
class TrackingController extends Controller
{
    public function __construct(private Tracking $tracking) {}

    public function show(string $token): Response
    {
        $view = $this->tracking->view($token);
        if (! $view || $view['ended']) {
            return response()->view('trips.track-gone', ['company' => $view['company'] ?? null], 410)->header('X-Robots-Tag', 'noindex');
        }

        return response()->view('trips.track', $view)->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'private, no-store');
    }

    /** From the trip page: the LR's link, made once. */
    public function share(Trip $trip, Consignment $consignment): RedirectResponse
    {
        abort_unless((int) $consignment->trip_id === (int) $trip->id, 404);
        $url = $this->tracking->link($consignment);

        return redirect()->to(route('trips.show', $trip) . '#loads')->with('track', ['lr' => $consignment->lr_number, 'url' => $url, 'message' => $this->tracking->message($consignment, $url)]);
    }
}
