<?php

namespace App\Platform\Devices;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The phone's key to the API (Sanctum, decision D18): one token per phone,
 * valid 90 days and renewed by the app while it is in use, so a phone left
 * in a drawer stops working on its own. Signing in again on the same phone
 * replaces its old token; the office can switch a phone off (Settings →
 * Team → devices, later) and its token goes with it.
 */
class DeviceTokens
{
    public const TOKEN_DAYS = 90;

    /**
     * @param array{device_id: string, platform: string, model?: ?string, app_version?: ?string} $info
     * @return array{token: string, expires_at: string, device: Device}
     */
    public function issue(Account $account, array $info, string $kind): array
    {
        return DB::transaction(function () use ($account, $info, $kind) {
            $device = Device::firstOrNew(['account_id' => $account->id, 'device_id' => $info['device_id']]);
            if ($device->token_id) {
                PersonalAccessToken::whereKey($device->token_id)->delete();
            }

            $expires = now()->addDays(self::TOKEN_DAYS);
            $new     = $account->createToken("{$kind}:" . mb_substr($info['device_id'], 0, 60), ["app:{$kind}"], $expires);

            $device->forceFill([
                'platform' => $info['platform'], 'model' => $info['model'] ?? null, 'app_version' => $info['app_version'] ?? null,
                'app_kind' => $kind, 'token_id' => $new->accessToken->id, 'activated_at' => now(), 'last_seen_at' => now(), 'revoked_at' => null,
            ])->save();

            return ['token' => $new->plainTextToken, 'expires_at' => $expires->toIso8601String(), 'device' => $device];
        });
    }

    /** A fresh token for the same phone; the one in use stops working. */
    public function refresh(Request $request): array
    {
        $current = $request->user()->currentAccessToken();
        $device  = $this->device($request);
        $kind    = $device?->app_kind ?? 'crew';

        return $this->issue($request->user(), [
            'device_id' => $device?->device_id ?? (string) str($current->name)->after(':'),
            'platform' => $device?->platform ?? 'android', 'model' => $device?->model, 'app_version' => $request->input('app_version', $device?->app_version),
        ], $kind);
    }

    public function revoke(Request $request): void
    {
        $this->device($request)?->forceFill(['revoked_at' => now(), 'token_id' => null, 'push_token' => null])->save();
        $request->user()->currentAccessToken()?->delete();
    }

    /** The phone this request comes from (by its token). */
    public function device(Request $request): ?Device
    {
        $token = $request->user()?->currentAccessToken();

        return $token instanceof PersonalAccessToken ? Device::where('token_id', $token->id)->first() : null;
    }

    /** Called on every sync: when each phone was last heard from (the office sees silent phones). */
    public function seen(Request $request, array $fields = []): void
    {
        $this->device($request)?->forceFill(['last_seen_at' => now()] + $fields)->save();
    }
}
