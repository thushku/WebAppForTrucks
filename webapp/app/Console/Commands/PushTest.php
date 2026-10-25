<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\CompanyUser;
use App\Platform\Devices\Device;
use App\Platform\Notifications\Notifier;
use App\Platform\Notifications\UserNotification;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Console\Command;

/**
 * Sends a test notice to one person's phones (M20) — to check the Firebase
 * set-up and the phone app's handling: `php artisan fleet:push +919847100001`.
 */
class PushTest extends Command
{
    protected $signature = 'fleet:push {who : mobile or email} {--title=Test notice from Fleet} {--body=If you can read this, push works.}';

    protected $description = 'Send a test push notification to a person\'s signed-in phones';

    public function handle(Notifier $notifier, CompanyContext $context): int
    {
        $who = (string) $this->argument('who');
        $account = Account::where('email', $who)->orWhere('mobile', $who)->first();
        $member = $account ? CompanyUser::with('company')->where('account_id', $account->id)->where('status', 'active')->orderBy('id')->first() : null;
        if (! $member) {
            $this->error("Nobody active found for {$who}.");

            return self::FAILURE;
        }
        $phones = Device::where('account_id', $account->id)->whereNull('revoked_at')->whereNotNull('push_token')->count();
        $this->line("{$account->name} at {$member->company->name}: {$phones} phone(s) with a push token; driver " . config('services.fcm.driver') . '.');

        $result = $context->runAs($member->company, function () use ($notifier, $account) {
            $notifier->tell([$account->id], 'test', (string) $this->option('title'), (string) $this->option('body'), null, ['type' => 'test']);

            return UserNotification::for($account)->latest('id')->value('push_result');
        });
        $this->info("Push: {$result}" . ($result === 'waiting' ? ' (queued — the queue worker sends it)' : ''));

        return self::SUCCESS;
    }
}
