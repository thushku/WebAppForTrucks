<?php

namespace App\Modules\Invoicing\Services;

use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\MessageTemplate;
use App\Modules\Invoicing\Models\Receipt;
use App\Platform\Tenancy\CompanyContext;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * The words sent with a document (blueprint 10 "Message templates"): per
 * purpose and channel, the company's own wording or the shipped one, with
 * {placeholders} filled from the document. Plain text only.
 */
class Messages
{
    public const PURPOSES = ['invoice' => 'Sending a bill', 'receipt' => 'Sending a receipt', 'reminder' => 'Overdue reminder'];

    public const CHANNELS = ['email' => 'Email', 'whatsapp' => 'WhatsApp'];

    public const PLACEHOLDERS = [
        '{company}' => 'Your company’s name', '{party}' => 'The client’s name', '{document}' => 'Tax invoice, Credit note, Receipt…',
        '{number}' => 'The document’s number', '{amount}' => 'The amount (the balance for a bill)', '{total}' => 'The bill’s total',
        '{date}' => 'The document’s date', '{due_date}' => 'The due date', '{days_overdue}' => 'Days past the due date', '{link}' => 'The secure link',
    ];

    public function __construct(private CompanyContext $context) {}

    /** @return array{subject: ?string, body: string} the company's wording, or the shipped one */
    public function template(string $purpose, string $channel): array
    {
        $own = MessageTemplate::where('purpose', $purpose)->where('channel', $channel)->first();

        return $own ? ['subject' => $own->subject, 'body' => $own->body] : $this->shipped($purpose, $channel);
    }

    /** @return array{subject: ?string, body: string} */
    public function shipped(string $purpose, string $channel): array
    {
        $email = [
            'invoice' => [__('{document} {number} from {company}'), __("Dear {party},\n\nPlease find {document} {number} dated {date} for {amount}, due on {due_date}.\n\nView or download it here: {link}\n\nThank you,\n{company}")],
            'receipt' => [__('Receipt {number} from {company}'), __("Dear {party},\n\nThank you for your payment. Receipt {number} dated {date} for {amount} is here: {link}\n\nRegards,\n{company}")],
            'reminder' => [__('Reminder: {document} {number} is {days_overdue} days overdue'), __("Dear {party},\n\nThis is a reminder that {document} {number} dated {date} is past its due date ({due_date}). {amount} is still due.\n\nYou can see the bill here: {link}\n\nIf you have paid already, please send us the payment details and ignore this message.\n\nRegards,\n{company}")],
        ];
        $whatsapp = [
            'invoice' => __('{document} {number} from {company} for {amount}. View or download: {link}'),
            'receipt' => __('Receipt {number} from {company} for {amount}. Thank you! {link}'),
            'reminder' => __('Reminder from {company}: {document} {number} for {amount} was due on {due_date}. {link}'),
        ];

        return $channel === 'email' ? ['subject' => $email[$purpose][0], 'body' => $email[$purpose][1]] : ['subject' => null, 'body' => $whatsapp[$purpose]];
    }

    /** @return array{subject: ?string, body: string} the wording with this document's values */
    public function render(string $purpose, string $channel, Invoice|Receipt $doc): array
    {
        $template = $this->template($purpose, $channel);
        $values = $this->values($doc);
        $fill = fn (?string $text) => $text === null ? null : strtr($text, $values);

        return ['subject' => $fill($template['subject']), 'body' => $fill($template['body'])];
    }

    /** The same placeholders with sample values, for the preview in Settings → Messages. */
    public function sample(string $purpose, string $channel, ?string $subject, string $body): array
    {
        $company = $this->context->company();
        $values = ['{company}' => $company->name, '{party}' => 'ACC Limited', '{document}' => __('Tax invoice'), '{number}' => 'INV/26-27/00042',
            '{amount}' => Money::company(64000), '{total}' => Money::company(64000), '{date}' => '25 Oct 2026', '{due_date}' => '24 Nov 2026', '{days_overdue}' => '7',
            '{link}' => url('/d/' . str_repeat('x', 40))];

        return ['subject' => $subject === null ? null : strtr($subject, $values), 'body' => strtr($body, $values)];
    }

    /** What the document is called, as printed: "Tax invoice", "Bill of supply", "Credit note"… */
    public function documentName(Invoice $invoice): string
    {
        return match ($invoice->doc_type) {
            'credit_note' => __('Credit note'), 'debit_note' => __('Debit note'), 'supplementary' => __('Supplementary invoice'),
            default => match ($invoice->kind) { 'bill_of_supply' => __('Bill of supply'), 'invoice' => __('Invoice'), default => __('Tax invoice') },
        };
    }

    /** @return array<string, string> */
    public function values(Invoice|Receipt $doc): array
    {
        $company = $this->context->company();
        $tz = $company->timezone ?: 'UTC';
        $link = $doc->public_token ? route('documents.public', $doc->public_token) : '';
        if ($doc instanceof Receipt) {
            $doc->loadMissing('party:id,name');

            return ['{company}' => $company->name, '{party}' => $doc->party?->name ?? '', '{document}' => __('Receipt'), '{number}' => (string) $doc->number,
                '{amount}' => Money::company($doc->amount), '{total}' => Money::company($doc->amount), '{date}' => $doc->receipt_date?->format('d M Y') ?? '',
                '{due_date}' => '', '{days_overdue}' => '', '{link}' => $link];
        }
        $doc->loadMissing('party:id,name');
        // Calendar days on the company's clock (a due date has no time of day).
        $today = CarbonImmutable::parse(CarbonImmutable::now($tz)->toDateString());
        $due = $doc->due_date ? CarbonImmutable::parse($doc->due_date->toDateString()) : null;
        $overdue = $due && $due->lt($today) ? (int) $due->diffInDays($today) : 0;

        return ['{company}' => $company->name, '{party}' => $doc->party?->name ?? '', '{document}' => $this->documentName($doc), '{number}' => (string) $doc->number,
            '{amount}' => Money::company((float) $doc->balance_due > 0 ? $doc->balance_due : $doc->total), '{total}' => Money::company($doc->total),
            '{date}' => $doc->issue_date?->format('d M Y') ?? '', '{due_date}' => $doc->due_date?->format('d M Y') ?? '', '{days_overdue}' => (string) $overdue, '{link}' => $link];
    }
}
