<?php

namespace App\Console\Commands;

use App\Modules\Documents\Services\DocumentReminders;
use App\Platform\Tenancy\Company;
use Illuminate\Console\Command;

/** Daily: email each company the documents that reached a new reminder step. */
class DocumentsRemind extends Command
{
    protected $signature = 'documents:remind';

    protected $description = 'Send document expiry reminders for every company';

    public function handle(DocumentReminders $reminders): int
    {
        $total = 0;
        Company::usable()->orderBy('id')->each(function (Company $company) use ($reminders, &$total) {
            $total += $reminders->runFor($company);
        });

        $this->info("Reminded {$total} documents.");

        return self::SUCCESS;
    }
}
