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
