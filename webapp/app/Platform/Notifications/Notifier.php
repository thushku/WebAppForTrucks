<?php

namespace App\Platform\Notifications;

use App\Models\CompanyUser;
use App\Modules\People\Models\Person;
use App\Platform\Authorization\PermissionResolver;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tells people something (M20): one inbox row each, in the company in
 * context, and a push to their phones once the change is saved. News of
 * one sort with the same collapse key (say "entries waiting for approval")
 * updates the unread row instead of adding one, and pushes again only after
 * PUSH_AGAIN_AFTER minutes — a busy day is one notice, not fifty.
 */
class Notifier
{
    public const PUSH_AGAIN_AFTER = 30;

    public function __construct(private CompanyContext $context, private PermissionResolver $permissions, private NotificationSettings $settings) {}

    /**
     * @param iterable<int> $accountIds
     * @return int how many were told
     */
    public function tell(iterable $accountIds, string $kind, string $title, ?string $body = null, ?string $url = null, array $data = [], ?string $collapse = null, ?int $except = null, ?string $priority = null): int
    {
        $ids = collect($accountIds)->map(fn ($id) => (int) $id)->filter()->unique()->reject(fn ($id) => $id === $except)->values();
        $priority ??= NotificationSettings::priorityOf($kind);
        $now = \Carbon\CarbonImmutable::now($this->context->company()->timezone ?: 'UTC');
        $members = CompanyUser::where('company_id', $this->context->id())->whereIn('account_id', $ids)->get()->keyBy('account_id');
        foreach ($ids as $accountId) {
            $fields = ['title' => mb_substr($title, 0, 150), 'body' => $body !== null ? mb_substr($body, 0, 500) : null, 'url' => $url ? mb_substr($url, 0, 255) : null, 'data' => $data ?: null, 'priority' => $priority];
            // The person's own choices (M26): a muted group, or quiet hours for all but high notices — the inbox keeps it all the same.
            $held = $this->settings->holdBack($members[$accountId] ?? null, $kind, $priority, $now);
            $row = $collapse ? UserNotification::for($accountId)->where('kind', $kind)->where('collapse_key', $collapse)->whereNull('read_at')->latest('id')->first() : null;
            if ($row) {
                $again = $held === null && (! $row->pushed_at || $row->pushed_at->lt(now()->subMinutes(self::PUSH_AGAIN_AFTER)));
                $row->forceFill($fields + ['times' => $row->times + 1, 'push_result' => $held ?? ($again ? 'waiting' : 'held')])->save();
            } else {
                $row = UserNotification::create($fields + ['account_id' => $accountId, 'kind' => $kind, 'collapse_key' => $collapse, 'push_result' => $held ?? 'waiting']);
                $again = $held === null;
            }
            if ($again) {
                $companyId = $row->company_id;
                $id = $row->id;
                DB::afterCommit(fn () => SendPush::dispatch($companyId, $id));
            }
        }

        return $ids->count();
    }

    /** The sign-ins of these crew members (people who have activated the app). */
    public function accountsOf(iterable $personIds): Collection
    {
        $memberIds = Person::whereIn('id', collect($personIds)->filter()->unique()->all())->whereNotNull('company_user_id')->pluck('company_user_id');

        return CompanyUser::whereIn('id', $memberIds)->where('status', 'active')->pluck('account_id');
    }

    /** Everyone active in the company who holds this permission (the owner always does). */
    public function accountsWith(string $permission): Collection
    {
        return CompanyUser::where('company_id', $this->context->id())->where('status', 'active')->get()
            ->filter(fn (CompanyUser $member) => $this->permissions->has($member, $permission))->pluck('account_id')->values();
    }
}
