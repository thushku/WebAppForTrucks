<?php

namespace App\Modules\Invoicing\Services;

use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\Receipt;
use App\Modules\Trips\Models\Consignment;
use Illuminate\Http\Response;

/**
 * A document as one self-contained HTML page with print CSS (ported from
 * Clinic Ledger). The same page is the preview, the browser print, the
 * public link and — printed by Chromium on the server — the PDF.
 */
class DocumentRenderer
{
    public function __construct(private DocumentData $data, private PdfRenderer $pdf) {}

    public function invoiceHtml(Invoice $invoice, bool $embed = false): string
    {
        return view('documents.invoice', ['doc' => $this->data->forInvoice($invoice), 'embed' => $embed])->render();
    }

    public function lrHtml(Consignment $consignment, bool $embed = false): string
    {
        return view('documents.lr', ['doc' => $this->data->forLr($consignment), 'embed' => $embed])->render();
    }

    public function receiptHtml(Receipt $receipt, bool $embed = false): string
    {
        return view('documents.receipt', ['doc' => $this->data->forReceipt($receipt), 'embed' => $embed])->render();
    }

    /**
     * A quotation on the letterhead (M21). On the client's link (M24) it also
     * carries the accept / decline form while it can still be answered.
     */
    public function quotationHtml(\App\Modules\Invoicing\Models\Quotation $quotation, ?string $answerUrl = null, ?string $answered = null): string
    {
        $quotation->load(['party', 'lines.from', 'lines.to']);
        $p = $quotation->party;
        $company = app(\App\Platform\Tenancy\CompanyContext::class)->company();

        return view('documents.quotation', [
            'quotation' => $quotation, 'seller' => $this->data->letterhead(),
            'partyAddress' => array_values(array_filter([$p->address_line1, $p->address_line2, trim(implode(' ', array_filter([$p->city, $p->postal_code])))])),
            'taxId' => \App\Modules\Parties\Models\PartyTaxId::where('party_id', $p->id)->orderByDesc('is_default')->value('tax_id'),
            'taxNote' => $company->country === 'IN' ? __('GST is charged on the bill as the law applies to the service (forward charge or reverse charge).') : __('VAT is charged on the bill as applicable.'),
            'tz' => $company->timezone ?: 'UTC',
            'materials' => \App\Modules\Settings\Models\MasterItem::ofList('material')->pluck('name', 'code'),
            'truckTypes' => \App\Modules\Settings\Models\MasterItem::ofList('truck_type')->pluck('name', 'code'),
            'answerUrl' => $answerUrl, 'answered' => $answered,
        ])->render();
    }

    public function canPdf(): bool
    {
        return PdfRenderer::available();
    }

    public function pdf(string $html): string
    {
        return $this->pdf->fromHtml($html);
    }

    public static function pdfResponse(string $bytes, string $filename): Response
    {
        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . str_replace(['"', '/', '\\'], '-', $filename) . '.pdf"',
            'Content-Length'      => (string) strlen($bytes),
        ]);
    }
}
