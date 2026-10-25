<?php

namespace App\Console\Commands;

use App\Platform\Console\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Create (or reset) a super-admin, from the server only. There is no screen
 * that makes the first admin: whoever can reach the console must already be
 * one, and whoever has the server can make one.
 */
class PlatformAdmin extends Command
{
    protected $signature = 'platform:admin {email} {--name=Platform admin}';

    protected $description = 'Create a super-admin console login, or set a new password for one';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('That is not an email address.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Password (at least 12 characters)');

        if (mb_strlen($password) < 12) {
            $this->error('The password must be at least 12 characters.');

            return self::FAILURE;
        }

        $admin = Admin::firstOrNew(['email' => $email]);
        $admin->name ??= (string) $this->option('name');
        $admin->forceFill([
            'password_hash'  => Hash::make($password),
            'status'         => 'active',
            'failed_attempts' => 0, 'lock_level' => 0, 'locked_until' => null, 'locked_hard_at' => null,
        ])->save();

        $this->info(($admin->wasRecentlyCreated ? 'Created' : 'Updated') . " console login {$email}. Sign in at /" . config('platform.path') . '/login');

        return self::SUCCESS;
    }
}
