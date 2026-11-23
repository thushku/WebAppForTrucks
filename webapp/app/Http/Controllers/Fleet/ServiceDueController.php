<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Fleet\Services\Maintenance;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Trucks → Service due (M50): every service overdue or expected within the
 * days chosen, across the fleet, with the date it is likely from each
 * truck's pace — to plan the workshop between trips. Our own and leased
 * trucks; an attached truck's servicing is its owner's.
 */
class ServiceDueController extends Controller
{
    public const WINDOWS = [7, 30, 60, 90];

    public function index(Request $request, Maintenance $maintenance): View
    {
        $days = in_array($request->integer('days'), self::WINDOWS, true) ? $request->integer('days') : Maintenance::SOON_DAYS;
        $trucks = Truck::inBranch()->where('ownership', '!=', 'attached')->whereNotIn('status', ['sold'])->get(['id', 'reg_no', 'odometer_km', 'status', 'ownership']);
        $rows = $maintenance->due($trucks, $days);

        return view('trucks.service-due', [
            'rows' => $rows, 'days' => $days,
            'without' => $trucks->whereNotIn('id', \App\Modules\Fleet\Models\ServiceSchedule::where('active', true)->distinct()->pluck('truck_id'))->values(),
        ]);
    }
}
