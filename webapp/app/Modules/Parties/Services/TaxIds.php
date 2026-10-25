<?php

namespace App\Modules\Parties\Services;

use App\Support\Regions;

/**
 * Checks tax numbers before they are saved, so a typo is caught at the desk
 * and not by the client's accounts team a month later.
 *
 *  · GSTIN (India): 2-digit state code + PAN + entity number + "Z" + a check
 *    character (GSTN's base-36 checksum).
 *  · PAN (India): 5 letters, 4 digits, 1 letter; the 4th letter is the holder
 *    type (P person, C company, F firm, H HUF…).
 *  · TRN (UAE): 15 digits starting with 100.
 */
final class TaxIds
{
    private const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public static function normalise(?string $value): string
    {
        return strtoupper((string) preg_replace('/[\s\-.]/', '', (string) $value));
    }

    public static function isGstin(string $gstin): bool
    {
        $gstin = self::normalise($gstin);

        if (! preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gstin)) {
            return false;
        }

        // The state code must be a real one (Regions::IN, plus 99 for the centre).
        if (! isset(Regions::IN[substr($gstin, 0, 2)]) && substr($gstin, 0, 2) !== '99') {
            return false;
        }

        return $gstin[14] === self::gstinCheckCharacter(substr($gstin, 0, 14));
    }

    /** The 15th character of a GSTIN from its first 14. */
    public static function gstinCheckCharacter(string $first14): string
    {
        $sum = 0;
        foreach (str_split($first14) as $i => $char) {
            $product = strpos(self::ALPHABET, $char) * ($i % 2 === 0 ? 1 : 2);
            $sum    += intdiv($product, 36) + $product % 36;
        }

        return self::ALPHABET[(36 - $sum % 36) % 36];
    }

    /** The state a GSTIN is registered in ("32" → Kerala). */
    public static function gstinState(string $gstin): string
    {
        return substr(self::normalise($gstin), 0, 2);
    }

    /** The PAN inside a GSTIN. */
    public static function gstinPan(string $gstin): string
    {
        return substr(self::normalise($gstin), 2, 10);
    }

    public static function isPan(string $pan): bool
    {
        return (bool) preg_match('/^[A-Z]{3}[ABCFGHJLPTK][A-Z][0-9]{4}[A-Z]$/', self::normalise($pan));
    }

    public static function isTrn(string $trn): bool
    {
        return (bool) preg_match('/^100[0-9]{12}$/', self::normalise($trn));
    }

    /** Valid for the country? (Countries without a check yet accept any 5–20 letters and digits.) */
    public static function isValid(string $country, string $taxId): bool
    {
        return match (strtoupper($country)) {
            'IN'    => self::isGstin($taxId),
            'AE'    => self::isTrn($taxId),
            default => (bool) preg_match('/^[A-Z0-9]{5,20}$/', self::normalise($taxId)),
        };
    }

    /** "GSTIN" or "TRN". */
    public static function label(string $country): string
    {
        return match (strtoupper($country)) {
            'IN'    => 'GSTIN',
            'AE'    => 'TRN',
            default => __('Tax number'),
        };
    }
}
