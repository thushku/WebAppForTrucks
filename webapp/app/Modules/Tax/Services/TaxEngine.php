<?php

namespace App\Modules\Tax\Services;

use App\Modules\Parties\Models\PartyTaxId;
use App\Modules\Settings\Services\CompanySettings;
use App\Modules\Tax\Models\TaxCode;
use App\Platform\Tenancy\CompanyContext;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * The tax of a document, step by step (blueprint 11 §3):
 *
 *   1. facts: date, our registration, the buyer, the trip (TaxContext);
 *   2. regime from our registration — none: a document without tax;
 *   3. place of supply (PlaceOfSupply), never guessed;
 *   4–5. each line's class → the most specific rule → a tax code; add-ons
 *        take the freight line's code ("@main"); no rule stops the document;
 *   6. a code changed by hand needs a reason (kept in the trace);
 *   7. zero-rated lines need their papers: blocked, or "pending evidence"
 *      (Settings → Tax);
 *   8–10. the rate valid on the date, split for the place of supply, worked
 *        out by TaxCalculator; reverse charge kept as notional tax;
 *   12. the TDS the client is expected to deduct (India).
 *
 * The answer carries a trace in plain words — why each line got its tax —
 * that is kept with the finalised document.
 */
class TaxEngine
{
    public function __construct(
        private CompanyContext $context,
        private CompanySettings $settings,
        private PlaceOfSupply $places,
        private TaxRuleMatcher $rules,
        private Withholding $withholding,
    ) {}

    /**
     * @param array<int, array{service_class: string, description?: string, quantity?: float, unit_rate: float, discount_percent?: float, tax_code?: ?string, override_reason?: ?string}> $lines
     * @param array{discount_type?: string, discount_value?: float} $options
     */
    public function calculate(TaxContext $ctx, array $lines, array $options = []): array
    {
        if ($lines === []) {
            throw new TaxException(__('Add a line first.'), 'lines');
        }
        $company  = $this->context->company();
        $currency = $ctx->currency ?? $company->currency;
        $trace    = [];
        $warnings = [];

        foreach ($lines as $i => $line) {
            if (! ServiceClasses::exists((string) ($line['service_class'] ?? ''))) {
                throw new TaxException(__('Line :n: choose what the charge is for.', ['n' => $i + 1]), "lines.{$i}.service_class");
            }
        }

        $roundTotal = $ctx->roundTotal ?? (bool) $this->settings->get('invoice_round_total');
        $options += ['inclusive' => $ctx->inclusive, 'decimals' => Money::decimals($currency), 'round_total' => $roundTotal];

        // 2. Not registered: an invoice without tax (India: a bill of supply).
        $registration = $ctx->registration;
        if (! $registration) {
            $trace[] = __('Not registered for tax on :date: no tax charged.', ['date' => $ctx->date->toDateString()]);
            $computed = TaxCalculator::compute(array_map(fn ($l) => $l + ['parts' => [], 'quantity' => $l['quantity'] ?? 1], $lines), $options);

            return $computed + [
                'document' => $company->country === 'IN' ? 'bill_of_supply' : 'invoice', 'regime' => null, 'registration' => null,
                'place_of_supply' => null, 'reverse_charge' => false, 'legal_texts' => [], 'warnings' => [], 'trace' => $trace,
                'pending_evidence' => false, 'withholding' => $ctx->buyer ? $this->withholding->onSale($ctx->buyer, null, $computed['taxable_total'], $ctx->date) : null,
                'currency' => $currency,
            ];
        }

        $regime = $registration->regime;
        $trace[] = __('Issued under :id (:regime).', ['id' => $registration->tax_id, 'regime' => __(\App\Modules\Tax\Models\TaxRegistration::REGIMES[$regime] ?? $regime)]);

        // The buyer's registration the bill is addressed to.
        $buyerTaxId = $this->buyerTaxId($ctx, $registration->country);
        $registered = $buyerTaxId !== null;
        $category   = $ctx->buyer?->tax_category ?: ($registered ? 'regular' : 'unregistered');
        $gtaOption  = $regime === 'in_gst' ? $registration->gtaOption($ctx->date, (int) ($company->fy_start_month ?: 4)) : null;
        $trace[] = $registered
            ? __('Buyer registered: :id.', ['id' => $buyerTaxId->tax_id])
            : __('Buyer not registered.');
        if ($gtaOption) {
            $trace[] = __('GTA option for the year: :option.', ['option' => __(\App\Modules\Tax\Models\TaxRegistration::GTA_OPTIONS[$gtaOption])]);
        }

        // 4–6. A tax code for every line; the main supply first, add-ons follow it.
        $facts = ['gta_option' => $gtaOption, 'transport_type' => $ctx->transportType, 'carrier_role' => $ctx->carrierRole,
            'buyer_registered' => $registered, 'buyer_category' => $category];
        $mainIndex = collect($lines)->search(fn ($l) => in_array($l['service_class'], ServiceClasses::MAIN, true));
        $resolved  = [];
        $order     = $mainIndex === false ? array_keys($lines) : [$mainIndex, ...array_diff(array_keys($lines), [$mainIndex])];

        foreach ($order as $i) {
            $resolved[$i] = $this->resolveLine($regime, $lines[$i], $i, $facts, $ctx, $mainIndex === false ? null : ($resolved[$mainIndex] ?? null), $trace);
        }
        ksort($resolved);

        // 3. Place of supply, from the main supply's class.
        $mainClass = $lines[$mainIndex === false ? 0 : $mainIndex]['service_class'];
        $place = $this->places->resolve($ctx, $buyerTaxId, $mainClass);
        $trace[] = __('Place of supply :name: :reason', ['name' => $place['name'], 'reason' => $place['reason']]);

        // 7. Papers for zero-rated lines.
        $pending = false;
        foreach ($resolved as $i => $r) {
            $missing = array_values(array_diff($r['code']->evidence ?? [], $ctx->evidence));
            if ($missing) {
                $names = collect($missing)->map(fn ($e) => __(TaxCode::EVIDENCE[$e] ?? $e))->join(', ');
                if ($this->settings->get('tax_evidence_policy') !== 'warn') {
                    throw new TaxException(__(':code needs these papers on file: :papers.', ['code' => $r['code']->name, 'papers' => $names]), 'evidence');
                }
                $warnings[] = __('Pending evidence for :code: :papers.', ['code' => $r['code']->name, 'papers' => $names]);
                $pending = true;
            }
        }

        // 8–10. Rates for the date, split for the place of supply, and the arithmetic.
        $input = [];
        foreach ($lines as $i => $line) {
            $code = $resolved[$i]['code'];
            $rate = $code->rateOn($ctx->date) ?? throw new TaxException(__(':code has no rate for :date.', ['code' => $code->name, 'date' => $ctx->date->format('d M Y')]), "lines.{$i}.tax_code");
            $parts = in_array($code->category, ['Z', 'E', 'O'], true) ? [] : $rate->partsFor($place['where'] === 'national' ? 'intra' : $place['where']);

            // What the engine decided wins over what the line came with (an empty SAC must not hide the rule's).
            $input[$i] = array_merge($line, [
                'quantity' => $line['quantity'] ?? 1, 'parts' => $parts, 'reverse_charge' => $code->reverse_charge,
                'tax_code' => $code->code, 'category' => $code->category, 'tax_code_name' => $code->name, 'rate_id' => $rate->id,
                'sac_code' => $resolved[$i]['sac'] ?? ($line['sac_code'] ?? null), 'rule_id' => $resolved[$i]['rule_id'], 'legal_text' => $code->legal_text,
            ]);
        }
        $computed = TaxCalculator::compute($input, $options);

        $codes = collect($resolved)->pluck('code');
        $reverse = $codes->contains(fn (TaxCode $c) => $c->reverse_charge);
        $document = $codes->every(fn (TaxCode $c) => in_array($c->category, ['E', 'O'], true)) && $regime === 'in_gst' ? 'bill_of_supply' : 'tax_invoice';
        if ($reverse) {
            $trace[] = __('Reverse charge: :amount of tax is for the buyer to pay; it is shown, not charged.', ['amount' => Money::format($computed['tax_notional'], $currency)]);
        }

        return $computed + [
            'document'        => $document,
            'regime'          => $regime,
            'registration'    => ['id' => $registration->id, 'tax_id' => $registration->tax_id, 'state_code' => $registration->state_code],
            'buyer_tax_id'    => $buyerTaxId?->tax_id,
            'place_of_supply' => $place,
            'reverse_charge'  => $reverse,
            'legal_texts'     => $codes->pluck('legal_text')->filter()->unique()->values()->all(),
            'warnings'        => $warnings,
            'pending_evidence' => $pending,
            'trace'           => $trace,
            'withholding'     => $ctx->buyer ? $this->withholding->onSale($ctx->buyer, $registration, $computed['taxable_total'], $ctx->date) : null,
            'currency'        => $currency,
        ];
    }

    /** @return array{code: TaxCode, sac: ?string, rule_id: ?int} */
    private function resolveLine(string $regime, array $line, int $i, array $facts, TaxContext $ctx, ?array $main, array &$trace): array
    {
        $class = $line['service_class'];
        $label = ServiceClasses::label($class);

        // A code chosen by hand: allowed with a reason, and said so.
        if (! empty($line['tax_code'])) {
            if (mb_strlen(trim((string) ($line['override_reason'] ?? ''))) < 10) {
                throw new TaxException(__('Line :n: say why the tax is changed by hand (at least 10 letters).', ['n' => $i + 1]), "lines.{$i}.override_reason");
            }
            $code = $this->code($line['tax_code'], $i);
            $trace[] = __(':label: :code, changed by hand — :reason', ['label' => $label, 'code' => $code->name, 'reason' => trim($line['override_reason'])]);

            return ['code' => $code, 'sac' => $line['sac_code'] ?? null, 'rule_id' => null];
        }

        $rule = $this->rules->match($regime, ['service_class' => $class] + $facts, $ctx->date);
        if (! $rule) {
            throw new TaxException($regime === 'ae_vat' && $class === 'FREIGHT_ROAD' && ! $ctx->transportType
                ? __('Set the transport type on the load (domestic, export, import or international).')
                : __('No tax rule for :label in these circumstances. Add one in Settings → Tax, or choose the tax by hand with a reason.', ['label' => $label]), "lines.{$i}.service_class");
        }

        if ($rule->tax_code === '@main') {
            if (! $main) {
                throw new TaxException(__(':label is charged with the freight: add the freight line.', ['label' => $label]), "lines.{$i}.service_class");
            }
            $trace[] = __(':label follows the freight line: :code.', ['label' => $label, 'code' => $main['code']->name]);

            return ['code' => $main['code'], 'sac' => $main['sac'], 'rule_id' => $rule->id];
        }

        $code = $this->code($rule->tax_code, $i);
        $trace[] = __(':label: :code (rule :rule:note).', ['label' => $label, 'code' => $code->name, 'rule' => $rule->id, 'note' => $rule->note ? ' — ' . $rule->note : '']);

        return ['code' => $code, 'sac' => $rule->sac_code, 'rule_id' => $rule->id];
    }

    private function code(string $code, int $i): TaxCode
    {
        return TaxCode::with('rates')->where('code', $code)->first()
            ?? throw new TaxException(__('Line :n: the tax code :code does not exist.', ['n' => $i + 1, 'code' => $code]), "lines.{$i}.tax_code");
    }

    private function buyerTaxId(TaxContext $ctx, string $country): ?PartyTaxId
    {
        if (! $ctx->buyer) {
            return null;
        }
        /** @var Collection<int, PartyTaxId> $ids */
        $ids = $ctx->buyer->relationLoaded('taxIds') ? $ctx->buyer->taxIds : $ctx->buyer->taxIds()->get();
        $ids = $ids->where('active', true)->where('country', $country);

        if ($ctx->buyerTaxId) {
            return $ids->firstWhere('tax_id', $ctx->buyerTaxId)
                ?? throw new TaxException(__('The buyer has no active registration :id.', ['id' => $ctx->buyerTaxId]), 'buyer_tax_id');
        }

        return $ids->firstWhere('is_default', true) ?? $ids->first();
    }
}
