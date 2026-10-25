<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\Receipt;
use App\Modules\Invoicing\Services\DocumentRenderer;
use App\Modules\Invoicing\Services\ReceiptService;
use App\Modules\Parties\Models\Party;
use App\Modules\Settings\Models\CompanyBankAccount;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Money received: the receipt register, a new receipt for a client (applied
 * to their open bills, oldest first or by hand, with the TDS they deducted
 * and their deductions), the receipt and its print, void.
 */
class ReceiptController extends Controller
{
    public function __construct(private CompanyContext $context, private ReceiptService $receipts, private DocumentRenderer $renderer) {}

    public function index(Request $request): View
    {
        $f = $request->validate(['party' => ['nullable', 'integer'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d']]);
        $query = Receipt::query()->when($f['party'] ?? null, fn ($q, $id) => $q->where('party_id', $id))
            ->when($f['from'] ?? null, fn ($q, $d) => $q->where('receipt_date', '>=', $d))->when($f['to'] ?? null, fn ($q, $d) => $q->where('receipt_date', '<=', $d));

        return view('receipts.index', [
            'receipts' => (clone $query)->with('party:id,name')->orderByDesc('receipt_date')->orderByDesc('id')->paginate(50)->withQueryString(),
            'totals'   => (clone $query)->where('status', 'posted')->selectRaw('count(*) as n, coalesce(sum(amount),0) as amount, coalesce(sum(tds_amount),0) as tds')->first(),
            'filters'  => $f,
            'parties'  => Party::active()->withRole('client')->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(Request $request): View
    {
        $invoice = $request->filled('invoice') ? Invoice::findOrFail((int) $request->query('invoice')) : null;
        $party   = $invoice?->party ?? ($request->filled('party') ? Party::findOrFail((int) $request->query('party')) : null);

        return view('receipts.create', [
            'party'   => $party,
            'invoice' => $invoice,
            'parties' => Party::active()->withRole('client')->orderBy('name')->get(['id', 'name']),
            'open'    => $party ? Invoice::where('party_id', $party->id)->whereIn('status', Invoice::OPEN_STATUSES)->where('doc_type', '!=', 'credit_note')
                ->where('balance_due', '>', 0)->orderBy('due_date')->get() : collect(),
            'banks'   => CompanyBankAccount::where('active', true)->orderByDesc('is_default')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'party_id'         => ['required', 'integer', Rule::exists('parties', 'id')->where('company_id', $this->context->id())],
            'receipt_date'     => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'amount'           => ['required', 'numeric', 'min:0', 'max:999999999'],
            'tds_amount'       => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'tds_section'      => ['nullable', 'string', 'max:10'],
            'deduction_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'deduction_note'   => ['nullable', 'string', 'max:255'],
            'mode'             => ['required', Rule::in(array_keys(Receipt::MODES))],
            'reference'        => ['nullable', 'required_unless:mode,cash', 'string', 'max:60'],
            'cheque_date'      => ['nullable', 'required_if:mode,cheque', 'date_format:Y-m-d'],
            'bank_name'        => ['nullable', 'string', 'max:120'],
            'bank_account_id'  => ['nullable', 'integer', Rule::exists('company_bank_accounts', 'id')->where('company_id', $this->context->id())],
            'narration'        => ['required', 'string', 'min:3', 'max:255'],
            'allocation'       => ['required', Rule::in(['oldest', 'manual'])],
            'apply'            => ['nullable', 'array'], 'apply.*' => ['nullable', 'numeric', 'min:0'],
        ], ['reference.required_unless' => __('Give the UTR, cheque or UPI reference.'), 'narration.required' => __('Write what the money is for.')]);

        $party = Party::findOrFail($data['party_id']);
        $data['allocations'] = $data['allocation'] === 'manual' ? array_filter($data['apply'] ?? [], fn ($v) => (float) $v > 0) : null;
        $receipt = $this->receipts->record($party, $data, $request->user());

        return redirect()->route('receipts.show', $receipt)->with('status', __('Receipt :number recorded.', ['number' => $receipt->number]));
    }

    public function show(Receipt $receipt): View
    {
        $receipt->load(['party:id,name,email', 'allocations.invoice:id,number,issue_date,total,balance_due,status']);

        return view('receipts.show', ['receipt' => $receipt, 'canPdf' => $this->renderer->canPdf(),
            'email' => ! $receipt->isVoid() && $receipt->public_token ? app(\App\Modules\Invoicing\Services\Messages::class)->render('receipt', 'email', $receipt) + ['to' => $receipt->party->email] : null]);
    }

    /** Email the receipt to the client (M18). */
    public function email(Request $request, Receipt $receipt, \App\Modules\Invoicing\Services\DocumentMailer $mailer): RedirectResponse
    {
        $data = $request->validate(['to' => ['required', 'email:filter', 'max:255'], 'subject' => ['required', 'string', 'max:200'], 'body' => ['required', 'string', 'max:5000']]);
        $sent = $mailer->send($receipt, [$data['to']], [], $data['subject'], $data['body'], $request->user());

        return back()->with($sent ? 'status' : 'error', $sent ? __('Sent to :to.', ['to' => $data['to']]) : __('The mail could not be sent. Check the address and try again.'));
    }

    public function print(Receipt $receipt): Response
    {
        return response($this->renderer->receiptHtml($receipt));
    }

    public function pdf(Receipt $receipt): Response|RedirectResponse
    {
        if (! $this->renderer->canPdf()) {
            return redirect()->route('receipts.print', $receipt);
        }

        return DocumentRenderer::pdfResponse($this->renderer->pdf($this->renderer->receiptHtml($receipt, true)), 'Receipt-' . str_replace('/', '-', $receipt->number));
    }

    public function void(Request $request, Receipt $receipt): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->receipts->void($receipt, $data['reason'], $request->user());

        return redirect()->route('receipts.show', $receipt)->with('status', __('Receipt voided; its bills are open again.'));
    }
}
