<?php

namespace App\Support;

/**
 * "Rupees fifty-six thousand six hundred and forty only" — the amount in
 * words that Indian and many other invoices carry. English only; the Indian
 * numbering system (lakh, crore) for the currencies that use it.
 */
final class NumberWords
{
    private const ONES = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];

    private const TENS = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

    private const INDIAN = ['INR', 'NPR', 'PKR', 'BDT', 'LKR'];

    private const NAMES = [
        'INR' => ['Rupees', 'paise'], 'NPR' => ['Rupees', 'paisa'], 'PKR' => ['Rupees', 'paisa'], 'LKR' => ['Rupees', 'cents'],
        'BDT' => ['Taka', 'poisha'], 'USD' => ['Dollars', 'cents'], 'AUD' => ['Dollars', 'cents'], 'CAD' => ['Dollars', 'cents'],
        'NZD' => ['Dollars', 'cents'], 'SGD' => ['Dollars', 'cents'], 'HKD' => ['Dollars', 'cents'], 'EUR' => ['Euros', 'cents'],
        'GBP' => ['Pounds', 'pence'], 'AED' => ['Dirhams', 'fils'], 'SAR' => ['Riyals', 'halalas'], 'QAR' => ['Riyals', 'dirhams'],
        'OMR' => ['Rials', 'baisa'], 'BHD' => ['Dinars', 'fils'], 'KWD' => ['Dinars', 'fils'], 'ZAR' => ['Rand', 'cents'],
        'MYR' => ['Ringgit', 'sen'], 'PHP' => ['Pesos', 'centavos'], 'JPY' => ['Yen', ''], 'KRW' => ['Won', ''], 'CNY' => ['Yuan', 'fen'],
        'BRL' => ['Reais', 'centavos'], 'MXN' => ['Pesos', 'centavos'], 'TRY' => ['Lira', 'kuruş'], 'ILS' => ['Shekels', 'agorot'],
        'NGN' => ['Naira', 'kobo'], 'KES' => ['Shillings', 'cents'], 'EGP' => ['Pounds', 'piastres'], 'CHF' => ['Francs', 'rappen'],
        'SEK' => ['Kronor', 'öre'], 'NOK' => ['Kroner', 'øre'], 'DKK' => ['Kroner', 'øre'], 'PLN' => ['Złoty', 'groszy'],
        'THB' => ['Baht', 'satang'], 'VND' => ['Dong', ''], 'IDR' => ['Rupiah', 'sen'],
    ];

    public static function amount(float $amount, string $currency): string
    {
        $currency = strtoupper($currency);
        [$major, $minor] = self::NAMES[$currency] ?? [$currency, 'cents'];

        $negative = $amount < 0;
        $amount   = round(abs($amount), 2);
        $whole    = (int) floor($amount);
        $cents    = (int) round(($amount - $whole) * 100);

        $words = in_array($currency, self::INDIAN, true) ? self::indian($whole) : self::international($whole);
        $text  = $major . ' ' . ($words ?: 'zero');

        if ($cents > 0 && $minor !== '') {
            $text .= ' and ' . self::international($cents) . ' ' . $minor;
        }

        return ($negative ? 'Minus ' : '') . ucfirst($text) . ' only';
    }

    /** 1,23,456 → one lakh twenty-three thousand four hundred and fifty-six */
    public static function indian(int $n): string
    {
        if ($n === 0) {
            return '';
        }

        $parts = [];

        if ($n >= 10000000) {
            $parts[] = self::indian(intdiv($n, 10000000)) . ' crore';
            $n %= 10000000;
        }
        if ($n >= 100000) {
            $parts[] = self::belowThousand(intdiv($n, 100000)) . ' lakh';
            $n %= 100000;
        }
        if ($n >= 1000) {
            $parts[] = self::belowThousand(intdiv($n, 1000)) . ' thousand';
            $n %= 1000;
        }
        if ($n > 0) {
            $parts[] = self::belowThousand($n);
        }

        return trim(implode(' ', $parts));
    }

    /** 1,234,567 → one million two hundred and thirty-four thousand five hundred and sixty-seven */
    public static function international(int $n): string
    {
        if ($n === 0) {
            return '';
        }

        $scales = [1000000000 => 'billion', 1000000 => 'million', 1000 => 'thousand'];
        $parts  = [];

        foreach ($scales as $value => $name) {
            if ($n >= $value) {
                $parts[] = self::belowThousand(intdiv($n, $value)) . ' ' . $name;
                $n %= $value;
            }
        }
        if ($n > 0) {
            $parts[] = self::belowThousand($n);
        }

        return trim(implode(' ', $parts));
    }

    private static function belowThousand(int $n): string
    {
        $out = '';

        if ($n >= 100) {
            $out .= self::ONES[intdiv($n, 100)] . ' hundred';
            $n %= 100;
            if ($n > 0) {
                $out .= ' and ';
            }
        }

        if ($n >= 20) {
            $out .= self::TENS[intdiv($n, 10)] . ($n % 10 ? '-' . self::ONES[$n % 10] : '');
        } elseif ($n > 0) {
            $out .= self::ONES[$n];
        }

        return $out;
    }
}
