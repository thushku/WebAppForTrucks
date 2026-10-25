<?php

namespace App\Console\Commands;

use App\Platform\Data\CompanyDeletion;
use Illuminate\Console\Command;

/** Every hour: companies whose 72-hour deletion countdown has run out are removed; law copies past their date go too. */
class CompanyDeletionSweep extends Command
{
    protected $signature = 'fleet:deletions';

    protected $description = 'Delete companies whose deletion countdown has ended, and law copies past their retention date';

    public function handle(CompanyDeletion $deletion): int
    {
        $done = $deletion->sweep();
        $this->info("{$done['deleted']} company(ies) deleted; {$done['expired']} law copy(ies) removed.");

        return self::SUCCESS;
    }
}
