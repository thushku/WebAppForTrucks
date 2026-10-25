<?php

namespace App\Console\Commands;

use App\Platform\Billing\Billing;
use Illuminate\Console\Command;

/**
 * Every night: subscriptions past their end date end, and companies whose
 * trial or subscription has run out turn read-only (their data stays; they
 * renew from Settings → Plan).
 */
class SubscriptionsSweep extends Command
{
    protected $signature = 'fleet:subscriptions';

    protected $description = 'End past subscriptions and make companies whose trial or plan has run out read-only';

    public function handle(Billing $billing): int
    {
        $this->info($billing->sweep() . ' company(ies) turned read-only.');

        return self::SUCCESS;
    }
}
