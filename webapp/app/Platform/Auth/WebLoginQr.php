<?php

namespace App\Platform\Auth;

use App\Models\Account;
use App\Models\CompanyUser;
use App\Platform\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Sign in with your phone" (decision P7, like WhatsApp Web).
 *
 *  1. The sign-in page asks for a code: it gets a QR token (shown as a QR
 *     code) and a separate poll secret, which only that browser holds — in
 *     its page and its session.
 *  2. The owner app, already signed in on the phone, scans the QR and calls
 *     the API to approve it for the company it is working in.
 *  3. The page asks every two seconds; once approved, that browser (and only
 *     that one) is signed in, once.
 *
 * Only hashes are stored. A code lives two minutes.
 */
class WebLoginQr
{
    public const LIFETIME_SECONDS = 120;

    public function __construct(private AuditRecorder $audit) {}

    /** @return array{token: string, poll: string, expires_at: \Illuminate\Support\Carbon} */
    public function create(?string $ip, ?string $userAgent): array
    {
        $token = Str::random(40);
        $poll  = Str::random(40);
        $expiresAt = now()->addSeconds(self::LIFETIME_SECONDS);

        DB::table('web_login_qr')->insert([
            'token_hash' => hash('sha256', $token),
            'poll_hash'  => hash('sha256', $poll),
            'expires_at' => $expiresAt,
            'ip'         => $ip,
            'user_agent' => mb_substr((string) $userAgent, 0, 255),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['token' => $token, 'poll' => $poll, 'expires_at' => $expiresAt];
    }

    /**
     * The phone approves a code for one of its companies.
     *
     * @return string 'approved' | 'expired' | 'used' | 'unknown'
     */
    public function approve(string $token, Account $account, CompanyUser $member): string
    {
        return DB::transaction(function () use ($token, $account, $member) {
            $row = DB::table('web_login_qr')->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();

            if (! $row) {
                return 'unknown';
            }
            if ($row->approved_at !== null || $row->consumed_at !== null) {
                return 'used';
            }
            if (now()->gte($row->expires_at)) {
                return 'expired';
            }

            DB::table('web_login_qr')->where('id', $row->id)->update([
                'approved_account_id' => $account->id,
                'approved_company_id' => $member->company_id,
                'approved_at'         => now(),
                'updated_at'          => now(),
            ]);

            $this->audit->record('login.qr_approved', "{$account->name} approved a web sign-in from the phone (browser at {$row->ip})", 'account', (string) $account->id, $account->name, companyId: $member->company_id, severity: 'notable');

            return 'approved';
        });
    }

    /**
     * The waiting browser asks. Returns the account and company once, after
     * approval; null while waiting; false once the code is dead.
     *
     * @return array{account: Account, company_id: int}|null|false
     */
    public function collect(string $poll): array|null|false
    {
        return DB::transaction(function () use ($poll) {
            $row = DB::table('web_login_qr')->where('poll_hash', hash('sha256', $poll))->lockForUpdate()->first();

            if (! $row || $row->consumed_at !== null) {
                return false;
            }
            if ($row->approved_at === null) {
                return now()->gte($row->expires_at) ? false : null;
            }

            DB::table('web_login_qr')->where('id', $row->id)->update(['consumed_at' => now(), 'updated_at' => now()]);

            $account = Account::find($row->approved_account_id);
            $member  = $account ? CompanyUser::where('account_id', $account->id)->where('company_id', $row->approved_company_id)->where('status', 'active')->first() : null;

            return $account && $member && $account->canLogIn() ? ['account' => $account, 'company_id' => (int) $row->approved_company_id] : false;
        });
    }

    public function find(string $token): ?object
    {
        return DB::table('web_login_qr')->where('token_hash', hash('sha256', $token))->first();
    }

    /** "Chrome on Windows" from a user-agent string, for the phone to show. */
    public static function describeBrowser(string $userAgent): string
    {
        $browser = match (true) {
            str_contains($userAgent, 'Edg/')     => 'Edge',
            str_contains($userAgent, 'OPR/')     => 'Opera',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/')  => 'Chrome',
            str_contains($userAgent, 'Safari/')  => 'Safari',
            default                              => 'A browser',
        };
        $system = match (true) {
            str_contains($userAgent, 'Windows')   => 'Windows',
            str_contains($userAgent, 'Android')   => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Mac OS X')  => 'macOS',
            str_contains($userAgent, 'Linux')     => 'Linux',
            default                               => null,
        };

        return $system ? "{$browser} on {$system}" : $browser;
    }

    /** Old codes are removed by the scheduler. */
    public function prune(): int
    {
        return DB::table('web_login_qr')->where('expires_at', '<', now()->subDay())->delete();
    }
}
