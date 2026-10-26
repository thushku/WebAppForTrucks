<?php

namespace App\Console\Commands;

use App\Modules\Reports\Services\Digest;
use App\Platform\Tenancy\Company;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Every hour: the morning summaries of the companies where it is now 07:00
 * (M32), daily, and the weekly one on Mondays. Read-only companies (plan
 * ended) get none: nothing new happens there.
 */
class DigestSend extends Command
{
    protected $signature = 'fleet:digest {--company= : One company id only} {--now : Send now, whatever the hour}';

    protected $description = 'Email the morning summary to the people who chose it, at 07:00 company time';

    public function handle(Digest $digest, CompanyContext $context): int
    {
        $sent = 0;
        foreach (Company::usable()->when($this->option('company'), fn ($q, $id) => $q->whereKey($id))->get() as $company) {
            if ($company->isReadOnly()) {
                continue;
            }
            $sent += $context->runAs($company, fn () => $digest->sendDue(CarbonImmutable::now(), (bool) $this->option('now')));
        }
        $this->info("{$sent} summary email(s) sent.");

        return self::SUCCESS;
    }
}
