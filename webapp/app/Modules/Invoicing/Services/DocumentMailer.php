<?php

namespace App\Modules\Invoicing\Services;

use App\Models\Account;
use App\Modules\Invoicing\Models\DocumentDelivery;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\Receipt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * A bill or a receipt by email (blueprint 10 "Email: PDF attached + secure
 * link"): plain text from Settings → Messages, the PDF attached when the
 * server can make one, the reply going to the company. Every send — or
 * failure — is kept with the document's deliveries.
 */
class DocumentMailer
{
    public function __construct(private DocumentRenderer $renderer, private InvoiceService $invoices) {}

    /**
     * @param string[] $to
     * @param string[] $cc
     * @return bool sent
     */
    public function send(Invoice|Receipt $doc, array $to, array $cc, string $subject, string $body, ?Account $by, bool $attach = true): bool
    {
        if (! $doc->public_token || ($doc instanceof Invoice && ! $doc->isFinal()) || ($doc instanceof Receipt && $doc->status === 'void')) {
            throw ValidationException::withMessages(['email' => __('Only an issued document can be sent.')]);
        }
        $to = array_values(array_unique(array_filter(array_map(fn ($e) => mb_strtolower(trim($e)), $to))));
        if ($to === []) {
            throw ValidationException::withMessages(['to' => __('Give at least one email address.')]);
        }

        $pdf = null;
        if ($attach && $this->renderer->canPdf()) {
            try {
                $pdf = $this->renderer->pdf($doc instanceof Invoice ? $this->renderer->invoiceHtml($doc, true) : $this->renderer->receiptHtml($doc, true));
            } catch (\Throwable $e) {
                // The link is in the message; the mail still goes.
                Log::warning('PDF for email failed', ['document' => $doc->id, 'error' => $e->getMessage()]);
            }
        }
        $company = app(\App\Platform\Tenancy\CompanyContext::class)->company();
        $file = ($doc instanceof Invoice ? $doc->fileName() : 'Receipt-' . str_replace(['/', '\\', ' '], '-', (string) $doc->number)) . '.pdf';

        $error = null;
        try {
            Mail::raw($body, function ($m) use ($to, $cc, $subject, $company, $pdf, $file) {
                $m->to($to)->subject($subject);
                if ($cc) {
                    $m->cc($cc);
                }
                if ($company->email) {
                    $m->replyTo($company->email, $company->name);
                }
                if ($pdf !== null) {
                    $m->attachData($pdf, $file, ['mime' => 'application/pdf']);
                }
            });
        } catch (\Throwable $e) {
            $error = mb_substr($e->getMessage(), 0, 500);
            Log::warning('Document email failed', ['document' => $doc->id, 'error' => $error]);
        }

        $type = $doc instanceof Invoice ? 'invoice' : 'receipt';
        $recipient = implode(', ', array_merge($to, $cc));
        if ($error === null && $doc instanceof Invoice) {
            $this->invoices->recordDelivery($doc, 'email', $recipient, $by);
        } else {
            DocumentDelivery::create(['document_type' => $type, 'document_id' => $doc->id, 'channel' => 'email', 'recipient' => mb_substr($recipient, 0, 255),
                'status' => $error ? 'failed' : 'sent', 'error' => $error, 'sent_by' => $by?->id, 'sent_at' => now()]);
        }

        return $error === null;
    }
}
