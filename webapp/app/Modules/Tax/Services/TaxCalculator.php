<?php

namespace App\Modules\Tax\Services;

/**
 * The arithmetic of a document's tax, ported from Clinic Ledger's
 * InvoiceCalculator so the builder, finalisation and the tests agree to the
 * paisa (or the fils), and extended with reverse charge and 3-decimal
 * currencies.
 *
 *   line gross     = quantity × rate
 *   line subtotal  = gross − line discount %
 *   discount       = document discount (% or amount), spread over the lines
 *                    pro rata (the last line takes the rounding drift), so
 *                    tax is charged on what is actually paid
 *   tax            = per line, per part (CGST + SGST, IGST, VAT…), added to
 *                    exclusive prices or carved out of inclusive ones
 *   reverse charge = worked out the same way but NOT charged: kept as a
 *                    notional amount for the buyer and the returns
 *   total          = Σ line totals, optionally rounded to the whole unit
 *                    (a separate "round off" amount)
 */
final class TaxCalculator
{
    /**
     * @param array<int, array{quantity: float, unit_rate: float, discount_percent?: float, parts: array<string, float>, reverse_charge?: bool}> $lines
     * @param array{inclusive?: bool, decimals?: int, discount_type?: string, discount_value?: float, round_total?: bool} $options
     */
    public static function compute(array $lines, array $options = []): array
    {
        $inclusive = (bool) ($options['inclusive'] ?? false);
        $d         = (int) ($options['decimals'] ?? 2);
        $r         = fn (float $v) => round($v, $d);

        // 1. Line subtotals.
        $out      = [];
        $subtotal = 0.0;
        foreach ($lines as $i => $line) {
            $gross    = $r((float) ($line['quantity'] ?? 0) * (float) ($line['unit_rate'] ?? 0));
            $discount = max(0.0, min(100.0, (float) ($line['discount_percent'] ?? 0)));
            $lineSub  = $r($gross - $r($gross * $discount / 100));

            $out[$i] = $line + ['gross' => $gross, 'line_subtotal' => $lineSub, 'net' => $lineSub];
            $subtotal += $lineSub;
        }
        $subtotal = $r($subtotal);

        // 2. The document discount, spread pro rata.
        $discountAmount = match ($options['discount_type'] ?? 'none') {
            'percent' => $r($subtotal * max(0.0, min(100.0, (float) ($options['discount_value'] ?? 0))) / 100),
            'amount'  => $r(max(0.0, min($subtotal, (float) ($options['discount_value'] ?? 0)))),
            default   => 0.0,
        };
        if ($discountAmount > 0 && $subtotal > 0) {
            $remaining = $r($subtotal - $discountAmount);
            $allocated = 0.0;
            $keys      = array_keys($out);
            foreach ($keys as $k => $i) {
                if ($k === count($keys) - 1) {
                    $out[$i]['net'] = $r($remaining - $allocated);
                } else {
                    $out[$i]['net'] = $r($out[$i]['line_subtotal'] * $remaining / $subtotal);
                    $allocated += $out[$i]['net'];
                }
            }
        }

        // 3. Tax per line and per part.
        $taxes = [];
        $charged = $notional = $taxable = $total = 0.0;

        foreach ($out as $i => $line) {
            $parts   = array_filter($line['parts'] ?? [], fn ($p) => (float) $p > 0);
            $reverse = (bool) ($line['reverse_charge'] ?? false);
            $net     = (float) $line['net'];
            $percent = array_sum($parts);

            // Inclusive prices carve the tax out; under reverse charge nothing was charged, so nothing is carved out.
            $base = $inclusive && $parts && ! $reverse ? $r($net / (1 + $percent / 100)) : $net;
            $amounts = [];
            $lineTax = 0.0;
            foreach ($parts as $code => $pct) {
                $amount = $inclusive && ! $reverse && array_key_last($parts) === $code
                    ? $r($net - $base - $lineTax)
                    : $r($base * $pct / 100);
                $amounts[$code] = $amount;
                $lineTax += $amount;

                $key = $code . '|' . self::pct($pct) . '|' . ($reverse ? 'rc' : 'ch');
                $taxes[$key] ??= ['code' => $code, 'percent' => (float) $pct, 'label' => $code . ' ' . self::pct($pct) . '%', 'charged' => ! $reverse, 'taxable' => 0.0, 'amount' => 0.0];
                $taxes[$key]['taxable'] = $r($taxes[$key]['taxable'] + $base);
                $taxes[$key]['amount']  = $r($taxes[$key]['amount'] + $amount);
            }
            $lineTax = $r($lineTax);

            $lineTotal = $reverse || ! $parts ? $net : ($inclusive ? $net : $r($net + $lineTax));
            $out[$i] += [
                'taxable' => $base, 'part_amounts' => $amounts, 'rate_percent' => (float) $percent,
                'tax' => $reverse ? 0.0 : $lineTax, 'notional_tax' => $reverse ? $lineTax : 0.0, 'line_total' => $lineTotal,
            ];
            $taxable  += $base;
            $charged  += $reverse ? 0.0 : $lineTax;
            $notional += $reverse ? $lineTax : 0.0;
            $total    += $lineTotal;
        }
        $total = $r($total);

        // 4. Round the total to the whole unit, if the company does.
        $roundOff = 0.0;
        if (! empty($options['round_total']) && $d > 0) {
            $rounded  = round($total);
            $roundOff = $r($rounded - $total);
            $total    = (float) $rounded;
        }

        return [
            'lines'         => $out,
            'taxes'         => array_values($taxes),
            'subtotal'      => $subtotal,
            'discount'      => $discountAmount,
            'taxable_total' => $r($taxable),
            'tax_charged'   => $r($charged),
            'tax_notional'  => $r($notional),
            'round_off'     => $roundOff,
            'total'         => $total,
        ];
    }

    /** "2.5", "18", "9.975" */
    public static function pct(float $pct): string
    {
        return rtrim(rtrim(number_format($pct, 3, '.', ''), '0'), '.');
    }
}
