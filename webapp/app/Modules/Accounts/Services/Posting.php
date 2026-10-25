<?php

namespace App\Modules\Accounts\Services;

use App\Modules\Accounts\Models\Voucher;
use App\Modules\Fleet\Models\ServiceJob;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\Receipt;
use App\Modules\Parties\Models\Party;
use App\Modules\Road\Models\RoadEvent;
use App\Modules\Road\Services\EventTypes;
use App\Modules\Trips\Models\BrokerCommission;
use App\Modules\Trips\Models\Consignment;
use App\Modules\Trips\Models\HireChallan;
use App\Modules\Trips\Models\HireChallanLine;
use App\Modules\Trips\Models\Trip;
use App\Modules\Trips\Models\TripAdvance;
use App\Modules\Trips\Models\TripOfficeEntry;
use App\Modules\Trips\Models\TripSettlement;

/**
 * The entries the system writes by itself (blueprint 07 §1.1): the crew and
 * the office do their normal work, and each money record becomes one
 * balanced voucher, tagged with its trip, truck, branch and LR.
 *
 *   advance to Ravi            Dr Ravi (trip cash)          Cr cash / bank
 *   fuel paid by Ravi          Dr fuel [trip, truck]        Cr Ravi (trip cash)
 *   fuel on pump credit        Dr fuel [trip, truck]        Cr the pump (vendor)
 *   toll on FASTag             Dr toll [trip, truck]        Cr FASTag wallet
 *   money from the party       Dr Ravi (trip cash)          Cr the party
 *   Ravi returns cash          Dr cash                      Cr Ravi (trip cash)
 *   bata for Ravi              Dr bata [trip, truck]        Cr Ravi (earnings)
 *   freight bill to ACC        Dr ACC                       Cr freight [trip, truck] + output GST
 *   ACC pays, TDS deducted     Dr bank + TDS receivable     Cr ACC
 *   lorry hire challan         Dr lorry hire [trip]         Cr the supplier (− TDS, munshiana, commission)
 *   service job done           Dr repairs [truck]           Cr the workshop / cash
 *
 * Rejected entries post nothing; a cancel posts the exact reversal (Books).
 * Each method is safe to call twice: the book posts a source once.
 */
class Posting
{
    public function __construct(private Books $books, private ChartOfAccounts $chart) {}

    // ---- Bills and receipts ---------------------------------------------------------

    /** An issued bill, supplementary bill or debit note (Dr the client); a credit note the other way. */
    public function invoice(Invoice $invoice): ?Voucher
    {
        if (! $invoice->finalized_at) {
            return null;
        }
        $invoice->loadMissing(['lines.consignment.trip', 'taxes', 'party.roles']);
        $sign = $invoice->isCreditNote() ? -1 : 1;
        $lines = [['ledger' => $this->chart->party($invoice->party), 'debit' => $sign * (float) $invoice->total]];

        $credited = 0.0;
        foreach ($invoice->lines as $line) {
            $amount = round((float) $line->taxable, 3);
            $trip = $line->consignment?->trip;
            $lines[] = ['ledger' => $this->chart->income((string) $line->service_class), 'credit' => $sign * $amount] + $this->centres($trip, $invoice->branch_id, $line->consignment_id);
            $credited += $amount;
        }
        foreach ($invoice->taxes->where('charged', true) as $tax) {
            $lines[] = ['ledger' => $this->chart->outputTax($tax->code), 'credit' => $sign * (float) $tax->amount];
            $credited += (float) $tax->amount;
        }
        // The round-off line, and any paisa the line rounding left, so the voucher is the bill to the paisa.
        $rest = round((float) $invoice->total - $credited, 3);
        if (abs($rest) >= 1) {
            throw new \RuntimeException("Invoice {$invoice->number} does not add up: total {$invoice->total}, lines and tax {$credited}.");
        }
        $lines[] = ['ledger' => $this->chart->ledger('round_off'), 'credit' => $sign * $rest];

        return $this->books->post([
            'type' => $invoice->isCreditNote() ? 'credit_note' : 'sales', 'date' => $invoice->issue_date, 'reference' => $invoice->number,
            'narration' => $invoice->kindLabel() . ' ' . $invoice->number . ' — ' . $invoice->party->name, 'currency' => $invoice->currency,
            'source_type' => 'invoice', 'source_id' => $invoice->id, 'created_by' => $invoice->finalized_by,
        ], $lines);
    }

    /** Money received: into cash or the bank, the TDS the client kept as receivable, their deductions as cost; the client credited with it all. */
    public function receipt(Receipt $receipt): ?Voucher
    {
        $receipt->loadMissing('party.roles');

        return $this->books->post([
            'type' => 'receipt', 'date' => $receipt->receipt_date, 'reference' => $receipt->number,
            'narration' => __('Receipt :no from :party', ['no' => $receipt->number, 'party' => $receipt->party->name]) . ($receipt->reference ? ' · ' . $receipt->reference : ''),
            'currency' => $receipt->currency, 'source_type' => 'receipt', 'source_id' => $receipt->id, 'created_by' => $receipt->created_by,
        ], [
            ['ledger' => $this->chart->money($receipt->mode === 'cash' ? 'cash' : 'bank', $receipt->bank_account_id), 'debit' => (float) $receipt->amount],
            ['ledger' => $this->chart->ledger('tds_receivable'), 'debit' => (float) $receipt->tds_amount, 'note' => $receipt->tds_section],
            ['ledger' => $this->chart->ledger('client_deductions'), 'debit' => (float) $receipt->deduction_amount, 'note' => $receipt->deduction_note],
            ['ledger' => $this->chart->party($receipt->party), 'credit' => (float) $receipt->amount + (float) $receipt->tds_amount + (float) $receipt->deduction_amount],
        ]);
    }

    // ---- The trip and its crew ------------------------------------------------------

    public function advance(TripAdvance $advance): ?Voucher
    {
        $advance->loadMissing(['trip', 'person']);

        return $this->books->post([
            'type' => 'payment', 'date' => $advance->given_on, 'reference' => $advance->reference ?: $advance->trip->trip_number,
            'narration' => __('Trip advance to :name, :trip', ['name' => $advance->person->name, 'trip' => $advance->trip->trip_number]) . ($advance->note ? ' · ' . $advance->note : ''),
            'source_type' => 'trip_advance', 'source_id' => $advance->id, 'created_by' => $advance->created_by,
        ], [
            ['ledger' => $this->chart->person($advance->person), 'debit' => (float) $advance->amount, 'bucket' => 'trip_cash'] + $this->centres($advance->trip),
            ['ledger' => $this->chart->money($advance->mode), 'credit' => (float) $advance->amount] + $this->centres($advance->trip),
        ]);
    }

    /**
     * An approved road entry with money. Spending is a cost of the trip and
     * truck; who paid decides the other side. Money from the party is held
     * by the crew member until settled.
     */
    public function roadEvent(RoadEvent $event): ?Voucher
    {
        if ($event->status !== 'approved' || (float) $event->amount <= 0) {
            return null;
        }
        $event->loadMissing(['trip', 'person']);
        $trip = $event->trip;
        $crew = $event->person ? $this->chart->person($event->person) : null;
        $amount = (float) $event->amount;
        $head = ['date' => $event->event_at ?? $event->created_at, 'reference' => $trip->trip_number, 'source_type' => 'road_event', 'source_id' => $event->id];

        if ($event->type === 'money_received') {
            $from = $event->data['from'] ?? 'other';
            if ($from === 'office' || ! $crew) {
                return null; // money the office sent is the office's advance, recorded there
            }
            $party = $from === 'party' ? $this->payer($trip, $event->data['consignment_id'] ?? null) : null;

            return $this->books->post($head + ['type' => 'receipt', 'narration' => __(':name received :what on :trip', ['name' => $event->person->name, 'what' => $party ? __('money from :party', ['party' => $party->name]) : __('money'), 'trip' => $trip->trip_number])], [
                ['ledger' => $crew, 'debit' => $amount, 'bucket' => 'trip_cash'] + $this->centres($trip),
                ['ledger' => $party ? $this->chart->party($party) : $this->chart->ledger('road_receipts'), 'credit' => $amount] + $this->centres($trip),
            ]);
        }

        $category = EventTypes::category($event->type) ?? 'other';
        $paidBy = match ($event->payment_mode) {
            'cash', 'upi'  => $crew ? ['ledger' => $crew, 'bucket' => 'trip_cash'] : ['ledger' => $this->chart->ledger('cash')],
            'fuel_card'    => ['ledger' => $this->chart->ledger('fuel_card')],
            'fastag'       => ['ledger' => $this->chart->ledger('fastag')],
            'credit'       => ['ledger' => ($vendor = Party::find($event->data['vendor_party_id'] ?? 0)) ? $this->chart->party($vendor) : $this->chart->ledger('vendor_credit')],
            'company_paid' => ['ledger' => $this->chart->ledger('cash')],
            'party_paid'   => null, // the party bore it: no cost of ours
            default        => $event->from_own_pocket && $crew ? ['ledger' => $crew, 'bucket' => 'trip_cash'] : ['ledger' => $this->chart->ledger('cash')],
        };
        if (! $paidBy) {
            return null;
        }
        $what = EventTypes::label($event->type) . ($event->person ? ' — ' . $event->person->name : '') . ', ' . $trip->trip_number;

        return $this->books->post($head + ['type' => 'expense', 'narration' => $what], [
            ['ledger' => $this->chart->expense($category), 'debit' => $amount, 'note' => $event->data['what'] ?? $event->data['pump'] ?? $event->data['plaza'] ?? null] + $this->centres($trip),
            $paidBy + ['credit' => $amount] + $this->centres($trip),
        ]);
    }

    /**
     * A crew member settled: bata to their earnings; the trip cash balance
     * returned in cash, paid to them, or moved to their personal account.
     * Carried forward, it stays in the trip cash bucket.
     */
    public function settlement(TripSettlement $settlement): ?Voucher
    {
        $settlement->loadMissing(['trip', 'person']);
        $trip = $settlement->trip;
        $crew = $this->chart->person($settlement->person);
        $balance = (float) $settlement->balance;
        $lines = [];

        if ((float) $settlement->bata_amount > 0) {
            $lines[] = ['ledger' => $this->chart->ledger('bata'), 'debit' => (float) $settlement->bata_amount] + $this->centres($trip);
            $lines[] = ['ledger' => $crew, 'credit' => (float) $settlement->bata_amount, 'bucket' => 'earnings'] + $this->centres($trip);
        }
        if (abs($balance) >= 0.005) {
            $lines = array_merge($lines, match ($settlement->mode) {
                'cash_returned' => [['ledger' => $this->chart->ledger('cash'), 'debit' => $balance], ['ledger' => $crew, 'credit' => $balance, 'bucket' => 'trip_cash'] + $this->centres($trip)],
                'paid_to_crew'  => [['ledger' => $crew, 'debit' => -$balance, 'bucket' => 'trip_cash'] + $this->centres($trip), ['ledger' => $this->chart->ledger('cash'), 'credit' => -$balance]],
                // To the personal account: what the trip cash held now counts against (or towards) earnings.
                'ledger'        => [['ledger' => $crew, 'debit' => $balance, 'bucket' => 'earnings'] + $this->centres($trip), ['ledger' => $crew, 'credit' => $balance, 'bucket' => 'trip_cash'] + $this->centres($trip)],
                default         => [],
            });
        }

        return $this->books->post([
            'type' => 'journal', 'date' => $settlement->settled_at, 'reference' => $trip->trip_number . ($settlement->reference ? ' · ' . $settlement->reference : ''),
            'narration' => __('Settlement of :name, :trip (:mode)', ['name' => $settlement->person->name, 'trip' => $trip->trip_number, 'mode' => __(TripSettlement::MODES[$settlement->mode] ?? $settlement->mode)]),
            'source_type' => 'settlement', 'source_id' => $settlement->id, 'created_by' => $settlement->settled_by,
        ], $lines);
    }

    /** A cost or income the office added to a trip (also after it closed, D8). */
    public function officeEntry(TripOfficeEntry $entry): ?Voucher
    {
        $entry->loadMissing(['trip', 'party.roles']);
        $amount = (float) $entry->amount;
        $other = $entry->party ? $this->chart->party($entry->party) : $this->chart->ledger('cash');
        $income = $entry->kind === 'income';
        $label = $income ? __(TripOfficeEntry::INCOME[$entry->category] ?? $entry->category) : $this->chart->expense($entry->category)->name;

        return $this->books->post([
            'type' => $income ? 'receipt' : 'expense', 'date' => $entry->entry_date, 'reference' => $entry->reference ?: $entry->trip->trip_number,
            'narration' => $label . ', ' . $entry->trip->trip_number . ($entry->note ? ' · ' . $entry->note : ''),
            'source_type' => 'office_entry', 'source_id' => $entry->id, 'created_by' => $entry->created_by,
        ], $income ? [
            ['ledger' => $other, 'debit' => $amount] + $this->centres($entry->trip),
            ['ledger' => $this->chart->officeIncome($entry->category, $label), 'credit' => $amount] + $this->centres($entry->trip),
        ] : [
            ['ledger' => $this->chart->expense($entry->category), 'debit' => $amount] + $this->centres($entry->trip),
            ['ledger' => $other, 'credit' => $amount] + $this->centres($entry->trip),
        ]);
    }

    // ---- Hired trucks, brokers, the workshop ------------------------------------------

    /** An issued lorry hire challan: the hire as a trip cost, owed to the supplier less TDS, munshiana and commission. */
    public function hireChallan(HireChallan $challan): ?Voucher
    {
        if (! in_array($challan->status, ['issued', 'settled'], true)) {
            return null;
        }
        $challan->loadMissing(['trip', 'supplier.roles', 'broker.roles']);
        $trip = $challan->trip;
        $supplier = $this->chart->party($challan->supplier);
        $hire = (float) $challan->hire_amount;
        $lines = [
            ['ledger' => $this->chart->ledger('lorry_hire'), 'debit' => $hire] + $this->centres($trip),
            ['ledger' => $supplier, 'credit' => $hire] + $this->centres($trip),
            ['ledger' => $supplier, 'debit' => (float) $challan->office_charge, 'note' => __('Office charges')] + $this->centres($trip),
            ['ledger' => $this->chart->ledger('office_charges'), 'credit' => (float) $challan->office_charge] + $this->centres($trip),
            ['ledger' => $supplier, 'debit' => (float) $challan->tds_amount, 'note' => $challan->tds_section] + $this->centres($trip),
            ['ledger' => $this->chart->ledger('tds_payable'), 'credit' => (float) $challan->tds_amount, 'note' => $challan->tds_section] + $this->centres($trip),
        ];
        $commission = (float) $challan->commission_amount;
        if ($commission > 0 && $challan->commission_mode === 'deducted') {
            // Kept from the supplier's hire: owed on to the broker, or ours when there is none.
            $lines[] = ['ledger' => $supplier, 'debit' => $commission, 'note' => __('Commission')] + $this->centres($trip);
            $lines[] = ['ledger' => $challan->broker ? $this->chart->party($challan->broker) : $this->chart->ledger('commission_income'), 'credit' => $commission] + $this->centres($trip);
        } elseif ($commission > 0 && $challan->broker) {
            $lines[] = ['ledger' => $this->chart->ledger('commission_expense'), 'debit' => $commission] + $this->centres($trip);
            $lines[] = ['ledger' => $this->chart->party($challan->broker), 'credit' => $commission] + $this->centres($trip);
        }

        return $this->books->post([
            'type' => 'purchase', 'date' => $challan->challan_date, 'reference' => $challan->number,
            'narration' => __('Lorry hire :no — :supplier, :vehicle, :trip', ['no' => $challan->number, 'supplier' => $challan->supplier->name, 'vehicle' => $challan->vehicle_reg, 'trip' => $trip->trip_number]),
            'source_type' => 'hire_challan', 'source_id' => $challan->id, 'created_by' => $challan->created_by,
        ], $lines);
    }

    /** Money on an issued challan: more owed (detention, extras) or paid / kept (advance, balance, fuel, deductions). */
    public function hireLine(HireChallanLine $line): ?Voucher
    {
        $line->loadMissing(['challan.trip', 'challan.supplier.roles']);
        $challan = $line->challan;
        $supplier = $this->chart->party($challan->supplier);
        $amount = (float) $line->amount;
        $centres = $this->centres($challan->trip);
        [$debit, $credit] = match ($line->kind) {
            'detention', 'extra' => [$this->chart->ledger('lorry_hire'), $supplier],
            'advance', 'payment' => [$supplier, $this->chart->money($line->mode)],
            'fuel'               => [$supplier, $this->chart->ledger('vendor_credit')],
            'deduction'          => [$supplier, $this->chart->ledger('supplier_deductions')],
            default              => [null, null],
        };
        if (! $debit) {
            return null;
        }

        return $this->books->post([
            'type' => in_array($line->kind, ['advance', 'payment'], true) ? 'payment' : 'journal', 'date' => $line->line_date, 'reference' => $line->reference ?: $challan->number,
            'narration' => __(HireChallan::LINE_KINDS[$line->kind][0]) . ' — ' . $challan->supplier->name . ', ' . $challan->number . ($line->note ? ' · ' . $line->note : ''),
            'source_type' => 'hire_line', 'source_id' => $line->id, 'created_by' => $line->created_by,
        ], [['ledger' => $debit, 'debit' => $amount] + $centres, ['ledger' => $credit, 'credit' => $amount] + $centres]);
    }

    /** A commission typed on its own (not the one on a hire challan, which posts with it). */
    public function brokerCommission(BrokerCommission $commission): ?Voucher
    {
        if ($commission->hire_challan_id || ! in_array($commission->direction, ['payable', 'receivable'], true)) {
            return null;
        }
        $commission->loadMissing(['trip', 'broker.roles']);
        $broker = $this->chart->party($commission->broker);
        $amount = (float) $commission->amount;
        $centres = $this->centres($commission->trip, null, $commission->consignment_id);
        $payable = $commission->direction === 'payable';

        return $this->books->post([
            'type' => 'journal', 'date' => $commission->created_at ?? now(), 'reference' => $commission->trip->trip_number,
            'narration' => ($payable ? __('Commission payable to :name', ['name' => $commission->broker->name]) : __('Commission earned from :name', ['name' => $commission->broker->name])) . ', ' . $commission->trip->trip_number,
            'source_type' => 'broker_commission', 'source_id' => $commission->id,
        ], $payable
            ? [['ledger' => $this->chart->ledger('commission_expense'), 'debit' => $amount] + $centres, ['ledger' => $broker, 'credit' => $amount] + $centres]
            : [['ledger' => $broker, 'debit' => $amount] + $centres, ['ledger' => $this->chart->ledger('commission_income'), 'credit' => $amount] + $centres]);
    }

    /** A finished service job: the repair as a cost of the truck, owed to the workshop (or paid in cash). */
    public function serviceJob(ServiceJob $job): ?Voucher
    {
        if ($job->status !== 'done' || (float) $job->total <= 0) {
            return null;
        }
        $job->loadMissing(['truck', 'vendor.roles']);
        $expense = match ($job->kind) {
            'breakdown' => $this->chart->expense('road_repair'),
            'accident'  => $this->chart->ledger('accident_repairs'),
            default     => $this->chart->expense('service'),
        };
        $centres = ['truck_id' => $job->truck_id, 'branch_id' => $job->truck?->branch_id];

        return $this->books->post([
            'type' => 'purchase', 'date' => $job->completed_at ?? $job->job_date, 'reference' => $job->bill_no ?: $job->number,
            'narration' => __('Service job :no, :truck', ['no' => $job->number, 'truck' => $job->truck?->reg_no]) . ($job->vendor ? ' — ' . $job->vendor->name : ''),
            'source_type' => 'service_job', 'source_id' => $job->id, 'created_by' => $job->created_by,
        ], [
            ['ledger' => $expense, 'debit' => (float) $job->total] + $centres,
            ['ledger' => $job->vendor ? $this->chart->party($job->vendor) : $this->chart->ledger('cash'), 'credit' => (float) $job->total] + $centres,
        ]);
    }

    // ---- Helpers -------------------------------------------------------------------------

    /** The cost centres of a line: trip, its truck and branch, the LR. */
    private function centres(?Trip $trip, ?int $branchId = null, ?int $consignmentId = null): array
    {
        return ['trip_id' => $trip?->id, 'truck_id' => $trip?->truck_id, 'branch_id' => $branchId ?? $trip?->branch_id, 'consignment_id' => $consignmentId];
    }

    /** Who paid the crew on the road: the LR's billing party, else the trip's first. */
    private function payer(Trip $trip, mixed $consignmentId): ?Party
    {
        $consignment = $consignmentId ? Consignment::where('trip_id', $trip->id)->find((int) $consignmentId) : null;
        $consignment ??= Consignment::where('trip_id', $trip->id)->whereNotNull('billing_party_id')->orderBy('id')->first();

        return $consignment?->billing_party_id ? Party::with('roles')->find($consignment->billing_party_id) : null;
    }
}
