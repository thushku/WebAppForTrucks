<?php

namespace App\Platform\Auth;

use App\Models\Account;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Auth\Models\TrustedBrowser;
use Illuminate\Support\Str;

/**
 * "Trust this computer for 30 days" (ported from Clinic Ledger). Ticked on
 * the code screen, it gives the browser a random token in a cookie and keeps
 * only the token's hash; within the 30 days that browser is not asked for a
 * code. A new password forgets every trusted computer.
 */
class TrustedBrowsers
{
    public const COOKIE = 'fl_trust';

    public const DAYS = 30;

    public function __construct(private AuditRecorder $audit) {}

    public function trust(Account $account, ?string $userAgent, ?string $ip): string
    {
        $token = Str::random(64);

        TrustedBrowser::create([
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'user_agent' => mb_substr((string) $userAgent, 0, 255) ?: null,
            'ip'         => $ip,
            'expires_at' => now()->addDays(self::DAYS),
        ]);

        $this->audit->record('security.browser_trusted', "{$account->name} trusted a computer for " . self::DAYS . ' days', 'account', (string) $account->id, $account->name);

        return $token;
    }

    public function recognises(Account $account, ?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $row = TrustedBrowser::live()->where('account_id', $account->id)->where('token_hash', hash('sha256', $token))->first();
        $row?->forceFill(['last_used_at' => now()])->save();

        return $row !== null;
    }

    public function forgetAll(Account $account, string $why): int
    {
        $gone = TrustedBrowser::where('account_id', $account->id)->delete();

        if ($gone > 0) {
            $this->audit->record('security.browsers_forgotten', "{$account->name}'s trusted computers forgotten ({$why})", 'account', (string) $account->id, $account->name);
        }

        return $gone;
    }
}
