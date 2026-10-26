<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Api\Owner\Concerns\RunsOnce;
use App\Http\Controllers\Controller;
use App\Modules\Accounts\Services\Payments;
use App\Modules\Accounts\Services\Statements;
use App\Modules\Invoicing\Models\Quotation;
use App\Modules\Invoicing\Services\Messages;
use App\Modules\Invoicing\Services\Quotations;
use App\Platform\Tenancy\BranchContext;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The owner app's quotations and payments (M31): write a quotation, send it
 * (its number, the client's link and the WhatsApp words), note the answer,
 * copy it for a new offer; pay a vendor, supplier, crew member or expense
 * from cash or the bank. The web's services and rules; what makes something
 * runs once per Idempotency-Key.
 */
class DealsController extends Controller
{
    use RunsOnce;

    public function __construct(protected CompanyContext $context, private Quotations $quotations) {}

    // ---- Quotations ---------------------------------------------------------------------

    public function quotations(Request $request): JsonResponse
    {
        $f = $request->validate(['tab' => ['nullable', Rule::in(['open', 'accepted', 'closed', 'all'])], 'q' => ['nullable', 'string', 'max:60']]);
        $tab = $f['tab'] ?? 'open';
        $q = trim((string) ($f['q'] ?? ''));
        $page = Quotation::with('party:id,name')->withCount('lines')->inBranch()
            ->when($tab === 'open', fn ($b) => $b->whereIn('status', ['draft', 'issued']))
            ->when($tab === 'accepted', fn ($b) => $b->where('status', 'accepted'))
            ->when($tab === 'closed', fn ($b) => $b->whereIn('status', ['declined', 'cancelled']))
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('number', 'like', '%' . addcslashes($q, '%_\\') . '%')->orWhereHas('party', fn ($p) => $p->where('name', 'like', '%' . addcslashes($q, '%_\\') . '%'))))
            ->orderByDesc('quote_date')->orderByDesc('id')->simplePaginate(30);

        return response()->json(['quotations' => $page->getCollection()->map(fn (Quotation $x) => $this->card($x))->values(), 'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null]);
    }

    public function quotation(Quotation $quotation, Messages $messages): JsonResponse
    {
        return response()->json(['quotation' => $this->full($quotation, $messages)]);
    }

    /** A draft (`issue: true` sends it at once — a refusal then makes nothing). */
    public function storeQuotation(Request $request, Messages $messages): JsonResponse
    {
        return $this->once($request, 'quotation', function () use ($request, $messages) {
            $today = $this->today();
            $request->merge(['quote_date' => $request->input('quote_date', $today->toDateString()), 'valid_until' => $request->input('valid_until', $today->addDays(15)->toDateString())]);
            [$fields, $lines] = $this->quotations->input($request->validate($this->quotations->rules()));
            if (! $request->has('terms')) {
                $fields['terms'] = __("Rates are per the basis shown; taxes extra as applicable.\nDetention after the free hours at the rate shown.\nPayment within 30 days of the bill.");
            }
            $quotation = $this->quotations->save(null, $fields + ['branch_id' => app(BranchContext::class)->defaultBranch()], $lines, $request->user());
            if ($request->boolean('issue')) {
                $this->quotations->issue($quotation->fresh());
            }

            return ['quotation' => $this->full($quotation->fresh(), $messages)];
        });
    }

    /** A draft saved again as a whole (its routes are replaced by those sent); a sent one is never changed. */
    public function updateQuotation(Request $request, Quotation $quotation, Messages $messages): JsonResponse
    {
        [$fields, $lines] = $this->quotations->input($request->validate($this->quotations->rules()));
        $this->quotations->save($quotation, $fields, $lines, $request->user());

        return response()->json(['quotation' => $this->full($quotation->fresh(), $messages)]);
    }

    public function issueQuotation(Quotation $quotation, Messages $messages): JsonResponse
    {
        if ($quotation->status !== 'issued') {
            $this->quotations->issue($quotation);
        }

        return response()->json(['quotation' => $this->full($quotation->fresh(), $messages)]);
    }

    /** The client's answer noted by the office, or the quotation called off. */
    public function decideQuotation(Request $request, Quotation $quotation, Messages $messages): JsonResponse
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

        return response()->json(['quotation' => $this->full($quotation->fresh(), $messages)]);
    }

    /** A new draft from any quotation — how a changed offer is made. */
    public function copyQuotation(Request $request, Quotation $quotation, Messages $messages): JsonResponse
    {
        return $this->once($request, 'quotation_copy', fn () => ['quotation' => $this->full($this->quotations->copy($quotation->load('lines'), $request->user())->fresh(), $messages)]);
    }

    // ---- Payments -----------------------------------------------------------------------

    /** Who can be paid, with what the book says we owe them (negative: we owe them). */
    public function payees(Request $request, Payments $payments, Statements $statements): JsonResponse
    {
        $q = mb_strtolower(trim((string) ($request->validate(['q' => ['nullable', 'string', 'max:60']])['q'] ?? '')));
        $list = $payments->payees()->when($q !== '', fn ($c) => $c->filter(fn ($l) => str_contains(mb_strtolower($l->name), $q)));
        $balances = $statements->balances($this->today())->keyBy('id');

        return response()->json(['payees' => $list->take(200)->map(fn ($l) => [
            'id' => $l->id, 'name' => $l->name, 'kind' => $l->kind, 'group' => $l->group?->name,
            'balance' => round((float) ($balances[$l->id]->balance ?? 0), 2),
        ])->values()]);
    }

    public function storePayment(Request $request, Payments $payments): JsonResponse
    {
        return $this->once($request, 'payment', function () use ($request, $payments) {
            $request->merge(['paid_on' => $request->input('paid_on', $this->today()->toDateString())]);
            $voucher = $payments->pay($request->validate($payments->rules(), ['reference.required_if' => __('Give the UTR or cheque number.')]), $request->user());

            return ['voucher' => ['id' => $voucher->id, 'number' => $voucher->number, 'date' => $voucher->voucher_date->toDateString(), 'amount' => (float) $voucher->total,
                'narration' => $voucher->narration]];
        });
    }

    // ---- Answers ------------------------------------------------------------------------

    private function card(Quotation $q): array
    {
        return ['id' => $q->id, 'number' => $q->number, 'label' => $q->label, 'status' => $q->isExpired($this->today()) ? 'expired' : $q->status, 'status_label' => $q->statusLabel($this->today()),
            'party' => ['id' => $q->party_id, 'name' => $q->party?->name], 'quote_date' => $q->quote_date->toDateString(), 'valid_until' => $q->valid_until?->toDateString(),
            'subject' => $q->subject, 'routes' => $q->lines_count ?? $q->lines()->count()];
    }

    private function full(Quotation $q, Messages $messages): array
    {
        $q->loadMissing(['party:id,name,mobile,email', 'lines.from:id,name,short_code', 'lines.to:id,name,short_code']);

        return $this->card($q) + [
            'terms' => $q->terms, 'notes' => $q->notes, 'decision_note' => $q->decision_note, 'answered_by_name' => $q->answered_by_name,
            'viewed_at' => $q->viewed_at?->toIso8601String(), 'issued_at' => $q->issued_at?->toIso8601String(),
            'lines' => $q->lines->map(fn ($l) => ['from' => $l->from?->only(['id', 'name', 'short_code']), 'to' => $l->to?->only(['id', 'name', 'short_code'])]
                + collect($l->only(\App\Modules\Invoicing\Models\QuotationLine::CONTRACT_FIELDS))->except(['from_place_id', 'to_place_id'])->map(fn ($v) => is_numeric($v) && ! is_int($v) ? (float) $v : $v)->all()
                + ['note' => $l->note])->values(),
            // Once sent: the client's link and the words to send with it.
            'share_url' => $q->public_token ? route('documents.public', $q->public_token) : null,
            'whatsapp_text' => $q->number ? $messages->renderQuotation('whatsapp', $q)['body'] : null,
            'party_mobile' => $q->party?->mobile,
        ];
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->context->company()->timezone ?: 'UTC')->startOfDay();
    }
}
