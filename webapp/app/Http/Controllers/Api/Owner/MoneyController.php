<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Models\LedgerAccount;
use App\Modules\Accounts\Services\Statements;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Services\InvoiceService;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The owner app's money screens (blueprint 05 §6.2): bills with what is
 * still due, sharing a bill on WhatsApp (its secure link, the same as the
 * web's), and the ledgers with their statements. Read only, except marking a
 * bill as shared. Making LRs, bills and receipts from the phone comes in the
 * next API version.
 */
class MoneyController extends Controller
{
    public const TABS = ['open', 'overdue', 'paid', 'all'];

    public function __construct(private CompanyContext $context) {}

    public function invoices(Request $request): JsonResponse
    {
        $f = $request->validate(['tab' => ['nullable', Rule::in(self::TABS)], 'q' => ['nullable', 'string', 'max:40'], 'page' => ['nullable', 'integer', 'min:1']]);
        $tab = $f['tab'] ?? 'open';
        $today = $this->today()->toDateString();

        $invoices = Invoice::with('party:id,name,mobile')->whereNotNull('finalized_at')
            ->when($tab === 'open', fn ($q) => $q->whereIn('status', Invoice::OPEN_STATUSES)->where('doc_type', '!=', 'credit_note'))
            ->when($tab === 'overdue', fn ($q) => $q->whereIn('status', Invoice::OPEN_STATUSES)->where('balance_due', '>', 0)->where('due_date', '<', $today))
            ->when($tab === 'paid', fn ($q) => $q->whereIn('status', ['paid', 'issued']))
            ->when(filled($f['q'] ?? null), fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', '%' . addcslashes(strtoupper($f['q']), '%_\\') . '%')
                ->orWhereHas('party', fn ($p) => $p->where('name', 'like', '%' . addcslashes($f['q'], '%_\\') . '%'))))
            ->orderByDesc('issue_date')->orderByDesc('id')->paginate(30);

        return response()->json([
            'invoices' => collect($invoices->items())->map(fn (Invoice $i) => $this->row($i, $today))->values(),
            'page' => $invoices->currentPage(), 'more' => $invoices->hasMorePages(),
        ]);
    }

    public function invoice(Invoice $invoice): JsonResponse
    {
        abort_unless($invoice->finalized_at !== null, 404);
        $invoice->load(['party:id,name,mobile', 'lines']);
        $today = $this->today()->toDateString();

        return response()->json($this->row($invoice, $today) + [
            'subtotal' => (float) $invoice->subtotal, 'tax' => (float) $invoice->tax_total, 'tax_reverse_charge' => (float) $invoice->tax_notional, 'paid' => (float) $invoice->amount_paid,
            'lines' => $invoice->lines->map(fn ($l) => ['description' => $l->description, 'quantity' => (float) $l->quantity, 'rate' => (float) $l->unit_rate, 'tax' => (float) $l->tax_amount, 'amount' => (float) $l->line_total])->values(),
        ]);
    }

    /**
     * The app shares the bill itself (WhatsApp or any app) and tells the
     * server, which notes it the way the web does. The answer has the text and
     * the WhatsApp link ready.
     */
    public function shared(Request $request, Invoice $invoice, InvoiceService $invoices): JsonResponse
    {
        $data = $request->validate(['channel' => ['required', Rule::in(['link', 'whatsapp'])], 'recipient' => ['nullable', 'string', 'max:20']]);
        abort_unless($invoice->isFinal() && $invoice->public_token, 404);
        $mobile = preg_replace('/\D/', '', (string) ($data['recipient'] ?? $invoice->party?->mobile)) ?: null;
        $invoices->recordDelivery($invoice, $data['channel'], $mobile, $request->user());
        $text = $this->shareText($invoice);

        return response()->json(['status' => $invoice->fresh()->status, 'text' => $text, 'whatsapp_url' => $mobile ? 'https://wa.me/' . $mobile . '?text=' . rawurlencode($text) : null]);
    }

    /** Every ledger with a balance, by kind (party, crew member, bank, cash), as of today. */
    public function ledgers(Request $request, Statements $statements): JsonResponse
    {
        $f = $request->validate(['kind' => ['nullable', Rule::in(array_keys(LedgerAccount::KINDS))]]);
        $rows = $statements->balances($this->today())
            ->when($f['kind'] ?? null, fn ($c, $k) => $c->where('kind', $k));

        return response()->json(['ledgers' => $rows->map(fn ($l) => [
            'id' => $l->id, 'name' => $l->name, 'kind' => $l->kind, 'group' => $l->group, 'nature' => $l->nature,
            // Positive: they owe us (a debit balance); negative: we owe them.
            'balance' => round((float) $l->balance, 2),
        ])->values()]);
    }

    public function statement(Request $request, LedgerAccount $ledger, Statements $statements): JsonResponse
    {
        $f = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $today = $this->today();
        $to = isset($f['to']) ? CarbonImmutable::parse($f['to']) : $today;
        $from = isset($f['from']) ? CarbonImmutable::parse($f['from']) : $to->subDays(90);
        $s = $statements->ledger($ledger, $from, $to);

        return response()->json([
            'ledger' => ['id' => $ledger->id, 'name' => $ledger->name, 'kind' => $ledger->kind], 'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'opening' => round($s['opening'], 2), 'debit' => round($s['debit'], 2), 'credit' => round($s['credit'], 2), 'closing' => round($s['closing'], 2),
            'buckets' => $ledger->kind === 'person' ? $statements->crewBuckets($ledger, $to) : null,
            'lines' => $s['rows']->map(fn ($r) => [
                'date' => (string) $r->voucher_date, 'number' => $r->number, 'reference' => $r->reference, 'narration' => $r->note ?: $r->narration,
                'debit' => round((float) $r->debit, 2), 'credit' => round((float) $r->credit, 2), 'balance' => round((float) $r->balance, 2),
                'bucket' => $r->bucket, 'cancelled' => $r->status === 'cancelled',
            ])->values(),
        ]);
    }

    private function row(Invoice $i, string $today): array
    {
        return [
            'id' => $i->id, 'number' => $i->number, 'type' => $i->doc_type, 'type_label' => $i->kindLabel(), 'status' => $i->status,
            'party' => $i->party?->name, 'party_mobile' => $i->party?->mobile,
            'issue_date' => $i->issue_date?->toDateString(), 'due_date' => $i->due_date?->toDateString(),
            'overdue_days' => $i->balance_due > 0 && $i->due_date && $i->due_date->toDateString() < $today ? (int) $i->due_date->diffInDays(CarbonImmutable::parse($today)) : 0,
            'currency' => $i->currency, 'total' => (float) $i->total, 'balance_due' => (float) $i->balance_due,
            'share_url' => $i->isFinal() && $i->public_token ? route('documents.public', $i->public_token) : null,
            'pdf_url' => $i->isFinal() && $i->public_token ? route('documents.public.pdf', $i->public_token) : null,
        ];
    }

    /** Today on the company's clock. */
    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->context->company()->timezone ?: 'UTC')->startOfDay();
    }

    private function shareText(Invoice $invoice): string
    {
        return __(':doc :number from :company for :amount. View or download: :link', [
            'doc' => $invoice->kindLabel(), 'number' => $invoice->number, 'company' => $this->context->company()->name,
            'amount' => \App\Support\Money::company($invoice->balance_due > 0 ? $invoice->balance_due : $invoice->total), 'link' => route('documents.public', $invoice->public_token),
        ]);
    }
}
