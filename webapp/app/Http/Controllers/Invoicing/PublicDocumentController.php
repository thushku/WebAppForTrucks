<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\Receipt;
use App\Modules\Invoicing\Services\DocumentLinks;
use App\Modules\Invoicing\Services\DocumentRenderer;
use App\Modules\Invoicing\Services\InvoiceService;
use App\Platform\Tenancy\Company;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\Response;

/**
 * A document by its secure link, /d/{token}, for the client who received it
 * (blueprint 10 §8): no sign-in; the token names the company and the
 * document. Drafts never have a link; a void document shows VOID. Opening an
 * invoice marks it viewed. Throttled; never indexed.
 */
class PublicDocumentController extends Controller
{
    public function __construct(private DocumentLinks $links, private CompanyContext $context, private DocumentRenderer $renderer) {}

    public function show(string $token): Response
    {
        return $this->render($token, false);
    }

    public function pdf(string $token): Response
    {
        return $this->render($token, true);
    }

    private function render(string $token, bool $pdf): Response
    {
        $link = $this->links->find($token) ?? abort(404);
        $company = Company::find($link->company_id);
        abort_unless($company?->isUsable(), 404);

        return $this->context->runAs($company, function () use ($link, $pdf) {
            if ($link->document_type === 'invoice') {
                $invoice = Invoice::whereKey($link->document_id)->whereNotNull('finalized_at')->firstOrFail();
                if (! $pdf) {
                    app(InvoiceService::class)->markViewed($invoice);
                }
                $html = $this->renderer->invoiceHtml($invoice, $pdf);
                $name = $invoice->fileName();
            } else {
                $receipt = Receipt::findOrFail($link->document_id);
                $html = $this->renderer->receiptHtml($receipt, $pdf);
                $name = 'Receipt-' . str_replace('/', '-', $receipt->number);
            }

            if ($pdf && $this->renderer->canPdf()) {
                return DocumentRenderer::pdfResponse($this->renderer->pdf($html), $name);
            }

            return response($html, 200, ['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'private, no-store']);
        });
    }
}
