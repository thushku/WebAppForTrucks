<?php

namespace App\Modules\Invoicing\Services;

use App\Models\Account;
use App\Modules\Invoicing\Models\DocumentDelivery;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\InvoiceLine;
use App\Modules\Invoicing\Models\InvoiceTax;
use App\Modules\Parties\Models\Party;
use App\Modules\Settings\Services\DocumentNumbering;
use App\Modules\Tax\Models\TaxRegistration;
use App\Modules\Tax\Services\ServiceClasses;
use App\Modules\Tax\Services\TaxContext;
use App\Modules\Tax\Services\TaxEngine;
use App\Modules\Tax\Services\TaxException;
use App\Modules\Trips\Models\Consignment;
use App\Modules\Trips\Models\Trip;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Freight bills, ported from Clinic Ledger's InvoiceService (blueprint 10
 * §2, §5) and taxed by the engine (M8):
 *
 *  · drafts from delivered LRs — one LR, or many for a consolidated bill
 *    with the LR annexure; supplementary bills (detention, rate difference)
 *    and credit / debit notes against an issued bill;
 *  · every save of a draft works the tax out again; a case the engine cannot
 *    decide is shown on the draft and stops finalising, never guessed;
 *  · finalising is one transaction: tax again, the number (per GSTIN when
 *    the company has several), the seller / buyer / bank snapshot, the LRs
 *    marked billed, the public link, the client's balance, the audit log;
 *  · voiding keeps the number, frees the LRs and needs a reason.
 */
class InvoiceService
{
    public function __construct(
        private CompanyContext $context,
        private DocumentNumbering $numbering,
        private TaxEngine $engine,
        private BalanceService $balances,
        private DocumentLinks $links,
        private AuditRecorder $audit,
    ) {}

    // ---- Drafts ----------------------------------------------------------

    /** A draft bill for these LRs of one client (one LR, or a consolidated bill). */
    public function draftFromLoads(Party $billTo, array $consignmentIds, ?Account $by = null): Invoice
    {
        return DB::transaction(function () use ($billTo, $consignmentIds, $by) {
            $invoice = $this->newDraft($billTo, 'invoice', null, $by);
            if ($this->addLoads($invoice, $consignmentIds) === 0) {
                throw ValidationException::withMessages(['loads' => __('None of those LRs is waiting to be billed to :name.', ['name' => $billTo->name])]);
            }
            // A bill belongs to its trips' branch when they share one (M22).
            $tripBranches = Trip::whereIn('id', InvoiceLine::where('invoice_id', $invoice->id)->whereNotNull('trip_id')->pluck('trip_id'))->pluck('branch_id')->unique();
            if ($tripBranches->count() === 1 && $tripBranches->first()) {
                $invoice->forceFill(['branch_id' => $tripBranches->first()])->save();
            }

            return $invoice->fresh(['lines', 'taxes']);
        });
    }

    public function draftBlank(Party $billTo, ?Account $by = null): Invoice
    {
        return DB::transaction(fn () => $this->newDraft($billTo, 'invoice', null, $by));
    }

    /** A credit note against an issued bill: its lines copied with their tax, to be reduced or removed. */
    public function draftCreditNote(Invoice $invoice, ?Account $by = null): Invoice
    {
        $this->assertNoteBase($invoice, 'credit_note');
        if ($invoice->creditable() <= 0.005) {
            throw ValidationException::withMessages(['invoice' => __(':number has been credited in full already.', ['number' => $invoice->number])]);
        }

        return DB::transaction(function () use ($invoice, $by) {
            $note = $this->newDraft($invoice->party, 'credit_note', $invoice, $by);
            foreach ($invoice->lines()->get() as $i => $line) {
                InvoiceLine::create([
                    'invoice_id' => $note->id, 'consignment_id' => $line->consignment_id, 'trip_id' => $line->trip_id,
                    'service_class' => $line->service_class, 'description' => $line->description, 'detail' => $line->detail,
                    'sac_code' => $line->sac_code, 'unit' => $line->unit, 'quantity' => $line->quantity, 'unit_rate' => $line->unit_rate,
                    'discount_percent' => $line->discount_percent, 'sort_order' => $i,
                ]);
            }
            $this->recalculate($note);

            return $note->fresh(['lines', 'taxes']);
        });
    }

    /** A debit note (a further charge) or a supplementary invoice (detention, rate difference) against an issued bill. */
    public function draftAgainst(Invoice $invoice, string $docType, ?Account $by = null): Invoice
    {
        $this->assertNoteBase($invoice, $docType);

        return DB::transaction(fn () => $this->newDraft($invoice->party, $docType, $invoice, $by)->fresh(['lines', 'taxes']));
    }

    private function newDraft(Party $party, string $docType, ?Invoice $related, ?Account $by): Invoice
    {
        $company = $this->context->company();
        $today   = CarbonImmutable::now($company->timezone ?: 'UTC')->startOfDay();
        $registration = $related?->registration ?? TaxRegistration::usable($today)->orderByDesc('is_default')->orderBy('id')->first();
        $buyerTaxId   = $related?->buyer_tax_id ?? $party->taxIds()->where('active', true)->where('country', $registration?->country ?? $company->country)
            ->orderByDesc('is_default')->value('tax_id');

        return Invoice::create([
            'doc_type' => $docType, 'status' => 'draft', 'party_id' => $party->id, 'buyer_tax_id' => $buyerTaxId,
            'tax_registration_id' => $registration?->id, 'related_invoice_id' => $related?->id,
            'issue_date' => $today->toDateString(),
            // A credit note is not paid: it falls due the day it is issued.
            'due_date' => $docType === 'credit_note' ? $today->toDateString() : $today->addDays((int) ($party->credit_days ?? 0))->toDateString(),
            'reference' => $related?->reference, 'currency' => $related?->currency ?? $company->currency,
            'transport_type' => $related?->transport_type, 'carrier_role' => $related?->carrier_role, 'loading_state' => $related?->loading_state,
            'evidence' => $related?->evidence, 'inclusive' => false, 'created_by' => $by?->id ?? auth()->id(),
            'branch_id' => $related?->branch_id ?? app(\App\Platform\Tenancy\BranchContext::class)->defaultBranch(),
        ]);
    }

    /**
     * Add these LRs as lines: freight (its basis in the detail), loading,
     * unloading and other charges; the trip's "other charges billed" once;
     * the party's advance as "less advance". Returns how many LRs were added.
     */
    public function addLoads(Invoice $invoice, array $consignmentIds): int
    {
        $this->assertDraft($invoice);

        $loads = Consignment::with(['trip.truck', 'trip.supplierVehicle', 'pickupStop.place', 'dropStop.place'])
            ->where('billing_party_id', $invoice->party_id)->whereIn('id', $consignmentIds)
            ->whereNull('invoice_id')->whereNotNull('lr_number')->where('status', '!=', 'cancelled')
            ->orderBy('lr_date')->orderBy('id')->get();
        // Not already on another draft (each LR is billed once).
        $taken = InvoiceLine::whereIn('consignment_id', $loads->pluck('id'))->where('invoice_id', '!=', $invoice->id)
            ->whereHas('invoice', fn ($q) => $q->where('status', '!=', 'void'))->pluck('consignment_id')->unique();
        $loads = $loads->reject(fn ($c) => $taken->contains($c->id));
        if ($loads->isEmpty()) {
            return 0;
        }

        $types = $loads->pluck('uae_transport_type')->filter()->unique();
        if ($types->count() > 1) {
            throw ValidationException::withMessages(['loads' => __('Bill domestic and international loads on separate invoices.')]);
        }

        $order = (int) $invoice->lines()->max('sort_order') + 1;
        $advance = 0.0;
        foreach ($loads as $c) {
            $route = trim(($c->pickupStop?->place?->name ?? '') . ' → ' . ($c->dropStop?->place?->name ?? ''));
            $basis = $this->basisText($c);
            $base  = ['invoice_id' => $invoice->id, 'consignment_id' => $c->id, 'trip_id' => $c->trip_id, 'unit' => 'trip', 'quantity' => 1];

            InvoiceLine::create($base + [
                'service_class' => 'FREIGHT_ROAD', 'sort_order' => $order++, 'unit_rate' => (float) $c->freight,
                'description' => __('Freight, LR :lr', ['lr' => $c->lr_number]) . ($route !== '→' ? ' · ' . $route : ''),
                'detail' => collect([$c->lr_date?->format('d M Y'), $c->trip?->vehicleLabel(), $c->description ?: $c->material, $basis])->filter()->join(' · '),
            ]);
            foreach (['loading_charge' => [__('Loading charges'), 'LOADING_UNLOADING'], 'unloading_charge' => [__('Unloading charges'), 'LOADING_UNLOADING'], 'other_charges' => [__('Other charges'), 'OTHER_CHARGES']] as $field => [$text, $class]) {
                if ((float) $c->{$field} > 0) {
                    InvoiceLine::create($base + ['service_class' => $class, 'sort_order' => $order++, 'unit_rate' => (float) $c->{$field}, 'description' => $text . ' · LR ' . $c->lr_number]);
                }
            }
            $advance += (float) $c->advance_from_party;
        }

        // The trip's own "other charges billed to the client" (13 §A #1), once per trip.
        foreach ($loads->pluck('trip')->filter()->unique('id') as $trip) {
            $already = InvoiceLine::where('trip_id', $trip->id)->whereNull('consignment_id')->where('service_class', 'OTHER_CHARGES')
                ->whereHas('invoice', fn ($q) => $q->where('status', '!=', 'void'))->exists();
            if ((float) $trip->other_charges > 0 && ! $already) {
                InvoiceLine::create(['invoice_id' => $invoice->id, 'trip_id' => $trip->id, 'service_class' => 'OTHER_CHARGES', 'unit' => 'trip', 'quantity' => 1,
                    'sort_order' => $order++, 'unit_rate' => (float) $trip->other_charges, 'description' => __('Other charges · trip :no', ['no' => $trip->trip_number])]);
            }
        }

        // The facts tax needs, from the first load.
        $first = $loads->first();
        $invoice->forceFill([
            'advance_less'   => round((float) $invoice->advance_less + $advance, 3),
            'transport_type' => $invoice->transport_type ?? $first->uae_transport_type,
            'carrier_role'   => $invoice->carrier_role ?? $first->uae_role,
            'loading_state'  => $invoice->loading_state ?? $first->pickupStop?->place?->state_code,
            'reference'      => $invoice->reference ?? ($loads->count() === 1 ? $first->client_ref : null),
            'period_from'    => $loads->count() > 1 ? $loads->min('lr_date')?->toDateString() : $invoice->period_from,
            'period_to'      => $loads->count() > 1 ? $loads->max('lr_date')?->toDateString() : $invoice->period_to,
        ])->save();

        $this->recalculate($invoice);

        return $loads->count();
    }

    /** The builder's save: header fields and the full set of lines (replaced), then the tax again. */
    public function updateDraft(Invoice $invoice, array $data): Invoice
    {
        $this->assertDraft($invoice);

        return DB::transaction(function () use ($invoice, $data) {
            $invoice->fill(array_intersect_key($data, array_flip([
                'issue_date', 'due_date', 'period_from', 'period_to', 'reference', 'buyer_tax_id', 'tax_registration_id', 'transport_type', 'carrier_role',
                'loading_state', 'evidence', 'inclusive', 'discount_type', 'discount_value', 'advance_less', 'notes', 'terms', 'reason', 'document_template_id',
            ])));
            if ($invoice->isCreditNote()) {
                $invoice->due_date = $invoice->issue_date;
            }
            $invoice->save();

            if (array_key_exists('lines', $data)) {
                $this->replaceLines($invoice, $data['lines']);
            }
            $this->recalculate($invoice);

            return $invoice->fresh(['lines', 'taxes']);
        });
    }

    private function replaceLines(Invoice $invoice, array $rows): void
    {
        $keep = [];
        $order = 0;
        foreach ($rows as $row) {
            $description = trim((string) ($row['description'] ?? ''));
            if ($description === '' && (float) ($row['unit_rate'] ?? 0) == 0.0) {
                continue;
            }
            $line = ! empty($row['id']) ? $invoice->lines()->whereKey($row['id'])->first() : null;
            $attributes = [
                'service_class'    => ServiceClasses::exists((string) ($row['service_class'] ?? '')) ? $row['service_class'] : ($line?->service_class ?? 'FREIGHT_ROAD'),
                'description'      => $description !== '' ? mb_substr($description, 0, 255) : ServiceClasses::label($row['service_class'] ?? 'FREIGHT_ROAD'),
                'detail'           => isset($row['detail']) ? (mb_substr(trim((string) $row['detail']), 0, 255) ?: null) : $line?->detail,
                'unit'             => mb_substr((string) ($row['unit'] ?? $line?->unit ?? 'trip'), 0, 12),
                'quantity'         => (float) ($row['quantity'] ?? 1),
                'unit_rate'        => (float) ($row['unit_rate'] ?? 0),
                'discount_percent' => (float) ($row['discount_percent'] ?? 0),
                'tax_code'         => ($row['tax_code'] ?? null) ?: null,
                'override_reason'  => ($row['override_reason'] ?? null) ?: null,
                'sort_order'       => $order++,
            ];
            if ($line) {
                $line->fill($attributes)->save();
                $keep[] = $line->id;
            } else {
                $keep[] = InvoiceLine::create($attributes + ['invoice_id' => $invoice->id])->id;
            }
        }
        $invoice->lines()->whereNotIn('id', $keep)->delete();
    }

    /**
     * The tax and totals from the lines, by the engine, written back to the
     * lines, the tax rows and the bill. A case the engine cannot decide is
     * kept on the draft (shown in the builder) — finalising will refuse it.
     *
     * @return ?string the engine's message when it could not decide
     */
    public function recalculate(Invoice $invoice): ?string
    {
        $invoice->loadMissing(['party.taxIds', 'registration.years', 'related.lines']);
        $lines = $invoice->lines()->get();
        $input = $lines->map(fn (InvoiceLine $l) => $this->lineInput($invoice, $l))->values()->all();

        try {
            $r = $lines->isEmpty() ? null : $this->engine->calculate($this->context($invoice), $input,
                ['discount_type' => $invoice->discount_type, 'discount_value' => (float) $invoice->discount_value]);
            $error = null;
        } catch (TaxException $e) {
            $r = null;
            $error = $e->getMessage();
        }

        InvoiceTax::where('invoice_id', $invoice->id)->delete();
        if ($r) {
            foreach ($lines->values() as $i => $line) {
                $out = $r['lines'][$i];
                $line->forceFill([
                    'tax_code' => $out['tax_code'] ?? null, 'tax_category' => $out['category'] ?? null,
                    'sac_code' => $out['sac_code'] ?? $line->sac_code, 'tax_rate_id' => $out['rate_id'] ?? null, 'tax_rule_id' => $out['rule_id'] ?? null,
                    'rate_percent' => $out['rate_percent'], 'line_subtotal' => $out['line_subtotal'], 'net' => $out['net'], 'taxable' => $out['taxable'],
                    'tax_amount' => $out['tax'], 'notional_tax' => $out['notional_tax'], 'parts' => $out['part_amounts'] ?: null, 'line_total' => $out['line_total'],
                ])->save();
            }
            foreach ($r['taxes'] as $i => $tax) {
                InvoiceTax::create(['invoice_id' => $invoice->id, 'code' => $tax['code'], 'percent' => $tax['percent'], 'taxable' => $tax['taxable'],
                    'amount' => $tax['amount'], 'charged' => $tax['charged'], 'sort_order' => $i]);
            }
        }

        $total = $r ? $r['total'] : round($lines->sum(fn ($l) => (float) $l->quantity * (float) $l->unit_rate), 2);
        $invoice->forceFill([
            'kind'             => $r['document'] ?? null,
            'subtotal'         => $r['subtotal'] ?? $total,
            'discount_amount'  => $r['discount'] ?? 0,
            'taxable_total'    => $r['taxable_total'] ?? $total,
            'tax_total'        => $r['tax_charged'] ?? 0,
            'tax_notional'     => $r['tax_notional'] ?? 0,
            'round_off'        => $r['round_off'] ?? 0,
            'total'            => $total,
            'reverse_charge'   => $r['reverse_charge'] ?? false,
            'pending_evidence' => $r['pending_evidence'] ?? false,
            'place_of_supply'  => $r['place_of_supply']['code'] ?? null,
            'place_of_supply_name' => $r['place_of_supply']['name'] ?? null,
            'tax_trace'        => $r ? ['steps' => $r['trace'], 'warnings' => $r['warnings'], 'legal_texts' => $r['legal_texts']] : ['error' => $error],
            'withholding'      => $r['withholding'] ?? null,
            'tds_expected'     => $r['withholding']['amount'] ?? 0,
            'balance_due'      => $invoice->isCreditNote() ? 0 : max(0, round($total - (float) $invoice->advance_less, 3)),
        ])->save();

        return $error;
    }

    private function context(Invoice $invoice): TaxContext
    {
        // A credit note takes the rates of the invoice it corrects (blueprint 11 §1 #2).
        $rateDate = $invoice->isCreditNote() && $invoice->related ? $invoice->related->issue_date : $invoice->issue_date;

        return new TaxContext(
            date: CarbonImmutable::parse($rateDate->toDateString()),
            registration: $invoice->registration,
            buyer: $invoice->party,
            buyerTaxId: $invoice->buyer_tax_id,
            loadingState: $invoice->loading_state,
            transportType: $invoice->transport_type,
            carrierRole: $invoice->carrier_role,
            evidence: $invoice->evidence ?? [],
            inclusive: (bool) $invoice->inclusive,
            roundTotal: $invoice->isCreditNote() ? false : null,
            currency: $invoice->currency,
        );
    }

    /**
     * One line for the engine. A credit note keeps each line's tax from its
     * invoice; an add-on on a supplementary bill or debit note takes the
     * original freight line's tax.
     */
    private function lineInput(Invoice $invoice, InvoiceLine $line): array
    {
        $input = ['service_class' => $line->service_class, 'description' => $line->description, 'quantity' => (float) $line->quantity,
            'unit_rate' => (float) $line->unit_rate, 'discount_percent' => (float) $line->discount_percent, 'sac_code' => $line->sac_code];

        if ($line->tax_code && $line->override_reason) {
            return $input + ['tax_code' => $line->tax_code, 'override_reason' => $line->override_reason];
        }

        $related = $invoice->related;
        if ($related && $invoice->isCreditNote()) {
            $original = $related->lines->firstWhere('service_class', $line->service_class) ?? $related->lines->first();
            if ($original && ($code = $this->codeOf($original))) {
                return $input + ['tax_code' => $code, 'override_reason' => __('Same tax as on :number', ['number' => $related->number])];
            }
        }
        if ($related && in_array($line->service_class, [...ServiceClasses::ADD_ONS, 'OTHER_CHARGES'], true)) {
            $main = $related->lines->first(fn ($l) => in_array($l->service_class, ServiceClasses::MAIN, true));
            if ($main && ($code = $this->codeOf($main))) {
                return $input + ['tax_code' => $code, 'override_reason' => __('Charged with the freight of :number', ['number' => $related->number])];
            }
        }

        return $input;
    }

    /** The tax code a line was given (by its rule, or by hand). */
    private function codeOf(InvoiceLine $line): ?string
    {
        return $line->tax_code;
    }

    // ---- Finalise --------------------------------------------------------

    public function finalise(Invoice $invoice, Account $by): Invoice
    {
        $this->assertDraft($invoice);

        return DB::transaction(function () use ($invoice, $by) {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($error = $this->recalculate($invoice)) {
                throw ValidationException::withMessages(['tax' => $error]);
            }
            $invoice->refresh();

            if ($invoice->lines()->count() === 0) {
                throw ValidationException::withMessages(['lines' => __('Add at least one line before issuing.')]);
            }
            if ((float) $invoice->total <= 0) {
                throw ValidationException::withMessages(['lines' => __('The total must be more than zero.')]);
            }

            $related = null;
            if ($invoice->related_invoice_id) {
                if (mb_strlen(trim((string) $invoice->reason)) < 3) {
                    throw ValidationException::withMessages(['reason' => __('Say why this :doc is issued.', ['doc' => mb_strtolower($invoice->kindLabel())])]);
                }
                $related = Invoice::whereKey($invoice->related_invoice_id)->lockForUpdate()->first();
                if (! $related || ! $related->isFinal()) {
                    throw ValidationException::withMessages(['invoice' => __('The bill this is against is no longer issued.')]);
                }
                if ($invoice->isCreditNote() && (float) $invoice->total > $related->creditable($invoice->id) + 0.005) {
                    throw ValidationException::withMessages(['lines' => __('This credit note is for :amount, but only :left of :number is left to credit.', [
                        'amount' => number_format((float) $invoice->total, 2), 'left' => number_format($related->creditable($invoice->id), 2), 'number' => $related->number])]);
                }
            }

            $registration = $invoice->registration;
            $series = Invoice::SERIES[$invoice->doc_type];
            // One series per GSTIN when the company bills from several (each registration's numbers are its own).
            $scoped = $registration && TaxRegistration::usable()->count() > 1;
            $number = $this->numbering->assign($series, $invoice->issue_date->copy()->setTime(12, 0), ['BR' => $invoice->branch_id ? (string) $invoice->branch_id : 'HO'],
                $scoped ? 'registration' : 'company', $scoped ? $registration->id : 0);

            $invoice->forceFill([
                'number'          => $number,
                'status'          => $invoice->isCreditNote() ? 'issued' : 'finalised',
                'finalized_at'    => now(),
                'finalized_by'    => $by->id,
                'snapshot'        => app(DocumentData::class)->snapshot($invoice),
                'design_snapshot' => app(DocumentTemplates::class)->freeze('invoice', $invoice->party_id, $invoice->branch_id, $invoice->document_template_id),
                'public_token'    => $this->links->create('invoice', $invoice->id),
            ])->save();

            if ($invoice->isCreditNote()) {
                // Off its invoice as far as that goes; the rest is held as the client's credit.
                $applied = round(min((float) $invoice->total, (float) $related->balance_due), 3);
                $related->forceFill(['amount_credited' => round((float) $related->amount_credited + $applied, 3)]);
                $related->settle(CarbonImmutable::parse($invoice->issue_date->toDateString()));
                $related->save();
                $invoice->forceFill(['credit_applied' => $applied, 'balance_due' => 0])->save();
            } else {
                $invoice->settle();
                $invoice->save();
                // The LRs are billed: their freight and route are fixed now (13 §A #6).
                $ids = $invoice->lines()->whereNotNull('consignment_id')->pluck('consignment_id')->unique();
                if ($invoice->doc_type === 'invoice' && $ids->isNotEmpty()) {
                    Consignment::whereIn('id', $ids)->update(['invoice_id' => $invoice->id, 'status' => 'billed', 'updated_at' => now()]);
                }
            }

            $this->balances->recompute($invoice->party_id);
            $this->audit->record("{$invoice->doc_type}.finalised", "{$by->name} issued {$number} to {$invoice->party->name}: {$invoice->currency} " . number_format((float) $invoice->total, 2)
                . ($related ? " against {$related->number} — {$invoice->reason}" : ''), 'invoice', (string) $invoice->id, $number, severity: 'notable');

            return $invoice->fresh(['lines', 'taxes']);
        });
    }

    public function void(Invoice $invoice, string $reason, Account $by): Invoice
    {
        if ($invoice->isDraft()) {
            $invoice->lines()->delete();
            $invoice->delete();

            return $invoice;
        }
        if ($invoice->isVoid()) {
            throw ValidationException::withMessages(['invoice' => __('This bill is already void.')]);
        }
        if (mb_strlen(trim($reason)) < 5) {
            throw ValidationException::withMessages(['reason' => __('Write why it is voided.')]);
        }
        if ((float) $invoice->amount_paid > 0) {
            throw ValidationException::withMessages(['invoice' => __('Money has been received against this bill. Void the receipt first.')]);
        }
        $notes = Invoice::where('related_invoice_id', $invoice->id)->whereNotNull('finalized_at')->where('status', '!=', 'void')->pluck('number');
        if ($notes->isNotEmpty()) {
            throw ValidationException::withMessages(['invoice' => __('Void its notes and supplementary bills first: :numbers.', ['numbers' => $notes->implode(', ')])]);
        }

        return DB::transaction(function () use ($invoice, $reason, $by) {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $invoice->forceFill(['status' => 'void', 'voided_at' => now(), 'void_reason' => mb_substr($reason, 0, 255), 'balance_due' => 0])->save();

            if ($invoice->isCreditNote()) {
                $related = Invoice::whereKey($invoice->related_invoice_id)->lockForUpdate()->first();
                if ($related && (float) $invoice->credit_applied > 0) {
                    $related->forceFill(['amount_credited' => round(max(0, (float) $related->amount_credited - (float) $invoice->credit_applied), 3)]);
                    $related->settle();
                    $related->save();
                }
            } else {
                // The LRs can be billed again.
                Consignment::where('invoice_id', $invoice->id)->get()->each(fn (Consignment $c) => $c->forceFill([
                    'invoice_id' => null, 'status' => $c->pod_status === 'none' ? 'delivered' : 'pod_received',
                ])->save());
            }

            $this->balances->recompute($invoice->party_id);
            $this->audit->record('invoice.voided', "{$by->name} voided {$invoice->number}", 'invoice', (string) $invoice->id, $invoice->number, reason: $reason, severity: 'sensitive');

            return $invoice->fresh();
        });
    }

    // ---- Sending -----------------------------------------------------------

    public function recordDelivery(Invoice $invoice, string $channel, ?string $recipient = null, ?Account $by = null): void
    {
        DocumentDelivery::create(['document_type' => 'invoice', 'document_id' => $invoice->id, 'channel' => $channel, 'recipient' => $recipient ? mb_substr($recipient, 0, 255) : null,
            'status' => 'sent', 'sent_by' => $by?->id, 'sent_at' => now()]);
        if (in_array($channel, ['email', 'whatsapp', 'link'], true) && $invoice->status === 'finalised') {
            $invoice->forceFill(['status' => 'sent', 'sent_at' => now()])->save();
        }
    }

    /** The secure link was opened by someone outside. */
    public function markViewed(Invoice $invoice): void
    {
        if (in_array($invoice->status, ['finalised', 'sent'], true)) {
            $invoice->forceFill(['status' => 'viewed', 'viewed_at' => now()])->save();
        }
        DocumentDelivery::create(['document_type' => 'invoice', 'document_id' => $invoice->id, 'channel' => 'link', 'status' => 'opened', 'sent_at' => now()]);
    }

    // ---- Helpers -------------------------------------------------------------

    /** "19 MT × ₹3,400 per tonne" — how the freight was reached. */
    private function basisText(Consignment $c): ?string
    {
        if ($c->rate === null || $c->freight_basis === 'trip') {
            return null;
        }
        $qty = match ($c->freight_basis) {
            'tonne'   => rtrim(rtrim(number_format($c->weight(), 3), '0'), '.') . ' MT',
            'kg'      => number_format($c->weight() * 1000) . ' kg',
            'package' => ($c->packages ?? 0) . ' ' . ($c->package_type ?? __('packages')),
            'km'      => ($c->trip?->planned_km ?? 0) . ' km',
            default   => null,
        };

        return $qty ? $qty . ' × ' . \App\Support\Money::company($c->rate) . ' ' . __(Consignment::BASES[$c->freight_basis] ?? '') : null;
    }

    private function assertDraft(Invoice $invoice): void
    {
        if (! $invoice->isDraft()) {
            throw ValidationException::withMessages(['invoice' => __('Only a draft can be changed. Void the bill, or issue a credit or debit note.')]);
        }
    }

    private function assertNoteBase(Invoice $invoice, string $type): void
    {
        if (! $invoice->isFinal()) {
            throw ValidationException::withMessages(['invoice' => $invoice->isDraft() ? __('Issue the bill first; a draft is simply changed.') : __('This bill is void.')]);
        }
        $allowed = $type === 'credit_note' ? ['invoice', 'supplementary', 'debit_note'] : ['invoice'];
        if (! in_array($invoice->doc_type, $allowed, true)) {
            throw ValidationException::withMessages(['invoice' => __('A :doc is issued against an invoice.', ['doc' => mb_strtolower(__(Invoice::DOC_TYPES[$type]))])]);
        }
    }
}
