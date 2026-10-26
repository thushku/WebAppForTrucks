<?php

namespace App\Modules\Invoicing\Services;

use App\Models\Account;
use App\Modules\Invoicing\Models\Quotation;
use App\Modules\Invoicing\Models\QuotationLine;
use App\Modules\Parties\Models\RateContract;
use App\Modules\Settings\Services\DocumentNumbering;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use App\Modules\Parties\Models\RateContractLine;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Quotations (M21): written as a draft, issued with the next QT/ number,
 * then accepted or declined by the client. An accepted quotation becomes
 * the client's rate contract (a draft there, approved as usual) so the rate
 * fills in on their LRs; any line can start a trip with its rate. An issued
 * quotation is never edited — a changed offer is a new quotation.
 */
class Quotations
{
    public function __construct(private DocumentNumbering $numbering, private CompanyContext $context, private AuditRecorder $audit) {}

    /** The rules of a quotation's form — the web's and the owner app's. */
    public function rules(): array
    {
        $companyId = $this->context->id();
        $place = Rule::exists('places', 'id')->where('company_id', $companyId);

        return [
            'party_id' => ['required', 'integer', Rule::exists('parties', 'id')->where('company_id', $companyId)],
            'quote_date' => ['required', 'date_format:Y-m-d'],
            'valid_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:quote_date'],
            'subject' => ['nullable', 'string', 'max:150'],
            'terms' => ['nullable', 'string', 'max:3000'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'lines' => ['nullable', 'array', 'max:200'],
            'lines.*.from_place_id' => ['nullable', 'integer', $place],
            'lines.*.to_place_id' => ['nullable', 'integer', $place],
            'lines.*.material' => ['nullable', 'string', Rule::exists('master_items', 'code')->where('company_id', $companyId)->where('list', 'material')],
            'lines.*.truck_type' => ['nullable', 'string', Rule::exists('master_items', 'code')->where('company_id', $companyId)->where('list', 'truck_type')],
            'lines.*.basis' => ['nullable', Rule::in(array_keys(RateContractLine::BASES))],
            'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.min_weight_mt' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'lines.*.detention_free_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'lines.*.detention_rate_per_day' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'lines.*.loading_charge' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'lines.*.unloading_charge' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'lines.*.note' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * The validated input split into the quotation's fields and its routes;
     * an empty row is ignored, a started one needs from, to and the rate.
     *
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    public function input(array $data): array
    {
        $lines = [];
        foreach ($data['lines'] ?? [] as $i => $line) {
            // The spare empty row on the form is ignored.
            if (! filled($line['from_place_id'] ?? null) && ! filled($line['to_place_id'] ?? null) && ! filled($line['rate'] ?? null)) {
                continue;
            }
            foreach (['from_place_id', 'to_place_id', 'rate'] as $required) {
                if (! filled($line[$required] ?? null)) {
                    throw ValidationException::withMessages(["lines.{$i}.{$required}" => __('Row :n: give from, to and the rate.', ['n' => count($lines) + 1])]);
                }
            }
            $lines[] = array_merge(array_map(fn ($v) => $v === '' ? null : $v, collect($line)->only(array_merge(QuotationLine::CONTRACT_FIELDS, ['note']))->all()), ['basis' => $line['basis'] ?? 'trip']);
        }

        return [collect($data)->except('lines')->all(), $lines];
    }

    /** @param array<int, array<string, mixed>> $lines */
    public function save(?Quotation $quotation, array $fields, array $lines, Account $by): Quotation
    {
        if ($quotation && ! $quotation->isDraft()) {
            throw ValidationException::withMessages(['quotation' => __('A sent quotation is not changed. Make a new one (Copy) for a new offer.')]);
        }

        return DB::transaction(function () use ($quotation, $fields, $lines, $by) {
            $quotation ??= new Quotation(['status' => 'draft', 'created_by' => $by->id, 'currency' => $this->context->company()->currency]);
            $quotation->fill($fields)->save();
            $quotation->lines()->delete();
            foreach (array_values($lines) as $i => $line) {
                QuotationLine::create(['quotation_id' => $quotation->id, 'sort_order' => $i] + $line);
            }

            return $quotation;
        });
    }

    /** Give it its number and send it out; from now on it is fixed. */
    public function issue(Quotation $quotation): Quotation
    {
        if (! $quotation->isDraft()) {
            throw ValidationException::withMessages(['quotation' => __('This quotation is sent already.')]);
        }
        if (! $quotation->lines()->exists()) {
            throw ValidationException::withMessages(['lines' => __('Add at least one route with its rate.')]);
        }

        DB::transaction(function () use ($quotation) {
            $quotation->forceFill([
                'number' => $this->numbering->assignInBranch('quotation', $quotation->quote_date->copy()->setTime(12, 0), $quotation->branch_id),
                'status' => 'issued', 'issued_at' => now(),
            ])->save();
            // The client's link, to read and answer it (M24).
            $quotation->forceFill(['public_token' => app(DocumentLinks::class)->create('quotation', $quotation->id)])->save();
        });
        $this->audit->record('quotation.issued', "Quotation {$quotation->number} to {$quotation->party->name}", 'quotation', (string) $quotation->id, $quotation->number);

        return $quotation;
    }

    /**
     * The client's answer: accepted (how — "PO 4512", "email of 3 Nov") or
     * declined (why); noted by the office ($by), or given by the client on
     * the link ($clientName).
     */
    public function decide(Quotation $quotation, string $decision, ?string $note, ?Account $by, ?string $clientName = null): void
    {
        if (! in_array($quotation->status, ['issued'], true) || ! in_array($decision, ['accepted', 'declined'], true)) {
            throw ValidationException::withMessages(['decision' => __('Only a sent quotation can be accepted or declined.')]);
        }
        if ($decision === 'declined' && mb_strlen(trim((string) $note)) < 3) {
            throw ValidationException::withMessages(['note' => __('Write why the client declined (it helps the next quotation).')]);
        }
        if ($quotation->isExpired(CarbonImmutable::now($this->context->company()->timezone ?: 'UTC')) && $clientName !== null) {
            throw ValidationException::withMessages(['decision' => __('This quotation has expired. Ask us for a new one.')]);
        }
        $quotation->forceFill(['status' => $decision, 'decision_note' => $note ? mb_substr(trim($note), 0, 255) : null, 'decided_at' => now(),
            'decided_by' => $by?->id, 'answered_by_name' => $clientName ? mb_substr(trim($clientName), 0, 120) : null])->save();
        if ($clientName !== null) {
            $this->audit->record("quotation.{$decision}", "Quotation {$quotation->number} {$decision} online by {$clientName}", 'quotation', (string) $quotation->id, $quotation->number, reason: $note);
            app(\App\Platform\Notifications\Notices::class)->quotationAnswered($quotation);
        }
    }

    public function cancel(Quotation $quotation, string $reason): void
    {
        if (in_array($quotation->status, ['accepted', 'cancelled'], true)) {
            throw ValidationException::withMessages(['quotation' => __('An accepted or cancelled quotation stays as it is.')]);
        }
        $quotation->forceFill(['status' => 'cancelled', 'decision_note' => mb_substr($reason, 0, 255), 'decided_at' => now()])->save();
    }

    /** An accepted quotation's routes as the client's rate contract — a draft, to approve on the client's page. */
    public function toRateContract(Quotation $quotation, Account $by): RateContract
    {
        if ($quotation->status !== 'accepted') {
            throw ValidationException::withMessages(['quotation' => __('Only an accepted quotation becomes a rate contract.')]);
        }
        if ($quotation->rate_contract_id) {
            throw ValidationException::withMessages(['quotation' => __('The rate contract is made already.')]);
        }

        return DB::transaction(function () use ($quotation, $by) {
            $today = CarbonImmutable::now($this->context->company()->timezone ?: 'UTC')->toDateString();
            $contract = RateContract::create([
                'party_id' => $quotation->party_id, 'name' => mb_substr(__('Quotation :number', ['number' => $quotation->number]), 0, 120),
                'valid_from' => max($today, $quotation->quote_date->toDateString()), 'valid_to' => null, 'status' => 'draft', 'version' => 1,
                'payment_terms' => $quotation->terms ? mb_substr($quotation->terms, 0, 1000) : null,
                'notes' => __('Made from quotation :number, accepted :date.', ['number' => $quotation->number, 'date' => $quotation->decided_at?->format('d M Y')]),
                'created_by' => $by->id,
            ]);
            foreach ($quotation->lines as $line) {
                $contract->lines()->create($line->only(QuotationLine::CONTRACT_FIELDS));
            }
            $quotation->forceFill(['rate_contract_id' => $contract->id])->save();

            return $contract;
        });
    }

    /** A new draft with the same client, routes and terms, dated today. */
    public function copy(Quotation $quotation, Account $by): Quotation
    {
        $today = CarbonImmutable::now($this->context->company()->timezone ?: 'UTC');
        $days = $quotation->valid_until ? (int) $quotation->quote_date->diffInDays($quotation->valid_until) : null;

        return $this->save(null, [
            'party_id' => $quotation->party_id, 'branch_id' => $quotation->branch_id, 'quote_date' => $today->toDateString(),
            'valid_until' => $days !== null ? $today->addDays($days)->toDateString() : null, 'subject' => $quotation->subject, 'terms' => $quotation->terms,
        ], $quotation->lines->map(fn (QuotationLine $l) => $l->only(array_merge(QuotationLine::CONTRACT_FIELDS, ['note'])))->all(), $by);
    }
}
