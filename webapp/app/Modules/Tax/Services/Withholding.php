<?php

namespace App\Modules\Tax\Services;

use App\Modules\Documents\Models\Document;
use App\Modules\Parties\Models\Party;
use App\Modules\Settings\Services\CompanySettings;
use App\Modules\Tax\Models\TaxRegistration;
use App\Platform\Tenancy\CompanyContext;
use App\Support\FiscalYear;
use Carbon\CarbonInterface;

/**
 * Tax deducted at source on transport (blueprint 11 §1 #10, 07 §2.3), apart
 * from GST/VAT. India only for now.
 *
 * Contractor TDS: section 393(1) of the Income-tax Act 2025 from 1 April 2026
 * (old section 194C, still printed beside it in 2026-27): 1% when the payee
 * is an individual or HUF, 2% otherwise. A transporter owning ten goods
 * carriages or fewer who gives a declaration with PAN is paid without TDS
 * (393(4), old 194C(6)) but is still reported.
 *
 *  · onPayment: what we deduct when we pay a hired truck's owner.
 *  · onSale: what a client is expected to deduct from our invoice (we book
 *    it as TDS receivable and match it with Form 26AS later).
 *
 * Thresholds (₹30,000 a payment, ₹1,00,000 a year) are the payer's test;
 * the suggestion is shown, the accountant confirms (verify).
 */
class Withholding
{
    public const NEW_ACT_FROM = '2026-04-01';

    public function __construct(private CompanyContext $context, private CompanySettings $settings) {}

    /** @return array{section: string, old_section: ?string, label: string} */
    public function section(CarbonInterface $on, bool $declaration = false): array
    {
        $new = $on->toDateString() >= self::NEW_ACT_FROM;
        [$section, $old] = $declaration ? ['393(4)', '194C(6)'] : ['393(1)', '194C'];

        return $new
            ? ['section' => $section, 'old_section' => $old, 'label' => __('Section :new (old :old)', ['new' => $section, 'old' => $old])]
            : ['section' => $old, 'old_section' => null, 'label' => __('Section :old', ['old' => $old])];
    }

    /** @return array{section: ?string, old_section: ?string, label: ?string, rate: float, nil_reason: ?string} */
    public function onPayment(Party $payee, ?CarbonInterface $on = null): array
    {
        $on ??= now();
        if ($this->context->company()->country !== 'IN') {
            return ['section' => null, 'old_section' => null, 'label' => null, 'rate' => 0.0, 'nil_reason' => null];
        }

        $declaration = Document::where('owner_type', 'party')->where('owner_id', $payee->id)->where('document_type', 'no_tds_decl')
            ->where(fn ($q) => $q->whereNull('expires_on')->orWhere('expires_on', '>=', $on->toDateString()))->exists();
        if ($declaration) {
            $fy = FiscalYear::containing($on, (int) $this->context->company()->fy_start_month);

            return $this->section($on, true) + ['rate' => 0.0, 'nil_reason' => __('Declaration of 10 trucks or fewer on file for :fy', ['fy' => $fy->key])];
        }

        return $this->section($on) + ['rate' => self::rateForPan($payee->pan), 'nil_reason' => null];
    }

    /**
     * What the client is expected to deduct from this invoice, or null when
     * they do not deduct (the party's "Client deducts TDS" switch).
     *
     * @return ?array{section: string, old_section: ?string, label: string, rate: float, base: float, amount: float, nil_reason: ?string}
     */
    public function onSale(Party $buyer, ?TaxRegistration $registration, float $base, CarbonInterface $on): ?array
    {
        if ($this->context->company()->country !== 'IN' || ! $buyer->tds_deducted) {
            return null;
        }

        if ($this->settings->get('tds_declaration_given')) {
            return $this->section($on, true) + ['rate' => 0.0, 'base' => $base, 'amount' => 0.0, 'nil_reason' => __('We give this client the declaration of 10 trucks or fewer.')];
        }

        // The rate follows the payee — us: 1% for an individual or HUF, else 2% (unless the client's agreed rate is set).
        $rate = $buyer->tds_rate !== null ? (float) $buyer->tds_rate : self::rateForPan($registration?->pan());

        return $this->section($on) + ['rate' => $rate, 'base' => round($base, 2), 'amount' => round($base * $rate / 100, 2), 'nil_reason' => null];
    }

    /** 1% for an individual (P) or HUF (H) by the PAN's fourth letter, else 2%. */
    public static function rateForPan(?string $pan): float
    {
        return in_array($pan ? strtoupper($pan)[3] ?? '' : '', ['P', 'H'], true) ? 1.0 : 2.0;
    }
}
