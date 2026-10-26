<?php

namespace App\Console\Commands;

use App\Modules\Road\Services\GpsDistance;
use App\Modules\Trips\Models\Trip;
use App\Platform\Tenancy\Company;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Console\Command;

/**
 * Every night: the trips that ended in the last three days measured again by
 * GPS (M34) — a phone that was offline sends its points late.
 */
class GpsCheck extends Command
{
    protected $signature = 'fleet:gps-check {--days=3 : Trips ended in this many days} {--company= : One company id only}';

    protected $description = 'Compare recent trips\' odometer km with their GPS km';

    public function handle(GpsDistance $gps, CompanyContext $context): int
    {
        $checked = 0;
        $differ = 0;
        foreach (Company::usable()->when($this->option('company'), fn ($q, $id) => $q->whereKey($id))->get() as $company) {
            $context->runAs($company, function () use ($gps, &$checked, &$differ) {
                Trip::whereNotNull('ended_at')->where('ended_at', '>=', now()->subDays((int) $this->option('days')))->whereNotIn('status', ['cancelled'])
                    ->each(function (Trip $trip) use ($gps, &$checked, &$differ) {
                        $checked++;
                        $differ += (int) ($gps->check($trip)->km_check === 'differs');
                    });
            });
        }
        $this->info("{$checked} trip(s) checked, {$differ} with the odometer and GPS apart.");

        return self::SUCCESS;
    }
}
