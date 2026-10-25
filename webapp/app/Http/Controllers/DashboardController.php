<?php

namespace App\Http\Controllers;

use App\Platform\Tenancy\CompanyContext;
use Illuminate\View\View;

/**
 * The owner's first screen. Tiles fill in as the modules arrive (trips,
 * approvals, money, documents); for now it greets and shows the trial.
 */
class DashboardController extends Controller
{
    public function __invoke(CompanyContext $context): View
    {
        return view('dashboard', ['company' => $context->company()]);
    }
}
