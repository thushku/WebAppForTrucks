<?php

namespace App\Console\Commands;

use App\Platform\Console\Admin;
use Illuminate\Console\Command;

/** Open a locked console login — only from the server, never from the console itself. */
class PlatformUnlock extends Command
{
    protected $signature = 'platform:unlock {email}';

    protected $description = 'Unlock a locked super-admin console login';

    public function handle(): int
    {
        $admin = Admin::where('email', mb_strtolower(trim((string) $this->argument('email'))))->first();

        if (! $admin) {
            $this->error('No console login with that address.');

            return self::FAILURE;
        }

        $admin->forceFill(['failed_attempts' => 0, 'lock_level' => 0, 'locked_until' => null, 'locked_hard_at' => null])->save();
        $this->info("Unlocked {$admin->email}.");

        return self::SUCCESS;
    }
}
