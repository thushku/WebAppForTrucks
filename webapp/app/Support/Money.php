<?php

namespace App\Support;

use NumberFormatter;

/**
 * Money on screen. Amounts are stored as decimals and formatted here in the
 * account's number style (₹ 1,23,456.00 · $ 123,456.00 · 123.456,00 €),
 * through ICU when the intl extension is present and by hand otherwise.
 */
final class Money
{
    /** style => [ICU locale, label shown in Settings] */
    public const STYLES = [
        'in' => ['en_IN', '₹ 1,23,456.00'],
        'us' => ['en_US', '$ 123,456.00'],
        'uk' => ['en_GB', '£123,456.00'],
        'eu' => ['de_DE', '123.456,00 €'],
        'fr' => ['fr_FR', '123 456,00 €'],
        'ch' => ['de_CH', 'CHF 123’456.00'],
    ];

    private const SYMBOLS = [
        'INR' => '₹', 'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥', 'CNY' => '¥', 'KRW' => '₩',
        'AUD' => 'A$', 'CAD' => 'C$', 'NZD' => 'NZ$', 'SGD' => 'S$', 'HKD' => 'HK$', 'BRL' => 'R$',
        'ZAR' => 'R', 'TRY' => '₺', 'ILS' => '₪', 'PHP' => '₱', 'NGN' => '₦', 'VND' => '₫', 'THB' => '฿',
    ];

    /** Currencies with no minor unit. */
    private const ZERO_DECIMALS = ['JPY', 'KRW', 'VND', 'CLP'];

    /** Currencies with three decimals (the Gulf's fils and baisa: Bahrain, Kuwait, Oman, and others). */
    private const THREE_DECIMALS = ['BHD', 'KWD', 'OMR', 'JOD', 'TND', 'LYD', 'IQD'];

    /** ICU formatters are costly to build; a list of 200 invoices asks 400 times. */
    private static array $formatters = [];

    public static function format(float|int|string|null $amount, string $currency, string $style = 'us'): string
    {
        $amount   = (float) ($amount ?? 0);
        $currency = strtoupper($currency);
        $decimals = self::decimals($currency);

        if (class_exists(NumberFormatter::class)) {
            $key = ($locale = self::STYLES[$style][0] ?? 'en_US') . ':' . $decimals;
            if (! isset(self::$formatters[$key])) {
                self::$formatters[$key] = new NumberFormatter($locale, NumberFormatter::CURRENCY);
                self::$formatters[$key]->setAttribute(NumberFormatter::FRACTION_DIGITS, $decimals);
            }
            $out = self::$formatters[$key]->formatCurrency($amount, $currency);

            if ($out !== false) {
                // ICU writes "AED 350.00" for currencies with no symbol, and
                // a narrow no-break space before symbols in some locales.
                return str_replace("\u{A0}", ' ', $out);
            }
        }

        return self::fallback($amount, $currency, $style, $decimals);
    }

    /** How many decimals amounts in a currency are kept and rounded to. */
    public static function decimals(string $currency): int
    {
        $currency = strtoupper($currency);

        return in_array($currency, self::ZERO_DECIMALS, true) ? 0 : (in_array($currency, self::THREE_DECIMALS, true) ? 3 : 2);
    }

    /** An amount in the company's own currency and number style (₹ 1,23,456.00 in India). */
    public static function company(float|int|string|null $amount): string
    {
        $company = app(\App\Platform\Tenancy\CompanyContext::class)->company();

        return self::format($amount, $company->currency ?: 'INR', $company->country === 'IN' ? 'in' : 'us');
    }

    /** "₹1,800/hr" style: symbol + number, no trailing zeros when whole. */
    public static function short(float|int|string|null $amount, string $currency, string $style = 'us'): string
    {
        $out = self::format($amount, $currency, $style);

        return preg_replace('/([.,])00$/', '', $out) ?? $out;
    }

    public static function symbol(string $currency): string
    {
        return self::SYMBOLS[strtoupper($currency)] ?? strtoupper($currency) . ' ';
    }

    private static function fallback(float $amount, string $currency, string $style, int $decimals): string
    {
        $negative = $amount < 0;
        $amount   = abs($amount);
        $symbol   = self::symbol($currency);

        [$thousands, $decimal] = match ($style) {
            'eu'    => ['.', ','],
            'fr'    => [' ', ','],
            'ch'    => ['’', '.'],
            default => [',', '.'],
        };

        if ($style === 'in') {
            $whole = (string) floor($amount);
            $last3 = substr($whole, -3);
            $rest  = substr($whole, 0, -3);
            $rest  = $rest !== '' ? preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) . ',' : '';
            $number = $rest . $last3 . ($decimals ? '.' . str_pad((string) round(($amount - floor($amount)) * 100), 2, '0', STR_PAD_LEFT) : '');
        } else {
            $number = number_format($amount, $decimals, $decimal, $thousands);
        }

        $out = in_array($style, ['eu', 'fr'], true) ? "{$number} {$symbol}" : "{$symbol}{$number}";

        return ($negative ? '-' : '') . trim($out);
    }
}
