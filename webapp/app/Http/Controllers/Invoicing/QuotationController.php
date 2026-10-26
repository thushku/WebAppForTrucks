<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Modules\Invoicing\Models\Quotation;
use App\Modules\Invoicing\Services\DocumentData;
use App\Modules\Invoicing\Services\DocumentMailer;
use App\Modules\Invoicing\Services\DocumentRenderer;
use App\Modules\Invoicing\Services\Messages;
use App\Modules\Invoicing\Services\Quotations;
use App\Modules\Parties\Models\Party;
use App\Modules\Parties\Models\PartyTaxId;
use App\Modules\Settings\Models\MasterItem;
use App\Modules\Settings\Models\Place;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Quotations / rate confirmations (M21) under Invoices & LR: write one,
 * send it (print, PDF, email), note the client's answer, then turn it into
 * the client's rate contract or start a trip from one of its routes.
 */
class QuotationController extends Controller
{
    public const TABS = ['open' => 'Open', 'accepted' => 'Accepted', 'closed' => 'Declined and cancelled', 'all' => 'All'];

    public function __construct(private CompanyContext $context, private Quotations $quotations) {}

    public function index(Request $request): View
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'open';
        $q = trim((string) $request->query('q'));
        $list = Quotation::with('party:id,name')->withCount('lines')->inBranch()
            ->when($tab === 'open', fn ($b) => $b->whereIn('status', ['draft', 'issued']))
            ->when($tab === 'accepted', fn ($b) => $b->where('status', 'accepted'))
            ->when($tab === 'closed', fn ($b) => $b->whereIn('status', ['declined', 'cancelled']))
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('number', 'like', "%{$q}%")->orWhereHas('party', fn ($p) => $p->where('name', 'like', "%{$q}%"))))
            ->orderByDesc('quote_date')->orderByDesc('id')->paginate(30)->withQueryString();

        return view('quotations.index', ['quotations' => $list, 'tab' => $tab, 'q' => $q, 'today' => $this->today()]);
    }

    public function create(Request $request): View
    {
        $today = $this->today();
        $quotation = new Quotation(['quote_date' => $today, 'valid_until' => $today->addDays(15), 'party_id' => (int) $request->query('party') ?: null,
            'terms' => __("Rates are per the basis shown; taxes extra as applicable.\nDetention after the free hours at the rate shown.\nPayment within 30 days of the bill.")]);

        return view('quotations.form', $this->formData($quotation));
    }

    public function store(Request $request): RedirectResponse
    {
        [$fields, $lines] = $this->validated($request);
        $quotation = $this->quotations->save(null, $fields + ['branch_id' => app(\App\Platform\Tenancy\BranchContext::class)->defaultBranch()], $lines, $request->user());

        return redirect()->route('quotations.show', $quotation)->with('status', __('Draft saved. Print it to check, then send it.'));
    }

    public function show(Quotation $quotation, Messages $messages): View
    {
        $quotation->load(['party', 'lines.from', 'lines.to', 'rateContract']);
        $emails = $quotation->number ? $messages->renderQuotation('email', $quotation) + ['to' => $quotation->party->email] : null;

        return view('quotations.show', [
            'quotation' => $quotation, 'today' => $this->today(), 'email' => $emails,
            'trips' => \App\Modules\Trips\Models\Consignment::with('trip:id,trip_number,trip_date,status')->where('quotation_id', $quotation->id)->latest('id')->limit(20)->get(['id', 'trip_id', 'lr_number', 'freight']),
            'names' => $this->names(),
        ]);
    }

    public function edit(Quotation $quotation): View|RedirectResponse
    {
        if (! $quotation->isDraft()) {
            return redirect()->route('quotations.show', $quotation)->with('error', __('A sent quotation is not changed. Copy it for a new offer.'));
        }

        return view('quotations.form', $this->formData($quotation->load('lines')));
    }

    public function update(Request $request, Quotation $quotation): RedirectResponse
    {
        [$fields, $lines] = $this->validated($request);
        $this->quotations->save($quotation, $fields, $lines, $request->user());

        return redirect()->route('quotations.show', $quotation)->with('status', __('Draft saved.'));
    }

    public function issue(Quotation $quotation): RedirectResponse
    {
        $this->quotations->issue($quotation);

        return redirect()->route('quotations.show', $quotation)->with('status', __('Quotation :number is ready to send.', ['number' => $quotation->number]));
    }

    public function decide(Request $request, Quotation $quotation): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['accepted', 'declined', 'cancelled'])], 'note' => ['nullable', 'string', 'max:255']]);
        if ($data['decision'] === 'cancelled') {
            if (mb_strlen(trim((string) ($data['note'] ?? ''))) < 3) {
                throw ValidationException::withMessages(['note' => __('Write why it is cancelled.')]);
            }
            $this->quotations->cancel($quotation, trim($data['note']));
        } else {
            $this->quotations->decide($quotation, $data['decision'], $data['note'] ?? null, $request->user());
        }

        return back()->with('status', ['accepted' => __('Marked accepted. Make it the client’s rate contract so their LRs get these rates.'), 'declined' => __('Marked declined.'), 'cancelled' => __('Cancelled.')][$data['decision']]);
    }

    public function contract(Request $request, Quotation $quotation): RedirectResponse
    {
        abort_unless($request->user()->can('party.manage'), 403);
        $contract = $this->quotations->toRateContract($quotation->load('lines'), $request->user());

        return redirect()->route('parties.rates.edit', [$quotation->party_id, $contract])->with('status', __('Rate contract made from :number. Check it and approve it.', ['number' => $quotation->number]));
    }

    public function copy(Request $request, Quotation $quotation): RedirectResponse
    {
        $copy = $this->quotations->copy($quotation->load('lines'), $request->user());

        return redirect()->route('quotations.edit', $copy)->with('status', __('A new draft from :number — change what is new and send it.', ['number' => $quotation->label]));
    }

    public function print(Quotation $quotation, DocumentData $data): Response
    {
        return response($this->html($quotation, $data));
    }

    public function pdf(Quotation $quotation, DocumentData $data, DocumentRenderer $renderer): Response|RedirectResponse
    {
        if (! $renderer->canPdf()) {
            return redirect()->route('quotations.print', $quotation);
        }

        return DocumentRenderer::pdfResponse($renderer->pdf($this->html($quotation, $data)), 'Quotation-' . str_replace('/', '-', (string) ($quotation->number ?? 'draft')));
    }

    public function email(Request $request, Quotation $quotation, DocumentData $data, DocumentMailer $mailer): RedirectResponse
    {
        if (! $quotation->number || $quotation->status === 'cancelled') {
            throw ValidationException::withMessages(['to_email' => __('Send the quotation first (it gets its number), then email it.')]);
        }
        $fields = $request->validate(['to_email' => ['required', 'email:filter', 'max:255'], 'subject' => ['required', 'string', 'max:200'], 'body' => ['required', 'string', 'max:5000']]);
        $sent = $mailer->sendQuotation($quotation, $this->html($quotation, $data), [$fields['to_email']], $fields['subject'], $fields['body'], $request->user());

        return back()->with($sent ? 'status' : 'error', $sent ? __('Quotation sent to :to.', ['to' => $fields['to_email']]) : __('The mail could not be sent. Check the address and try again.'));
    }

    public function whatsapp(Quotation $quotation, Messages $messages): RedirectResponse
    {
        abort_unless((bool) $quotation->number, 404);
        $quotation->load('party');
        $text = $messages->renderQuotation('whatsapp', $quotation)['body'];
        $mobile = preg_replace('/\D/', '', (string) $quotation->party->mobile);

        return redirect()->away('https://wa.me/' . ($mobile ?: '') . '?text=' . rawurlencode($text));
    }

    private function html(Quotation $quotation, DocumentData $data): string
    {
        return app(DocumentRenderer::class)->quotationHtml($quotation);
    }

    private function names(): array
    {
        return [
            'materials' => MasterItem::ofList('material')->pluck('name', 'code'),
            'truckTypes' => MasterItem::ofList('truck_type')->pluck('name', 'code'),
        ];
    }

    private function formData(Quotation $quotation): array
    {
        return [
            'quotation' => $quotation,
            'clients' => Party::active()->withRole('client')->orderBy('name')->get(['id', 'name', 'short_code']),
            'places' => Place::active()->orderBy('name')->get(['id', 'name', 'short_code']),
            'materials' => MasterItem::ofList('material')->where('active', true)->get(['code', 'name']),
            'truckTypes' => MasterItem::ofList('truck_type')->where('active', true)->get(['code', 'name']),
        ];
    }

    /** @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>} */
    private function validated(Request $request): array
    {
        return $this->quotations->input($request->validate($this->quotations->rules()));
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->context->company()->timezone ?: 'UTC')->startOfDay();
    }
}
