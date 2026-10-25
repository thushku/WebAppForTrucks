<?php

namespace App\Platform\Settings;

use Illuminate\Support\Facades\DB;

/**
 * The super-admin's own settings (console-wide switches).
 *
 * Read once per request and memoised. Decision D20: pricing, trial days,
 * branding and enabled languages are set here, never fixed in code.
 */
final class PlatformSettings
{
    public const DEFAULTS = [
        // 'always': the picture challenge on every sign-in (the safe default).
        // 'after_failures': only once a computer or an address has failed three times.
        'captcha_mode'      => 'always',
        'trial_days'        => null,           // null = config('platform.trial_days')
        'brand_name'        => null,           // null = config('app.name')
        'support_email'     => null,
        'support_phone'     => null,
        'support_whatsapp'  => null,
        'enabled_languages' => ['en', 'hi'],
        'announcement'      => null,           // shown on every company's screens and in the apps (console → System)
    ];

    private ?array $values = null;

    public function get(string $key): mixed
    {
        $this->values ??= DB::table('platform_settings')->pluck('value', 'key')
            ->map(fn ($v) => json_decode((string) $v, true))->all();

        return $this->values[$key] ?? self::DEFAULTS[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        DB::table('platform_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()],
        );
        $this->values = null;
    }

    public function brandName(): string
    {
        return (string) ($this->get('brand_name') ?: config('app.name'));
    }

    public function trialDays(): int
    {
        return max(1, (int) ($this->get('trial_days') ?: config('platform.trial_days', 14)));
    }
}
