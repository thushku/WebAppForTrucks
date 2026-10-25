<?php

namespace App\Platform\Signup;

use App\Modules\Fleet\Models\Truck;
use App\Modules\People\Models\ActivationCode;
use App\Modules\People\Models\Person;
use App\Modules\Trips\Models\Trip;
use App\Platform\Tenancy\CompanyContext;

/**
 * A new company's first steps, each ticked from the data (so it does not
 * matter where the owner did it): the company's address, the first truck,
 * the first driver, the first phone signed in, the first trip.
 */
class GettingStarted
{
    public function __construct(private CompanyContext $context) {}

    /** @return array<string, bool> step => done */
    public function steps(): array
    {
        $company = $this->context->company();

        return [
            'company' => filled($company->address_line1) && filled($company->state_code),
            'truck' => Truck::exists(),
            'driver' => Person::where('type', 'driver')->exists(),
            'app' => ActivationCode::whereNotNull('used_at')->exists(),
            'trip' => Trip::exists(),
        ];
    }

    /** @return array{done: int, total: int} */
    public function progress(): array
    {
        $steps = $this->steps();

        return ['done' => count(array_filter($steps)), 'total' => count($steps)];
    }
}
