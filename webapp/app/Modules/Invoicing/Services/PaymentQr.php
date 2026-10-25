<?php

namespace App\Modules\Invoicing\Services;

use App\Support\QrCode;

/**
 * The UPI payment QR on a bill (ported from Clinic Ledger): any UPI app opens
 * it with the amount due filled in. Rupees only; shown while something is
 * left to pay and the bank account printed has a UPI id.
 *
 *   upi://pay?pa=<UPI id>&pn=<payee>&am=<amount>&cu=INR&tn=<note>
 */
final class PaymentQr
{
    public const UPI_ID = '/^[A-Za-z0-9.\-_]{2,256}@[A-Za-z][A-Za-z0-9.\-]{1,63}$/';

    /** @return ?array{uri: string, svg: string, amount: ?float} */
    public static function for(?array $bank, string $currency, float $due, ?string $number, string $payee): ?array
    {
        $upi = trim((string) ($bank['upi_id'] ?? ''));
        if ($upi === '' || ! preg_match(self::UPI_ID, $upi) || strtoupper($currency) !== 'INR' || $due <= 0) {
            return null;
        }
        $uri = self::upiUri($upi, (string) ($bank['holder_name'] ?? '') ?: $payee, round($due, 2), $number);

        return ['uri' => $uri, 'svg' => QrCode::encode($uri, 'M')->svg(4, '#111827', __('Pay by UPI')), 'amount' => round($due, 2)];
    }

    public static function upiUri(string $upiId, string $payee, ?float $amount, ?string $number): string
    {
        $query = 'pa=' . $upiId . '&pn=' . rawurlencode(mb_substr(trim($payee), 0, 50));
        if ($amount !== null) {
            $query .= '&am=' . number_format($amount, 2, '.', '');
        }
        $query .= '&cu=INR';
        if ($number) {
            $query .= '&tn=' . rawurlencode(mb_substr('Bill ' . $number, 0, 50));
        }

        return 'upi://pay?' . $query;
    }
}
