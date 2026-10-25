<?php

namespace App\Modules\Accounts\Services;

use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\PartyBalance;
use App\Modules\Trips\Models\HireChallan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The working lists the accountant lives in (blueprint 09 §2.4, §4.4, 07
 * §2.3): who owes how long (bill-wise ageing of clients' bills, the hire
 * still owed to truck suppliers) and the TDS registers — what clients
 * deducted from us (to match with Form 26AS / AIS) and what we deducted
 * from suppliers (for the TDS return), with the nil-TDS declarations.
 */
class Registers
{
    public const SLABS = ['not_due' => 'Not due', 'd30' => '1–30 days', 'd60' => '31–60 days', 'd90' => '61–90 days', 'over' => 'Over 90 days'];

    /**
     * Clients' open bills by age on a date, per client, with each bill.
     * $basis 'due' counts days past the due date; 'bill' from the bill date.
     */
    public function receivables(CarbonImmutable $asOf, string $basis = 'due'): Collection
    {
        $asOf = self::day($asOf);
        $advances = PartyBalance::where('advance', '>', 0)->pluck('advance', 'party_id');

        return Invoice::with('party:id,name')->whereIn('status', Invoice::OPEN_STATUSES)->where('doc_type', '!=', 'credit_note')
            ->where('balance_due', '>', 0)->where('issue_date', '<=', $asOf->toDateString())->orderBy('due_date')->get()
            ->groupBy('party_id')
            ->map(function (Collection $bills) use ($asOf, $basis, $advances) {
                $row = ['party' => $bills->first()->party, 'bills' => [], 'total' => 0.0, 'advance' => round((float) ($advances[$bills->first()->party_id] ?? 0), 2)] + array_fill_keys(array_keys(self::SLABS), 0.0);
                foreach ($bills as $bill) {
                    $days = (int) CarbonImmutable::parse(($basis === 'bill' ? $bill->issue_date : $bill->due_date)->toDateString(), 'UTC')->diffInDays($asOf, false);
                    $slab = $this->slab($days, $basis);
                    $row[$slab] = round($row[$slab] + (float) $bill->balance_due, 2);
                    $row['total'] = round($row['total'] + (float) $bill->balance_due, 2);
                    $row['bills'][] = ['invoice' => $bill, 'days' => $days, 'slab' => $slab];
                }

                return $row;
            })
            ->sortByDesc('total')->values();
    }

    /** Hire still owed to truck suppliers on issued challans, by the challan's age. */
    public function payables(CarbonImmutable $asOf): Collection
    {
        $asOf = self::day($asOf);
        return HireChallan::with(['supplier:id,name', 'lines', 'trip:id,trip_number'])->where('status', 'issued')
            ->where('challan_date', '<=', $asOf->toDateString())->orderBy('challan_date')->get()
            ->map(fn (HireChallan $c) => ['challan' => $c, 'balance' => $c->balance()])
            ->filter(fn ($r) => $r['balance'] > 0.004)
            ->groupBy(fn ($r) => $r['challan']->supplier_party_id)
            ->map(function (Collection $rows) use ($asOf) {
                $row = ['party' => $rows->first()['challan']->supplier, 'bills' => [], 'total' => 0.0] + array_fill_keys(array_keys(self::SLABS), 0.0);
                foreach ($rows as $r) {
                    $days = (int) CarbonImmutable::parse($r['challan']->challan_date->toDateString(), 'UTC')->diffInDays($asOf, false);
                    $slab = $this->slab($days, 'bill');
                    $row[$slab] = round($row[$slab] + $r['balance'], 2);
                    $row['total'] = round($row['total'] + $r['balance'], 2);
                    $row['bills'][] = ['challan' => $r['challan'], 'balance' => $r['balance'], 'days' => $days, 'slab' => $slab];
                }

                return $row;
            })
            ->sortByDesc('total')->values();
    }

    /**
     * TDS clients deducted from our bills, per receipt and bill (for 26AS /
     * AIS matching), with what each bill expected.
     */
    public function tdsReceivable(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $company = app(\App\Platform\Tenancy\CompanyContext::class)->id();

        return DB::table('receipt_allocations as a')->join('receipts as r', 'r.id', '=', 'a.receipt_id')->join('invoices as i', 'i.id', '=', 'a.invoice_id')
            ->join('parties as p', 'p.id', '=', 'r.party_id')
            ->where('a.company_id', $company)->where('a.tds', '>', 0)->where('r.status', '!=', 'void')
            ->whereBetween('r.receipt_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('r.receipt_date')->orderBy('r.number')
            ->get(['p.id as party_id', 'p.name as party', 'p.pan', 'p.tan', 'i.buyer_tax_id', 'i.number as invoice', 'i.issue_date', 'i.total as bill_total', 'i.tds_expected', 'a.tds', 'r.tds_section as section', 'r.number as receipt', 'r.receipt_date'])
            ->map(function ($row) {
                // No PAN typed on the party: an Indian GSTIN carries it (characters 3 to 12).
                $row->pan ??= $row->buyer_tax_id && preg_match('/^\d{2}([A-Z]{5}\d{4}[A-Z])/', $row->buyer_tax_id, $m) ? $m[1] : null;
                $row->quarter = $this->quarter(CarbonImmutable::parse($row->receipt_date));
                $row->difference = round((float) $row->tds - (float) $row->tds_expected, 2);

                return $row;
            });
    }

    /** TDS we deducted on lorry hire (and the nil-TDS declarations), per challan, for the TDS return. */
    public function tdsPayable(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return HireChallan::with('supplier:id,name,pan')->whereIn('status', ['issued', 'settled'])
            ->whereBetween('challan_date', [$from->toDateString(), $to->toDateString()])
            ->where(fn ($q) => $q->where('tds_amount', '>', 0)->orWhereNotNull('tds_nil_reason'))
            ->orderBy('challan_date')->orderBy('number')->get()
            ->map(fn (HireChallan $c) => (object) [
                'party' => $c->supplier->name, 'pan' => $c->supplier->pan, 'challan' => $c->number, 'date' => $c->challan_date, 'hire' => (float) $c->hire_amount,
                'section' => $c->tds_section, 'rate' => (float) $c->tds_rate, 'tds' => (float) $c->tds_amount, 'nil_reason' => $c->tds_nil_reason, 'quarter' => $this->quarter(CarbonImmutable::parse($c->challan_date->toDateString())),
            ]);
    }

    /** "Q3 2026-27": the Indian TDS quarter (Apr–Jun = Q1). */
    public function quarter(CarbonImmutable $date): string
    {
        $fy = \App\Support\FiscalYear::containing($date, 4);
        $q = intdiv(($date->month + 8) % 12, 3) + 1;

        return 'Q' . $q . ' ' . $fy->key;
    }

    /** A calendar day with no time zone, so day counts are whole days whatever the company's zone. */
    private static function day(CarbonImmutable $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date->toDateString(), 'UTC');
    }

    private function slab(int $days, string $basis): string
    {
        return match (true) {
            $days <= 0 && $basis === 'due' => 'not_due',
            $days <= 30 => 'd30',
            $days <= 60 => 'd60',
            $days <= 90 => 'd90',
            default => 'over',
        };
    }
}
