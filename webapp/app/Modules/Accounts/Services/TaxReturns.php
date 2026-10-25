<?php

namespace App\Modules\Accounts\Services;

use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Tax\Models\TaxRegistration;
use App\Support\Regions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The data a tax return needs, from the issued bills (blueprint 07 §2.3,
 * §3): India's GSTR-1 tables for one GSTIN and month, with a GSTR-3B
 * working; the UAE's VAT 201 boxes for a TRN and period. The tables follow
 * the GST offline tool's sheet columns so they can be pasted in; the
 * accountant files the return — this only prepares it.
 *
 * Not tax advice: every figure comes from the bills as issued (the tax
 * engine's decision on each line); the CA checks before filing (verify).
 */
class TaxReturns
{
    /** B2C large: an unregistered buyer in another state above this invoice value (₹1 lakh since Aug 2024 — verify). */
    public const B2CL_LIMIT = 100000;

    /**
     * GSTR-1 for one GSTIN and month.
     *
     * @return array{b2b: array, b2cl: array, b2cs: array, cdnr: array, cdnur: array, exemp: array, hsn: array, docs: array, summary: array}
     */
    public function gstr1(TaxRegistration $registration, CarbonImmutable $month): array
    {
        $from = $month->startOfMonth();
        $to = $month->endOfMonth();
        $invoices = Invoice::with(['lines', 'party:id,name'])
            ->where('tax_registration_id', $registration->id)->whereNotNull('finalized_at')
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('issue_date')->orderBy('number')->get();
        $live = $invoices->where('status', '!=', 'void');
        $ourState = $registration->state_code;

        $b2b = $b2cl = $b2cs = $cdnr = $cdnur = [];
        $exempt = ['nil' => 0.0, 'exempt' => 0.0, 'non_gst' => 0.0];
        $hsn = [];

        foreach ($live as $invoice) {
            $note = in_array($invoice->doc_type, ['credit_note', 'debit_note'], true);
            $registered = filled($invoice->buyer_tax_id);
            $pos = $this->pos($invoice->place_of_supply);
            $inter = $invoice->place_of_supply && $invoice->place_of_supply !== $ourState;
            $sign = $invoice->doc_type === 'credit_note' ? -1 : 1;

            if ($invoice->kind === 'bill_of_supply') {
                $exempt['exempt'] += $sign * (float) $invoice->taxable_total;
                $this->addHsn($hsn, $invoice, $sign);

                continue;
            }

            foreach ($this->byRate($invoice) as $rate => $taxable) {
                $common = ['rate' => $rate, 'taxable' => round($taxable, 2), 'cess' => 0];
                if ($note && $registered) {
                    $cdnr[] = ['gstin' => $invoice->buyer_tax_id, 'name' => $invoice->party->name, 'number' => $invoice->number, 'date' => $this->date($invoice->issue_date),
                        'type' => $invoice->doc_type === 'credit_note' ? 'C' : 'D', 'pos' => $pos, 'rcm' => $invoice->reverse_charge ? 'Y' : 'N', 'supply_type' => 'Regular B2B',
                        'value' => round((float) $invoice->total, 2)] + $common;
                } elseif ($note && $inter && (float) $invoice->total > self::B2CL_LIMIT) {
                    $cdnur[] = ['type' => 'B2CL', 'number' => $invoice->number, 'date' => $this->date($invoice->issue_date), 'note_type' => $invoice->doc_type === 'credit_note' ? 'C' : 'D',
                        'pos' => $pos, 'value' => round((float) $invoice->total, 2)] + $common;
                } elseif ($registered) {
                    $b2b[] = ['gstin' => $invoice->buyer_tax_id, 'name' => $invoice->party->name, 'number' => $invoice->number, 'date' => $this->date($invoice->issue_date),
                        'value' => round((float) $invoice->total, 2), 'pos' => $pos, 'rcm' => $invoice->reverse_charge ? 'Y' : 'N', 'type' => 'Regular B2B'] + $common;
                } elseif ($inter && (float) $invoice->total > self::B2CL_LIMIT) {
                    $b2cl[] = ['number' => $invoice->number, 'date' => $this->date($invoice->issue_date), 'value' => round((float) $invoice->total, 2), 'pos' => $pos] + $common;
                } else {
                    // B2C small: one row per place of supply and rate, notes netted in.
                    $key = $pos . '|' . $rate;
                    $b2cs[$key] ??= ['type' => 'OE', 'pos' => $pos, 'rate' => $rate, 'taxable' => 0.0, 'cess' => 0];
                    $b2cs[$key]['taxable'] = round($b2cs[$key]['taxable'] + $sign * $taxable, 2);
                }
            }
            $this->addHsn($hsn, $invoice, $sign);
        }

        $docs = [];
        foreach (['Invoices for outward supply' => ['invoice', 'supplementary'], 'Credit note' => ['credit_note'], 'Debit note' => ['debit_note']] as $nature => $types) {
            $set = $invoices->whereIn('doc_type', $types)->sortBy('number');
            if ($set->isNotEmpty()) {
                $docs[] = ['nature' => $nature, 'from' => $set->first()->number, 'to' => $set->last()->number, 'total' => $set->count(), 'cancelled' => $set->where('status', 'void')->count()];
            }
        }

        $sales = $live->where('doc_type', '!=', 'credit_note');
        $credits = $live->where('doc_type', 'credit_note');
        $sum = fn (Collection $set, string $column) => round($set->sum(fn ($i) => (float) $i->{$column}), 2);
        $taxBy = function (Collection $set, string $code) {
            return round($set->sum(fn ($i) => $i->lines->sum(fn ($l) => (float) (($l->parts ?? [])[$code] ?? 0) * ((float) $l->tax_amount > 0 ? 1 : 0))), 2);
        };
        $taxed = fn (Collection $set) => $set->where('kind', '!=', 'bill_of_supply')->where('reverse_charge', false);
        $rcm = fn (Collection $set) => $set->where('reverse_charge', true);

        return [
            'b2b' => $b2b, 'b2cl' => $b2cl, 'b2cs' => array_values($b2cs), 'cdnr' => $cdnr, 'cdnur' => $cdnur,
            'exemp' => [['description' => 'Intra-State supplies to registered persons and others (bills of supply)', 'nil' => round($exempt['nil'], 2), 'exempt' => round($exempt['exempt'], 2), 'non_gst' => round($exempt['non_gst'], 2)]],
            'hsn' => array_values(array_map(fn ($r) => array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $r), $hsn)),
            'docs' => $docs,
            // GSTR-3B working (verify the tables with the CA): tax we charged, supplies where the recipient pays (RCM), exempt.
            'summary' => [
                'taxable' => round($sum($taxed($sales), 'taxable_total') - $sum($taxed($credits), 'taxable_total'), 2),
                'igst' => round($taxBy($taxed($sales), 'IGST') - $taxBy($taxed($credits), 'IGST'), 2),
                'cgst' => round($taxBy($taxed($sales), 'CGST') - $taxBy($taxed($credits), 'CGST'), 2),
                'sgst' => round($taxBy($taxed($sales), 'SGST') + $taxBy($taxed($sales), 'UTGST') - $taxBy($taxed($credits), 'SGST') - $taxBy($taxed($credits), 'UTGST'), 2),
                'rcm_taxable' => round($sum($rcm($sales), 'taxable_total') - $sum($rcm($credits), 'taxable_total'), 2),
                'rcm_tax' => round($sum($rcm($sales), 'tax_notional') - $sum($rcm($credits), 'tax_notional'), 2),
                'exempt' => round($exempt['exempt'] + $exempt['nil'], 2),
                'documents' => $invoices->count(), 'cancelled' => $invoices->where('status', 'void')->count(),
            ],
        ];
    }

    /**
     * The UAE VAT 201 output boxes for a period: standard-rated supplies and
     * their VAT by emirate (box 1a–1g), zero-rated (box 4), exempt (box 5),
     * credit notes netted in. Input VAT (boxes 9–11) comes with purchases.
     */
    public function vat201(TaxRegistration $registration, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $invoices = Invoice::with('lines')->where('tax_registration_id', $registration->id)->whereNotNull('finalized_at')->where('status', '!=', 'void')
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])->get();
        $boxes = [];
        foreach (Regions::AE as $code => $name) {
            $boxes[$code] = ['emirate' => $name, 'amount' => 0.0, 'vat' => 0.0];
        }
        $zero = $exempt = 0.0;
        foreach ($invoices as $invoice) {
            $sign = $invoice->doc_type === 'credit_note' ? -1 : 1;
            $emirate = isset($boxes[$invoice->place_of_supply]) ? $invoice->place_of_supply : ($registration->state_code ?: 'DU');
            foreach ($invoice->lines as $line) {
                $taxable = $sign * (float) $line->taxable;
                match ($line->tax_category) {
                    'Z' => $zero += $taxable,
                    'E' => $exempt += $taxable,
                    'O' => null,
                    default => [$boxes[$emirate]['amount'] += $taxable, $boxes[$emirate]['vat'] += $sign * (float) $line->tax_amount],
                };
            }
        }

        $boxes = array_map(fn ($b) => ['emirate' => $b['emirate'], 'amount' => round($b['amount'], 2), 'vat' => round($b['vat'], 2)], $boxes);

        return [
            'emirates' => $boxes, 'zero_rated' => round($zero, 2), 'exempt' => round($exempt, 2),
            'total_amount' => round(array_sum(array_column($boxes, 'amount')) + $zero + $exempt, 2), 'total_vat' => round(array_sum(array_column($boxes, 'vat')), 2),
        ];
    }

    // ---- Helpers ---------------------------------------------------------------------

    /** Taxable value of a bill by GST rate (the line's total rate, e.g. 18 for 9 + 9). */
    private function byRate(Invoice $invoice): array
    {
        $out = [];
        foreach ($invoice->lines as $line) {
            $rate = $this->rate((float) $line->rate_percent);
            $out[$rate] = ($out[$rate] ?? 0) + (float) $line->taxable;
        }
        ksort($out);

        return $out;
    }

    /** The HSN / SAC summary (table 12): taxable value and the tax charged by code and rate. */
    private function addHsn(array &$hsn, Invoice $invoice, int $sign): void
    {
        foreach ($invoice->lines as $line) {
            $code = $line->sac_code ?: '9965';
            $rate = $this->rate((float) $line->rate_percent);
            $key = $code . '|' . $rate;
            $hsn[$key] ??= ['hsn' => $code, 'description' => 'Goods transport services', 'uqc' => 'NA', 'qty' => 0, 'value' => 0.0, 'rate' => $rate, 'taxable' => 0.0, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'cess' => 0.0];
            $charged = (float) $line->tax_amount > 0;
            $parts = $line->parts ?? [];
            $hsn[$key]['value'] += $sign * (float) ($charged ? $line->line_total : $line->taxable);
            $hsn[$key]['taxable'] += $sign * (float) $line->taxable;
            if ($charged) {
                $hsn[$key]['igst'] += $sign * (float) ($parts['IGST'] ?? 0);
                $hsn[$key]['cgst'] += $sign * (float) ($parts['CGST'] ?? 0);
                $hsn[$key]['sgst'] += $sign * ((float) ($parts['SGST'] ?? 0) + (float) ($parts['UTGST'] ?? 0));
            }
        }
    }

    private function rate(float $percent): float|int
    {
        return fmod($percent, 1.0) === 0.0 ? (int) $percent : round($percent, 2);
    }

    /** "33-Tamil Nadu", as the GST portal writes a place of supply. */
    private function pos(?string $code): string
    {
        return $code ? $code . '-' . (Regions::IN[$code] ?? $code) : '';
    }

    /** dd-Mon-yyyy (the offline tool's format — verify against its sample sheet). */
    private function date($date): string
    {
        return CarbonImmutable::parse($date)->format('d-M-Y');
    }
}
