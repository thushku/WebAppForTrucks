<?php

namespace App\Platform\Notifications;

use App\Models\CompanyUser;
use Carbon\CarbonImmutable;

/**
 * What a person wants pushed to their phone, per company (M26, 13-parity
 * #15, #29). Every notice has a kind in a group and a priority; a person
 * can switch a group's pushes off (the inbox still keeps them) and set
 * quiet hours when only high-priority notices push.
 */
class NotificationSettings
{
    /** group => [name, what it covers] */
    public const GROUPS = [
        'trips' => ['Trips', 'A trip sent to you or called off'],
        'entries' => ['My entries', 'A question on or rejection of your entry, a second approval asked of you'],
        'approvals' => ['Entries to approve', 'Crew entries waiting for your approval'],
        'incidents' => ['Incidents', 'An accident, breakdown or police case on the road'],
        'papers' => ['Papers', 'Insurance, permits, PUC and licences running out'],
        'money' => ['Pay', 'Your pay paid, and fines that come off it'],
        'quotations' => ['Quotations', 'A client accepting or declining a quotation'],
        'leave' => ['Leave', 'Leave asked for, and your leave approved or not'],
        'digest' => ['Morning summary', 'Yesterday’s trips and money, when you get the summary'],
    ];

    /** kind => [group, default priority] */
    public const KINDS = [
        'trip.sent' => ['trips', 'high'], 'trip.cancelled' => ['trips', 'high'],
        'entry.second' => ['entries', 'high'], 'entry.queried' => ['entries', 'medium'], 'entry.rejected' => ['entries', 'medium'],
        'approvals.waiting' => ['approvals', 'medium'], 'road.incident' => ['incidents', 'high'], 'papers.due' => ['papers', 'medium'],
        'pay.paid' => ['money', 'low'], 'fine.recover' => ['money', 'medium'], 'quotation.accepted' => ['quotations', 'medium'], 'quotation.declined' => ['quotations', 'medium'],
        'leave.asked' => ['leave', 'medium'], 'leave.decided' => ['leave', 'medium'],
        'digest.ready' => ['digest', 'low'],
        'test' => [null, 'low'],
    ];

    public const PRIORITIES = ['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'];

    /** The morning summary by email (M32): owners get the daily one unless they say otherwise. */
    public const DIGESTS = ['daily' => 'Every morning (yesterday)', 'weekly' => 'Monday mornings (last week)', 'off' => 'Off'];

    public static function priorityOf(string $kind): string
    {
        return self::KINDS[$kind][1] ?? 'medium';
    }

    public static function groupOf(string $kind): ?string
    {
        return self::KINDS[$kind][0] ?? null;
    }

    /** @return array{muted: string[], quiet_from: ?string, quiet_to: ?string, digest: string} */
    public function of(?CompanyUser $member): array
    {
        $s = $member?->notification_settings ?? [];

        return ['muted' => array_values(array_intersect($s['muted'] ?? [], array_keys(self::GROUPS))), 'quiet_from' => $s['quiet_from'] ?? null, 'quiet_to' => $s['quiet_to'] ?? null,
            'digest' => isset(self::DIGESTS[$s['digest'] ?? '']) ? $s['digest'] : $this->defaultDigest($member)];
    }

    public function save(CompanyUser $member, array $muted, ?string $quietFrom, ?string $quietTo, ?string $digest = null): void
    {
        $before = $member->notification_settings ?? [];
        $member->forceFill(['notification_settings' => [
            'muted' => array_values(array_intersect($muted, array_keys(self::GROUPS))),
            'quiet_from' => $quietFrom && $quietTo ? $quietFrom : null, 'quiet_to' => $quietFrom && $quietTo ? $quietTo : null,
        ] + (isset(self::DIGESTS[(string) $digest]) ? ['digest' => $digest] : (isset($before['digest']) ? ['digest' => $before['digest']] : []))])->save();
    }

    /** Owners get the daily summary; everyone else chooses it. */
    private function defaultDigest(?CompanyUser $member): string
    {
        return $member && app(\App\Platform\Authorization\PermissionResolver::class)->levelFor($member) === 0 ? 'daily' : 'off';
    }

    /**
     * Whether this notice goes to the phone now: not for a muted group; in
     * quiet hours only a high one. null = push; else why not ("off", "quiet").
     */
    public function holdBack(?CompanyUser $member, string $kind, string $priority, CarbonImmutable $now): ?string
    {
        $s = $this->of($member);
        $group = self::groupOf($kind);
        if ($group !== null && in_array($group, $s['muted'], true)) {
            return 'off';
        }
        if ($priority !== 'high' && $s['quiet_from'] && $s['quiet_to'] && self::within($now->format('H:i'), $s['quiet_from'], $s['quiet_to'])) {
            return 'quiet';
        }

        return null;
    }

    /** "23:30" within 22:00–06:00 (across midnight) or 13:00–14:00. */
    public static function within(string $time, string $from, string $to): bool
    {
        return $from <= $to ? $time >= $from && $time < $to : $time >= $from || $time < $to;
    }
}
