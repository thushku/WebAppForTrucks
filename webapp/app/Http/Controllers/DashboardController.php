<?php

namespace App\Http\Controllers;

use App\Modules\Reports\Services\Dashboard;
use App\Modules\Reports\Services\LiveMap;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The owner's first screen (blueprint 04 §3.1): trucks, trips and entries to
 * check for everyone who opens it; the money tiles, the chart and the work
 * queues only for those who may see money.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, CompanyContext $context, Dashboard $dashboard, LiveMap $map): View
    {
        $user = $request->user();
        $money = $user->canAny(['report.view', 'accounts.view', 'ledger.view']);
        $positions = $map->positions();

        return view('dashboard', [
            'company' => $context->company(),
            'counts' => $dashboard->counts(),
            'alerts' => $dashboard->alerts($positions),
            'positions' => $positions,
            'money' => $money,
            'profit' => $money ? $dashboard->profit() : null,
            'months' => $money ? $dashboard->months() : [],
            'spending' => $user->can('trip.view') ? $dashboard->spendingToday() : [],
            'queues' => $user->canAny(['invoice.view', 'lr.view']) ? $dashboard->queues() : null,
            'showMap' => $user->can('map.view'),
        ]);
    }
}
