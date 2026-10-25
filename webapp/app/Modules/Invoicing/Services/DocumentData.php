<?php

namespace App\Modules\Invoicing\Services;

use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\Receipt;
use App\Modules\Parties\Models\Party;
use App\Modules\Settings\Models\CompanyBankAccount;
use App\Modules\Tax\Models\TaxRegistration;
use App\Modules\Trips\Models\Consignment;
use App\Platform\Tenancy\Company;
use App\Platform\Tenancy\CompanyContext;
use App\Support\Money;
use App\Support\NumberWords;
use App\Support\Regions;
use Illuminate\Support\Facades\Storage;

/**
 * Everything a printed document needs, as plain arrays (ported from Clinic
 * Ledger). A draft prints from the live company, client and bank; an issued
 * bill prints from the snapshot taken when it was issued, so a changed
 * address or bank account never rewrites what was sent. Lines and totals
 * always come from the bill's own rows, which are fixed once issued.
 */
class DocumentData
{
    public function __construct(private CompanyContext $context) {}

    /** What is frozen at issue: seller (with our registration), buyer (with the GSTIN billed), bank. */
    public function snapshot(Invoice $invoice): array
    {
        $company = $this->context->company();
        $invoice->loadMissing(['party.taxIds', 'registration']);

        return [
            'seller' => $this->seller($company, $invoice->registration),
            'buyer'  => $this->buyer($invoice->party, $invoice->buyer_tax_id),
            'bank'   => $this->bank($invoice->currency),
        ];
    }

    public function forInvoice(Invoice $invoice): array
    {
        $invoice->loadMissing(['lines.consignment.trip', 'lines.consignment.consignor', 'lines.consignment.consignee', 'lines.consignment.pickupStop.place', 'lines.consignment.dropStop.place',
            'taxes', 'party.taxIds', 'registration', 'related']);
        $frozen  = $invoice->snapshot ?: $this->snapshot($invoice);
        $company = $this->context->company();
        $india   = $company->country === 'IN';
        $money   = fn ($v) => Money::format($v, $invoice->currency, $india ? 'in' : 'us');

        $title = match ($invoice->doc_type) {
            'credit_note' => __('Credit note'),
            'debit_note'  => __('Debit note'),
            'supplementary' => $invoice->kind === 'bill_of_supply' ? __('Supplementary bill of supply') : __('Supplementary tax invoice'),
            default       => match ($invoice->kind) {
                'bill_of_supply' => __('Bill of supply'),
                'invoice'        => __('Invoice'),
                default          => __('Tax invoice'),
            },
        };

        // An LR annexure when the bill carries LRs (consolidated freight bills, blueprint 10 §5).
        $annexure = $invoice->lines->whereNotNull('consignment_id')->groupBy('consignment_id')->map(function ($lines) use ($money) {
            $c = $lines->first()->consignment;

            return [
                'lr' => $c->lr_number, 'date' => $c->lr_date?->format('d-m-Y'), 'vehicle' => $c->trip?->vehicleLabel(),
                'from' => $c->pickupStop?->place?->name, 'to' => $c->dropStop?->place?->name,
                'goods' => $c->description ?: ucfirst(str_replace('_', ' ', (string) $c->material)),
                'weight' => $c->weight() ? rtrim(rtrim(number_format($c->weight(), 3), '0'), '.') . ' MT' : null,
                'freight' => $money($lines->where('service_class', 'FREIGHT_ROAD')->sum(fn ($l) => (float) $l->net)),
                'other' => $money($lines->where('service_class', '!=', 'FREIGHT_ROAD')->sum(fn ($l) => (float) $l->net)),
                'total' => $money($lines->sum(fn ($l) => (float) $l->net)),
            ];
        })->values();

        $balance = $invoice->isDraft() ? (float) $invoice->total - (float) $invoice->advance_less : (float) $invoice->balance_due;
        $trace   = $invoice->tax_trace ?? [];

        return [
            'type'        => $invoice->doc_type,
            'title'       => $title,
            'number'      => $invoice->number,
            'is_draft'    => $invoice->isDraft(),
            'is_void'     => $invoice->isVoid(),
            'issue_date'  => $invoice->issue_date->format('d-m-Y'),
            'due_date'    => $invoice->isCreditNote() ? null : $invoice->due_date->format('d-m-Y'),
            'period'      => $invoice->period_from && $invoice->period_to ? $invoice->period_from->format('d-m-Y') . ' – ' . $invoice->period_to->format('d-m-Y') : null,
            'reference'   => $invoice->reference,
            'against'     => $invoice->related ? ['number' => $invoice->related->number, 'date' => $invoice->related->issue_date->format('d-m-Y')] : null,
            'reason'      => $invoice->reason,
            'seller'      => $frozen['seller'],
            'buyer'       => $frozen['buyer'],
            'bank'        => $invoice->isCreditNote() ? null : $frozen['bank'],
            'place_of_supply' => $invoice->place_of_supply ? ($invoice->place_of_supply_name . ($india ? ' (' . $invoice->place_of_supply . ')' : '')) : null,
            'reverse_charge'  => $india ? ($invoice->reverse_charge ? __('Yes') : __('No')) : null,
            'tax_label'   => $india ? 'GST' : 'VAT',
            'lines'       => $invoice->lines->map(fn ($l) => [
                'description' => $l->description, 'detail' => $l->detail, 'sac' => $l->sac_code, 'qty' => rtrim(rtrim(number_format((float) $l->quantity, 3), '0'), '.'),
                'unit' => $l->unit, 'rate' => $money($l->unit_rate), 'discount' => (float) $l->discount_percent ? rtrim(rtrim(number_format((float) $l->discount_percent, 2), '0'), '.') . '%' : null,
                'taxable' => $money($l->taxable), 'tax_rate' => (float) $l->rate_percent ? \App\Modules\Tax\Services\TaxCalculator::pct((float) $l->rate_percent) . '%' : ($l->tax_category === 'E' ? __('Exempt') : ($l->tax_category === 'O' ? __('n/a') : '0%')),
                'category' => $l->tax_category, 'tax' => $money((float) $l->tax_amount + (float) $l->notional_tax), 'notional' => (float) $l->notional_tax > 0, 'amount' => $money($l->line_total),
            ])->values()->all(),
            'annexure'    => $annexure->count() > 1 ? $annexure->all() : [],
            'lr_count'    => $annexure->count(),
            'taxes'       => $invoice->taxes->map(fn ($t) => ['label' => $t->label(), 'taxable' => $money($t->taxable), 'amount' => $money($t->amount), 'charged' => $t->charged])->all(),
            'subtotal'    => $money($invoice->subtotal),
            'discount'    => (float) $invoice->discount_amount ? $money($invoice->discount_amount) : null,
            'taxable_total' => $money($invoice->taxable_total),
            'tax_total'   => $money($invoice->tax_total),
            'tax_notional' => (float) $invoice->tax_notional ? $money($invoice->tax_notional) : null,
            'round_off'   => (float) $invoice->round_off ? $money($invoice->round_off) : null,
            'total'       => $money($invoice->total),
            'total_words' => NumberWords::amount((float) $invoice->total, $invoice->currency),
            'advance_less' => (float) $invoice->advance_less ? $money($invoice->advance_less) : null,
            'paid'        => (float) $invoice->amount_paid ? $money($invoice->amount_paid) : null,
            'balance'     => $invoice->isCreditNote() ? null : $money($balance),
            'tds'         => $invoice->withholding && (float) ($invoice->withholding['amount'] ?? 0) > 0
                ? __('TDS @:rate% (:section) to be deducted by you: :amount', ['rate' => rtrim(rtrim(number_format($invoice->withholding['rate'], 2), '0'), '.'), 'section' => $invoice->withholding['label'] ?? '', 'amount' => $money($invoice->withholding['amount'])])
                : (($invoice->withholding['nil_reason'] ?? null) ? __('No TDS: :reason', ['reason' => $invoice->withholding['nil_reason']]) : null),
            'legal_texts' => $trace['legal_texts'] ?? [],
            'tax_error'   => $trace['error'] ?? null,
            'pending_evidence' => (bool) $invoice->pending_evidence,
            'qr'          => $invoice->isCreditNote() || $invoice->isVoid() ? null : PaymentQr::for($frozen['bank'] ?? null, $invoice->currency, $balance, $invoice->number, $frozen['seller']['name'] ?? ''),
            'notes'       => $invoice->notes,
            'terms'       => $invoice->terms,
            // GST Rule 48: a service invoice in two copies (P5).
            'copies'      => $india && ! $invoice->isDraft() ? [__('Original for recipient'), __('Duplicate for supplier')] : [null],
            'currency'    => $invoice->currency,
        ];
    }

    /**
     * An LR / consignment note (blueprint 10 §4) in its copy set: consignor,
     * consignee, driver / lorry, office (P5). Freight hidden on the copies set
     * to hide it (the driver's, by default).
     */
    public function forLr(Consignment $c, array $copies = []): array
    {
        $c->loadMissing(['trip.truck', 'trip.supplierVehicle', 'trip.crew.person', 'consignor.taxIds', 'consignee.taxIds', 'billingParty.taxIds', 'pickupStop.place', 'dropStop.place', 'ewaybills']);
        $company = $this->context->company();
        $india   = $company->country === 'IN';
        $money   = fn ($v) => Money::format($v, $company->currency, $india ? 'in' : 'us');
        $driver  = $c->trip?->crew->firstWhere('role', 'driver')?->person;
        $registration = TaxRegistration::usable()->orderByDesc('is_default')->first();

        $copies = $copies ?: [
            ['label' => __('Consignor copy'), 'hide_freight' => false, 'tint' => '#ffffff'],
            ['label' => __('Consignee copy'), 'hide_freight' => false, 'tint' => '#f5faff'],
            ['label' => __('Driver / lorry copy'), 'hide_freight' => true, 'tint' => '#fffbeb'],
            ['label' => __('Office copy'), 'hide_freight' => false, 'tint' => '#f0fdf4'],
        ];

        return [
            'seller'    => $this->seller($company, $registration),
            'number'    => $c->lr_number,
            'date'      => $c->lr_date?->format('d-m-Y'),
            'trip'      => $c->trip?->trip_number,
            'vehicle'   => $c->trip?->vehicleLabel(),
            'driver'    => $driver ? $driver->name . ($driver->mobile ? ' · ' . $driver->mobile : '') : null,
            'from'      => $c->pickupStop?->place?->name,
            'to'        => $c->dropStop?->place?->name,
            'consignor' => $c->consignor ? $this->buyer($c->consignor, null) : null,
            'consignee' => $c->consignee ? $this->buyer($c->consignee, null) : null,
            'billing'   => $c->billing_party_id !== $c->consignor_party_id && $c->billing_party_id !== $c->consignee_party_id ? $this->buyer($c->billingParty, null) : null,
            'goods'     => $c->description ?: ucfirst(str_replace('_', ' ', (string) $c->material)),
            'packages'  => $c->packages ? $c->packages . ' ' . ($c->package_type ?? '') : null,
            'actual_weight'  => $c->actual_weight_mt !== null ? rtrim(rtrim(number_format((float) $c->actual_weight_mt, 3), '0'), '.') . ' MT' : null,
            'charged_weight' => $c->charged_weight_mt !== null ? rtrim(rtrim(number_format((float) $c->charged_weight_mt, 3), '0'), '.') . ' MT' : null,
            'invoice'   => $c->consignor_invoice_no ? $c->consignor_invoice_no . ($c->consignor_invoice_date ? ' · ' . $c->consignor_invoice_date->format('d-m-Y') : '') . ($c->consignor_invoice_value ? ' · ' . $money($c->consignor_invoice_value) : '') : null,
            'ewaybills' => $c->ewaybills->map(fn ($e) => $e->number . ($e->valid_until ? ' (' . __('valid till :d', ['d' => $e->valid_until->format('d-m-Y H:i')]) . ')' : ''))->all(),
            'declared_value' => $c->declared_value ? $money($c->declared_value) : null,
            'risk'      => $c->risk === 'carrier' ? __('At carrier’s risk') : __('At owner’s risk'),
            'seal'      => $c->seal_no,
            'container' => $c->container_no,
            'client_ref' => $c->client_ref,
            'charges'   => array_filter([
                __('Freight') => (float) $c->freight, __('Loading') => (float) $c->loading_charge, __('Unloading') => (float) $c->unloading_charge, __('Other charges') => (float) $c->other_charges,
            ]),
            'total'     => $c->totalCharges(),
            'advance'   => (float) $c->advance_from_party,
            'money'     => $money,
            'payment'   => __(Consignment::PAYMENT_TYPES[$c->payment_type] ?? $c->payment_type),
            'gst_by'    => $india && $c->gst_payable_by ? __(Consignment::GST_PAYERS[$c->gst_payable_by] ?? $c->gst_payable_by) : null,
            'hazardous' => (bool) $c->hazardous,
            'remarks'   => $c->remarks,
            'copies'    => $copies,
            'is_cancelled' => $c->status === 'cancelled',
        ];
    }

    public function forReceipt(Receipt $receipt): array
    {
        $receipt->loadMissing(['party.taxIds', 'allocations.invoice']);
        $company = $this->context->company();
        $money   = fn ($v) => Money::format($v, $receipt->currency, $company->country === 'IN' ? 'in' : 'us');

        return [
            'number' => $receipt->number, 'date' => $receipt->receipt_date->format('d-m-Y'), 'is_void' => $receipt->isVoid(),
            'seller' => $this->seller($company, TaxRegistration::usable()->orderByDesc('is_default')->first()),
            'buyer'  => $this->buyer($receipt->party, null),
            'amount' => $money($receipt->amount), 'words' => NumberWords::amount((float) $receipt->amount, $receipt->currency),
            'tds'    => (float) $receipt->tds_amount ? $money($receipt->tds_amount) . ($receipt->tds_section ? ' (' . $receipt->tds_section . ')' : '') : null,
            'deduction' => (float) $receipt->deduction_amount ? $money($receipt->deduction_amount) . ($receipt->deduction_note ? ' — ' . $receipt->deduction_note : '') : null,
            'gross'  => $money($receipt->gross()),
            'mode'   => __(Receipt::MODES[$receipt->mode] ?? $receipt->mode) . ($receipt->reference ? ' · ' . $receipt->reference : '') . ($receipt->cheque_date ? ' · ' . $receipt->cheque_date->format('d-m-Y') : '') . ($receipt->bank_name ? ' · ' . $receipt->bank_name : ''),
            'cheque' => $receipt->mode === 'cheque',
            'narration' => $receipt->narration,
            'applied' => $receipt->allocations->map(fn ($a) => ['number' => $a->invoice->number, 'date' => $a->invoice->issue_date->format('d-m-Y'), 'amount' => $money($a->total()),
                'tds' => (float) $a->tds ? $money($a->tds) : null])->all(),
            'unapplied' => (float) $receipt->unapplied ? $money($receipt->unapplied) : null,
        ];
    }

    // ---- Parts -----------------------------------------------------------------

    private function seller(Company $company, ?TaxRegistration $registration): array
    {
        $india = $company->country === 'IN';

        return [
            'name'       => $company->name,
            'legal_name' => $registration?->legal_name ?: $company->legal_name,
            'address'    => array_values(array_filter($registration?->address ? preg_split('/\R/', $registration->address) : [
                $company->address_line1, $company->address_line2,
                trim(implode(' ', array_filter([$company->city, $company->postal_code]))) ?: null,
                $company->state_code ? (Regions::for($company->country)[$company->state_code] ?? null) : null,
            ])),
            'phone'      => $company->phone,
            'email'      => $company->email,
            'website'    => $company->website,
            'tax_label'  => $india ? 'GSTIN' : 'TRN',
            'tax_id'     => $registration?->tax_id,
            'state'      => $registration?->state_code ? ((Regions::for($company->country)[$registration->state_code] ?? $registration->state_code) . ($india ? ' (' . $registration->state_code . ')' : '')) : null,
            'pan'        => $registration?->pan(),
            'logo'       => $this->logo($company),
        ];
    }

    private function buyer(?Party $party, ?string $taxId): array
    {
        if (! $party) {
            return ['name' => __('(removed)'), 'address' => []];
        }
        $id = $taxId ? $party->taxIds->firstWhere('tax_id', $taxId) : $party->primaryTaxId();

        return [
            'name'    => $party->legal_name ?: $party->name,
            'address' => array_values(array_filter($id?->address ? preg_split('/\R/', $id->address) : [
                $party->address_line1, $party->address_line2,
                trim(implode(' ', array_filter([$party->city, $party->postal_code]))) ?: null,
                $party->state_code ? (Regions::for($party->country)[$party->state_code] ?? null) : null,
            ])),
            'tax_id'  => $id?->tax_id,
            'state'   => $id?->state_code ? (Regions::for($party->country)[$id->state_code] ?? $id->state_code) . ($party->country === 'IN' ? ' (' . $id->state_code . ')' : '') : null,
            'pan'     => $party->pan,
            'phone'   => $party->mobile,
        ];
    }

    private function bank(string $currency): ?array
    {
        $bank = CompanyBankAccount::where('active', true)->where('show_on_invoice', true)
            ->orderByRaw('currency = ? desc', [$currency])->orderByDesc('is_default')->first();

        return $bank ? [
            'bank_name' => $bank->bank_name, 'holder_name' => $bank->holder_name, 'account_number' => $bank->account_number,
            'ifsc' => $bank->ifsc, 'iban' => $bank->iban, 'swift' => $bank->swift, 'branch' => $bank->branch, 'upi_id' => $bank->upi_id,
        ] : null;
    }

    /** The logo inside the page (data URI), so the HTML works alone: PDF, public link, print. */
    private function logo(Company $company): ?string
    {
        if (! $company->logo_path || ! Storage::disk('local')->exists($company->logo_path)) {
            return null;
        }
        $bytes = Storage::disk('local')->get($company->logo_path);
        $mime  = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }
}
