<?php

namespace App\Modules\Invoicing\Design;

use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\Receipt;
use App\Modules\Invoicing\Services\DocumentData;
use App\Modules\Invoicing\Services\PaymentQr;
use App\Modules\Trips\Models\Consignment;
use App\Platform\Tenancy\CompanyContext;
use App\Support\Money;
use App\Support\NumberWords;

/**
 * What the template editor previews: the company's latest real document of
 * the family when there is one (the design laid over it), otherwise a
 * sample made of our own name, address and bank with an invented client and
 * load — so a company designs its bill before it has issued one. Nothing is
 * written; a sample is only an array for the document views.
 */
class SampleDocuments
{
    public function __construct(private CompanyContext $context, private DocumentData $data) {}

    /** The document array for the family's view, with this design. */
    public function for(string $family, array $design): array
    {
        return match ($family) {
            'lr'      => ($c = Consignment::whereNotNull('lr_number')->latest('id')->first()) ? $this->data->forLr($c, $design) : $this->lr($design),
            'receipt' => ($r = Receipt::latest('id')->first()) ? $this->data->forReceipt($r, $design) : $this->receipt($design),
            default   => $this->invoiceDoc($design),
        };
    }

    /** Whether the preview shows a real document of the company (else a sample). */
    public function hasReal(string $family): bool
    {
        return match ($family) {
            'lr'      => Consignment::whereNotNull('lr_number')->exists(),
            'receipt' => Receipt::exists(),
            default   => Invoice::whereNotNull('finalized_at')->where('doc_type', 'invoice')->exists(),
        };
    }

    /** A bill: the latest issued one (printed as its first copy set), else the sample. */
    private function invoiceDoc(array $design): array
    {
        $invoice = Invoice::whereNotNull('finalized_at')->where('doc_type', 'invoice')->latest('id')->first();
        if (! $invoice) {
            return $this->invoice($design);
        }
        $doc = $this->data->forInvoice($invoice, $design);
        $doc['copies'] = array_map(fn ($c) => $c['label'] !== '' ? __($c['label']) : null, $design['copies']);

        return $doc;
    }

    public function invoice(array $design): array
    {
        $company  = $this->context->company();
        $india    = $company->country === 'IN';
        $currency = $company->currency;
        $money    = fn ($v) => Money::format($v, $currency, $india ? 'in' : 'us');
        $parts    = $this->data->companyParts($currency);
        $rate     = 5;
        $lines    = [
            ['description' => __('Freight: Kochi to Bengaluru'), 'detail' => 'LR 0001 · KL-07-AB-1234 · 18 MT', 'sac' => $india ? '996791' : null, 'qty' => '1', 'unit' => 'trip', 'rate' => 42000, 'discount' => null],
            ['description' => __('Loading charges'), 'detail' => null, 'sac' => $india ? '996791' : null, 'qty' => '1', 'unit' => 'job', 'rate' => 1500, 'discount' => null],
            ['description' => __('Detention (2 days)'), 'detail' => null, 'sac' => $india ? '996791' : null, 'qty' => '2', 'unit' => 'day', 'rate' => 1000, 'discount' => null],
        ];
        $taxable = 0;
        foreach ($lines as &$line) {
            $net = (float) $line['qty'] * $line['rate'];
            $taxable += $net;
            $line += ['taxable' => $money($net), 'tax_rate' => $rate . '%', 'category' => 'S', 'tax' => $money($net * $rate / 100), 'notional' => false, 'amount' => $money($net * (1 + $rate / 100))];
            $line['rate'] = $money($line['rate']);
        }
        unset($line);
        $tax   = round($taxable * $rate / 100, 2);
        $total = $taxable + $tax;
        $taxes = $india
            ? [['label' => 'CGST 2.5%', 'taxable' => $money($taxable), 'amount' => $money($tax / 2), 'charged' => true], ['label' => 'SGST 2.5%', 'taxable' => $money($taxable), 'amount' => $money($tax / 2), 'charged' => true]]
            : [['label' => 'VAT 5%', 'taxable' => $money($taxable), 'amount' => $money($tax), 'charged' => true]];

        return [
            'type' => 'invoice', 'title' => DesignSchema::title($design, $india ? 'tax_invoice' : 'invoice'), 'number' => 'INV/26-27/0001',
            'is_draft' => false, 'is_void' => false, 'issue_date' => now()->format('d-m-Y'), 'due_date' => now()->addDays(30)->format('d-m-Y'),
            'period' => null, 'reference' => 'PO 4471', 'against' => null, 'reason' => null,
            'seller' => $parts['seller'],
            'buyer' => ['name' => __('Sample Cements Pvt Ltd'), 'address' => ['Plot 12, Industrial Area', 'Bengaluru 560058', 'Karnataka'], 'tax_id' => $india ? '29ABCDE1234F1Z5' : null,
                'state' => $india ? 'Karnataka (29)' : null, 'pan' => null, 'phone' => null],
            'bank' => $parts['bank'],
            'place_of_supply' => $india ? 'Karnataka (29)' : null, 'reverse_charge' => $india ? __('No') : null, 'tax_label' => $india ? 'GST' : 'VAT',
            'lines' => $lines, 'annexure' => [], 'lr_count' => 1, 'taxes' => $taxes,
            'subtotal' => $money($taxable), 'discount' => null, 'taxable_total' => $money($taxable), 'tax_total' => $money($tax), 'tax_notional' => null, 'round_off' => null,
            'total' => $money($total), 'total_words' => NumberWords::amount($total, $currency), 'advance_less' => null, 'paid' => null, 'balance' => $money($total),
            'tds' => $india ? __('TDS @:rate% (:section) to be deducted by you: :amount', ['rate' => 2, 'section' => '194C', 'amount' => $money($taxable * 0.02)]) : null,
            'legal_texts' => [], 'tax_error' => null, 'pending_evidence' => false,
            'qr' => PaymentQr::for($parts['bank'], $currency, $total, 'INV/26-27/0001', $parts['seller']['name']),
            'notes' => __('Thank you for your business.'), 'terms' => __('Payment within 30 days.'),
            'copies' => array_map(fn ($c) => $c['label'] !== '' ? __($c['label']) : null, $design['copies']),
            'currency' => $currency, 'design' => $design,
        ];
    }

    public function lr(array $design): array
    {
        $company = $this->context->company();
        $india   = $company->country === 'IN';
        $parts   = $this->data->companyParts($company->currency);

        return [
            'seller' => $parts['seller'], 'number' => 'LR/0001', 'date' => now()->format('d-m-Y'), 'trip' => 'TR-0001', 'vehicle' => 'KL-07-AB-1234',
            'driver' => 'Ramesh K · 98470 12345', 'from' => 'Kochi', 'to' => 'Bengaluru',
            'consignor' => ['name' => __('Sample Traders'), 'address' => ['MG Road, Kochi 682016'], 'tax_id' => $india ? '32ABCDE1234F1Z2' : null, 'phone' => '98470 00000'],
            'consignee' => ['name' => __('Sample Cements Pvt Ltd'), 'address' => ['Plot 12, Industrial Area', 'Bengaluru 560058'], 'tax_id' => $india ? '29ABCDE1234F1Z5' : null, 'phone' => null],
            'billing' => null, 'goods' => __('Cement bags'), 'packages' => '360 ' . __('bags'), 'actual_weight' => '18 MT', 'charged_weight' => '18 MT',
            'invoice' => 'TI/778 · ' . now()->format('d-m-Y'), 'ewaybills' => $india ? ['3410 1234 5678'] : [], 'declared_value' => Money::format(540000, $company->currency, $india ? 'in' : 'us'),
            'risk' => __('At owner’s risk'), 'seal' => null, 'container' => null, 'client_ref' => null,
            'charges' => [__('Freight') => 42000.0, __('Loading') => 1500.0], 'total' => 43500.0, 'advance' => 10000.0,
            'money' => fn ($v) => Money::format($v, $company->currency, $india ? 'in' : 'us'),
            'payment' => __('To be billed'), 'gst_by' => $india ? __('Consignor') : null, 'hazardous' => false, 'remarks' => null,
            'copies' => array_map(fn ($copy) => ['label' => $copy['label'] !== '' ? __($copy['label']) : null] + $copy, $design['copies']),
            'is_cancelled' => false, 'design' => $design,
        ];
    }

    public function receipt(array $design): array
    {
        $company = $this->context->company();
        $india   = $company->country === 'IN';
        $money   = fn ($v) => Money::format($v, $company->currency, $india ? 'in' : 'us');
        $parts   = $this->data->companyParts($company->currency);

        return [
            'number' => 'RC/0001', 'date' => now()->format('d-m-Y'), 'is_void' => false, 'seller' => $parts['seller'],
            'buyer' => ['name' => __('Sample Cements Pvt Ltd'), 'address' => [], 'tax_id' => null],
            'amount' => $money(44100), 'words' => NumberWords::amount(44100, $company->currency), 'tds' => $india ? $money(900) . ' (194C)' : null, 'deduction' => null,
            'gross' => $money(45000), 'mode' => __(Receipt::MODES['neft']) . ' · UTR 1234567890', 'cheque' => false, 'narration' => null,
            'applied' => [['number' => 'INV/26-27/0001', 'date' => now()->format('d-m-Y'), 'amount' => $money(45000), 'tds' => $india ? $money(900) : null]],
            'unapplied' => null, 'design' => $design,
        ];
    }
}
