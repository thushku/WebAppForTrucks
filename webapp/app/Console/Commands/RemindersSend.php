<?php

namespace App\Console\Commands;

use App\Modules\Invoicing\Services\Reminders;
use App\Platform\Tenancy\Company;
use Illuminate\Console\Command;

/** Every morning: overdue reminders by email, for the companies that switched them on (Settings → Messages). */
class RemindersSend extends Command
{
    protected $signature = 'fleet:reminders {--company= : One company id only}';

    protected $description = 'Email clients a reminder for bills past their due date, on the company\'s reminder days';

    public function handle(Reminders $reminders): int
    {
        $sent = 0;
        $failed = 0;
        foreach (Company::usable()->when($this->option('company'), fn ($q, $id) => $q->whereKey($id))->get() as $company) {
            $done = $reminders->run($company);
            $sent += $done['sent'];
            $failed += $done['failed'];
        }
        $this->info("{$sent} reminder(s) sent, {$failed} not sent.");

        return self::SUCCESS;
    }
}
