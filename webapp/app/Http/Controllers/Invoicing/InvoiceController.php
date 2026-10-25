<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\InvoiceLine;
use App\Modules\Invoicing\Services\DocumentRenderer;
use App\Modules\Invoicing\Services\InvoiceService;
use App\Modules\Parties\Models\Party;
use App\Modules\Tax\Models\TaxCode;
use App\Modules\Tax\Models\TaxRegistration;
use App\Modules\Tax\Services\ServiceClasses;
use App\Modules\Trips\Models\Consignment;
use App\Platform\Tenancy\CompanyContext;
use App\Support\Regions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Bills: the register with its tabs and totals, a new bill from a client's
 * unbilled LRs (one, or many for a consolidated bill), the builder of a
 * draft, issuing, the bill page with its printed document, notes against it,
 * void, the PDF and the bill-submission record.
 */
class InvoiceController extends Controller
{
    public const TABS = ['open' => 'Open', 'overdue' => 'Overdue', 'draft' => 'Drafts', 'paid' => 'Paid', 'void' => 'Void', 'all' => 'All'];

    public function __construct(private CompanyContext $context, private InvoiceService $invoices, private DocumentRenderer $renderer) {}

    public function index(Request $request): View
    {
        $f = $request->validate(['tab' => ['nullable', Rule::in(array_keys(self::TABS))], 'party' => ['nullable', 'integer'], 'q' => ['nullable', 'string', 'max:40'],
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d']]);
        $tab = $f['tab'] ?? 'open';
        $today = now($this->context->company()->timezone ?: 'UTC')->toDateString();

        $query = Invoice::query()
            ->when($tab === 'open', fn ($q) => $q->whereIn('status', Invoice::OPEN_STATUSES)->where('doc_type', '!=', 'credit_note'))
            ->when($tab === 'overdue', fn ($q) => $q->whereIn('status', Invoice::OPEN_STATUSES)->where('balance_due', '>', 0)->where('due_date', '<', $today))
            ->when($tab === 'draft', fn ($q) => $q->where('status', 'draft'))
            ->when($tab === 'paid', fn ($q) => $q->whereIn('status', ['paid', 'issued']))
            ->when($tab === 'void', fn ($q) => $q->where('status', 'void'))
            ->when($f['party'] ?? null, fn ($q, $id) => $q->where('party_id', $id))
            ->when(filled($f['q'] ?? null), fn ($q) => $q->where('number', 'like', '%' . addcslashes(strtoupper($f['q']), '%_\\') . '%'))
            ->when($f['from'] ?? null, fn ($q, $d) => $q->where('issue_date', '>=', $d))
            ->when($f['to'] ?? null, fn ($q, $d) => $q->where('issue_date', '<=', $d));

        $totals = (clone $query)->where('status', '!=', 'void')->where('status', '!=', 'draft')
            ->selectRaw("count(*) as n, coalesce(sum(case when doc_type = 'credit_note' then -total else total end),0) as total, coalesce(sum(balance_due),0) as due")->first();

        return view('invoices.index', [
            'invoices' => $query->with('party:id,name')->orderByDesc('issue_date')->orderByDesc('id')->paginate(50)->withQueryString(),
            'tab'      => $tab,
            'filters'  => $f,
            'totals'   => $totals,
            'parties'  => Party::active()->withRole('client')->orderBy('name')->pluck('name', 'id'),
            'unbilled' => $this->unbilledQuery()->count(),
        ]);
    }

    /** A new bill: choose the client, then tick their LRs waiting to be billed. */
    public function create(Request $request): View
    {
        $party = $request->filled('party') ? Party::findOrFail((int) $request->query('party')) : null;

        return view('invoices.new', [
            'party'   => $party,
            'parties' => Party::active()->withRole('client')->orderBy('name')->get(['id', 'name']),
            'loads'   => $party ? $this->unbilledQuery()->where('billing_party_id', $party->id)
                ->with(['trip:id,trip_number,truck_id,truck_source,supplier_vehicle_id', 'trip.truck:id,reg_no', 'trip.supplierVehicle:id,reg_no', 'pickupStop.place:id,name', 'dropStop.place:id,name'])
                ->orderBy('lr_date')->get() : collect(),
            'ticked'  => array_map('intval', (array) $request->query('loads', [])),
            'counts'  => $this->unbilledQuery()->selectRaw('billing_party_id, count(*) as n')->groupBy('billing_party_id')->pluck('n', 'billing_party_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'party_id' => ['required', 'integer', Rule::exists('parties', 'id')->where('company_id', $this->context->id())],
            'loads'    => ['nullable', 'array', 'max:500'],
            'loads.*'  => ['integer'],
        ]);
        $party = Party::findOrFail($data['party_id']);
        $invoice = empty($data['loads']) || $request->boolean('blank')
            ? $this->invoices->draftBlank($party, $request->user())
            : $this->invoices->draftFromLoads($party, $data['loads'], $request->user());

        return redirect()->route('invoices.edit', $invoice)->with('status', __('Draft made. Check the lines and the tax, then issue it.'));
    }

    /** The builder of a draft. */
    public function edit(Invoice $invoice): View|RedirectResponse
    {
        if (! $invoice->isDraft()) {
            return redirect()->route('invoices.show', $invoice);
        }
        $invoice->load(['lines.consignment:id,lr_number', 'taxes', 'party.taxIds', 'registration', 'related']);
        $company = $this->context->company();

        return view('invoices.builder', [
            'invoice'       => $invoice,
            'registrations' => TaxRegistration::usable()->orderByDesc('is_default')->get(),
            'codes'         => TaxCode::where('active', true)->orderBy('code')->get(['code', 'name', 'category']),
            'classes'       => ServiceClasses::CLASSES,
            'regions'       => Regions::for($company->country),
            'india'         => $company->country === 'IN',
            'addable'       => $invoice->doc_type === 'invoice' ? $this->unbilledQuery()->where('billing_party_id', $invoice->party_id)->get(['id', 'lr_number', 'lr_date', 'freight']) : collect(),
        ]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'issue_date'   => ['required', 'date_format:Y-m-d'],
            'due_date'     => ['required', 'date_format:Y-m-d', 'after_or_equal:issue_date'],
            'reference'    => ['nullable', 'string', 'max:60'],
            'buyer_tax_id' => ['nullable', 'string', 'max:20'],
            'tax_registration_id' => ['nullable', 'integer', Rule::exists('tax_registrations', 'id')->where('company_id', $this->context->id())],
            'transport_type' => ['nullable', Rule::in(['domestic', 'export', 'import', 'international'])],
            'carrier_role' => ['nullable', Rule::in(['main', 'sub'])],
            'loading_state' => ['nullable', 'string', 'max:10'],
            'evidence'     => ['nullable', 'array'], 'evidence.*' => [Rule::in(array_keys(TaxCode::EVIDENCE))],
            'discount_type' => ['required', Rule::in(['none', 'percent', 'amount'])],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'advance_less' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'notes'        => ['nullable', 'string', 'max:2000'],
            'terms'        => ['nullable', 'string', 'max:2000'],
            'reason'       => ['nullable', 'string', 'max:500'],
            'lines'        => ['nullable', 'array', 'max:500'],
            'lines.*.id'   => ['nullable', 'integer'],
            'lines.*.service_class' => ['nullable', Rule::in(array_keys(ServiceClasses::CLASSES))],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.detail' => ['nullable', 'string', 'max:255'],
            'lines.*.unit' => ['nullable', 'string', 'max:12'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'lines.*.unit_rate' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'lines.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.tax_code' => ['nullable', 'string', 'max:20'],
            'lines.*.override_reason' => ['nullable', 'string', 'max:255'],
            'add_loads'    => ['nullable', 'array'], 'add_loads.*' => ['integer'],
        ]);
        $data['inclusive'] = $request->boolean('inclusive');
        $data['evidence'] = $data['evidence'] ?? [];
        $data['discount_value'] = $data['discount_value'] ?? 0;
        $data['advance_less'] = $data['advance_less'] ?? 0;

        DB::transaction(function () use ($invoice, $data) {
            $this->invoices->updateDraft($invoice, $data);
            if (! empty($data['add_loads'])) {
                $this->invoices->addLoads($invoice->fresh(), $data['add_loads']);
            }
        });

        if ($request->input('then') === 'finalise') {
            return $this->finalise($request, $invoice->fresh());
        }

        return redirect()->route('invoices.edit', $invoice)->with('status', __('Saved.'));
    }

    public function finalise(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($request->user()->can('invoice.finalise'), 403);
        $invoice = $this->invoices->finalise($invoice, $request->user());

        return redirect()->route('invoices.show', $invoice)->with('status', __(':doc :number issued.', ['doc' => $invoice->kindLabel(), 'number' => $invoice->number]));
    }

    public function show(Invoice $invoice): View|RedirectResponse
    {
        if ($invoice->isDraft()) {
            return redirect()->route('invoices.edit', $invoice);
        }
        $invoice->load(['party:id,name,mobile,email', 'related:id,number,doc_type', 'notesAgainst:id,related_invoice_id,number,doc_type,status,total,issue_date', 'allocations.receipt:id,number,receipt_date,status,mode']);

        return view('invoices.show', [
            'invoice'   => $invoice,
            'publicUrl' => $invoice->public_token ? route('documents.public', $invoice->public_token) : null,
            'canPdf'    => $this->renderer->canPdf(),
            'deliveries' => \App\Modules\Invoicing\Models\DocumentDelivery::where('document_type', 'invoice')->where('document_id', $invoice->id)->orderByDesc('id')->limit(20)->get(),
        ]);
    }

    /** The printed document itself (the preview frame, and browser printing). */
    public function print(Invoice $invoice): Response
    {
        return response($this->renderer->invoiceHtml($invoice));
    }

    public function pdf(Request $request, Invoice $invoice): Response|RedirectResponse
    {
        if (! $this->renderer->canPdf()) {
            return redirect()->route('invoices.print', $invoice);
        }
        if (! $invoice->isDraft()) {
            $this->invoices->recordDelivery($invoice, 'download', null, $request->user());
        }

        return DocumentRenderer::pdfResponse($this->renderer->pdf($this->renderer->invoiceHtml($invoice, true)), $invoice->fileName());
    }

    public function void(Request $request, Invoice $invoice): RedirectResponse
    {
        if ($invoice->isDraft()) {
            $this->invoices->void($invoice, '', $request->user());

            return redirect()->route('invoices.index', ['tab' => 'draft'])->with('status', __('Draft deleted.'));
        }
        abort_unless($request->user()->can('invoice.void'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->invoices->void($invoice, $data['reason'], $request->user());

        return redirect()->route('invoices.show', $invoice)->with('status', __(':number is void. Its LRs can be billed again.', ['number' => $invoice->number]));
    }

    /** A credit note, debit note or supplementary invoice against an issued bill. */
    public function against(Request $request, Invoice $invoice): RedirectResponse
    {
        $type = $request->validate(['type' => ['required', Rule::in(['credit_note', 'debit_note', 'supplementary'])]])['type'];
        $draft = $type === 'credit_note' ? $this->invoices->draftCreditNote($invoice, $request->user()) : $this->invoices->draftAgainst($invoice, $type, $request->user());

        return redirect()->route('invoices.edit', $draft)->with('status', $type === 'credit_note'
            ? __('Credit note drafted with the bill’s lines: reduce or remove them, give the reason, then issue it.')
            : __('Add the lines (detention, rate difference…), give the reason, then issue it.'));
    }

    /** How and when the bill reached the client (due dates may start from submission). */
    public function submit(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'submitted_via'  => ['required', Rule::in(['hand', 'courier', 'email', 'portal'])],
            'submitted_on'   => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'submission_ref' => ['nullable', 'string', 'max:60'],
        ]);
        $invoice->forceFill($data)->save();

        return back()->with('status', __('Submission recorded.'));
    }

    /**
     * Shared from the bill page: WhatsApp opens with a ready message and the
     * secure link (blueprint 10 §8); the share is kept in the delivery trail.
     */
    public function shared(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate(['channel' => ['required', Rule::in(['link', 'whatsapp'])], 'recipient' => ['nullable', 'string', 'max:20']]);
        abort_unless($invoice->isFinal() && $invoice->public_token, 404);
        $mobile = preg_replace('/\D/', '', (string) ($data['recipient'] ?? $invoice->party->mobile));
        $this->invoices->recordDelivery($invoice, $data['channel'], $mobile ?: null, $request->user());

        if ($data['channel'] === 'whatsapp') {
            $text = __(':doc :number from :company for :amount. View or download: :link', [
                'doc' => $invoice->kindLabel(), 'number' => $invoice->number, 'company' => $this->context->company()->name,
                'amount' => \App\Support\Money::company($invoice->balance_due > 0 ? $invoice->balance_due : $invoice->total), 'link' => route('documents.public', $invoice->public_token),
            ]);

            return redirect()->away('https://wa.me/' . $mobile . '?text=' . rawurlencode($text));
        }

        return back()->with('status', __('Link marked as shared.'));
    }

    /** LRs with a number, not cancelled, not billed yet and not on a live draft. */
    private function unbilledQuery()
    {
        return Consignment::query()->whereNotNull('lr_number')->whereNull('invoice_id')->whereIn('status', ['booked', 'picked_up', 'in_transit', 'delivered', 'pod_received'])
            ->whereNotIn('id', InvoiceLine::query()->whereNotNull('consignment_id')->whereHas('invoice', fn ($q) => $q->where('status', 'draft'))->select('consignment_id'));
    }
}
