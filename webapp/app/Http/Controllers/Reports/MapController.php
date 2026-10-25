<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Modules\Reports\Services\LiveMap;
use App\Modules\Trips\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * The live map (blueprint 04 §2): every running trip where its crew's phone
 * last was, refreshed every minute; and a trip's route for the replay on the
 * trip page. The map draws in the browser (Leaflet, decision D18); these
 * only hand it the points.
 */
class MapController extends Controller
{
    public function index(LiveMap $map): View
    {
        return view('map.index', ['positions' => $map->positions()]);
    }

    public function positions(LiveMap $map): JsonResponse
    {
        return response()->json(['positions' => $map->positions(), 'at' => now()->toIso8601String()]);
    }

    public function route(Trip $trip, LiveMap $map): JsonResponse
    {
        return response()->json($map->route($trip));
    }
}
