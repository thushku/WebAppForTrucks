<?php

namespace App\Platform\Auth;

use App\Platform\Settings\PlatformSettings;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Cache;

/**
 * The sign-in picture challenge (ported from Clinic Ledger).
 *
 * Built in, with no outside service, so nobody's browser is sent to a third
 * party. It is on every sign-in unless the provider chooses, in the
 * console, to show it only after failures — from one computer, or against
 * one address.
 *
 * It sits IN FRONT of the account lock, not instead of it. The lock stops a
 * patient guesser on one account; the picture stops a script trying many
 * passwords or many accounts from one place, before a password is checked.
 */
class Captcha
{
    /** No 0/O, 1/I/L: a person must be able to read it at a glance. */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const LENGTH = 5;

    public const WINDOW_MINUTES = 15;

    public const VALID_MINUTES = 10;

    public const DEFAULT_AFTER = 3;

    private const SESSION_KEY = 'captcha';

    // ---- When to ask ------------------------------------------------------

    /** @param int|null $after failures before it appears; 0 = always; null = as the provider has set */
    public function required(?string $ip, ?string $email, ?int $after = null): bool
    {
        $after ??= $this->threshold();

        if ($after <= 0) {
            return true;
        }

        return $this->count($this->ipKey($ip)) >= $after
            || ($email && $this->count($this->accountKey($email)) >= $after);
    }

    /** Failures before the picture appears: none at all when the provider wants it every time (the default). */
    public function threshold(): int
    {
        $mode = config('platform.captcha') ?: app(PlatformSettings::class)->get('captcha_mode');

        return $mode === 'after_failures' ? self::DEFAULT_AFTER : 0;
    }

    public function failed(?string $ip, ?string $email): void
    {
        foreach (array_filter([$this->ipKey($ip), $email ? $this->accountKey($email) : null]) as $key) {
            Cache::put($key, $this->count($key) + 1, now()->addMinutes(self::WINDOW_MINUTES));
        }
    }

    /** A successful sign-in wipes the slate for that computer and address. */
    public function clear(?string $ip, ?string $email): void
    {
        Cache::forget($this->ipKey($ip));

        if ($email) {
            Cache::forget($this->accountKey($email));
        }
    }

    // ---- The picture ------------------------------------------------------

    public function issue(Session $session): string
    {
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        // Only a hash is kept, so a session file read off a disk does not
        // hand over the answer.
        $session->put(self::SESSION_KEY, [
            'hash'   => hash_hmac('sha256', $code, (string) config('app.key')),
            'issued' => time(),
        ]);

        return $code;
    }

    /** One try per picture: right or wrong, the picture is spent. */
    public function passes(Session $session, ?string $answer): bool
    {
        $held = $session->pull(self::SESSION_KEY);

        if (! is_array($held) || ! $answer) {
            return false;
        }

        if (time() - (int) ($held['issued'] ?? 0) > self::VALID_MINUTES * 60) {
            return false;
        }

        $given = strtoupper(preg_replace('/\s+/', '', $answer));

        return hash_equals((string) $held['hash'], hash_hmac('sha256', $given, (string) config('app.key')));
    }

    /** Draw the code as a PNG with GD's built-in font — no font file needed. */
    public function png(string $code): string
    {
        $w = 190;
        $h = 64;
        $img = imagecreatetruecolor($w, $h);

        $ground = imagecolorallocate($img, 244, 250, 249);
        imagefilledrectangle($img, 0, 0, $w, $h, $ground);

        for ($i = 0; $i < 7; $i++) {
            $c = imagecolorallocate($img, random_int(160, 205), random_int(200, 230), random_int(200, 230));
            imagesetthickness($img, random_int(1, 2));
            imageline($img, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $c);
        }

        for ($i = 0; $i < 220; $i++) {
            $c = imagecolorallocate($img, random_int(150, 220), random_int(170, 230), random_int(170, 230));
            imagesetpixel($img, random_int(0, $w - 1), random_int(0, $h - 1), $c);
        }

        $x = 14;

        foreach (str_split($code) as $char) {
            $cell = imagecreatetruecolor(12, 16);
            $clear = imagecolorallocatealpha($cell, 0, 0, 0, 127);
            imagealphablending($cell, false);
            imagefilledrectangle($cell, 0, 0, 12, 16, $clear);
            imagesavealpha($cell, true);
            $ink = imagecolorallocate($cell, random_int(10, 40), random_int(80, 120), random_int(90, 120));
            imagestring($cell, 5, 2, 0, $char, $ink);

            $big = imagecreatetruecolor(36, 48);
            imagealphablending($big, false);
            imagefilledrectangle($big, 0, 0, 36, 48, imagecolorallocatealpha($big, 0, 0, 0, 127));
            imagesavealpha($big, true);
            imagecopyresampled($big, $cell, 0, 0, 0, 0, 36, 48, 12, 16);

            $turned = imagerotate($big, random_int(-18, 18), imagecolorallocatealpha($big, 0, 0, 0, 127));
            imagealphablending($img, true);
            imagecopy($img, $turned, $x, random_int(2, 12), 0, 0, imagesx($turned), imagesy($turned));

            $x += random_int(30, 34);
        }

        for ($i = 0; $i < 2; $i++) {
            $c = imagecolorallocate($img, random_int(60, 110), random_int(140, 180), random_int(150, 190));
            imagesetthickness($img, 1);
            imageline($img, 0, random_int(10, $h - 10), $w, random_int(10, $h - 10), $c);
        }

        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    // ---- Keys -------------------------------------------------------------

    private function count(string $key): int
    {
        return (int) Cache::get($key, 0);
    }

    private function ipKey(?string $ip): string
    {
        return 'captcha:ip:' . sha1((string) $ip);
    }

    private function accountKey(string $email): string
    {
        return 'captcha:acct:' . sha1(Identifier::normalise($email));
    }
}
