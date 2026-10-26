<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Api\Owner\Concerns\RunsOnce;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Trips\Concerns\ValidatesLoads;
use App\Http\Controllers\Trips\Concerns\ValidatesTrips;
use App\Modules\Accounts\Models\LedgerAccount;
use App\Modules\Accounts\Services\CrewCash;
use App\Modules\Accounts\Services\Statements;
use App\Modules\Documents\Services\Compliance;
use App\Modules\Documents\Services\DocumentFiles;
use App\Modules\Documents\Services\DocumentService;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\InvoiceLine;
use App\Modules\Invoicing\Models\Receipt;
use App\Modules\Invoicing\Services\InvoiceService;
use App\Modules\Invoicing\Services\ReceiptService;
use App\Modules\Parties\Models\Party;
use App\Modules\People\Models\Person;
use App\Modules\Settings\Models\CompanyBankAccount;
use App\Modules\Settings\Models\MasterItem;
use App\Modules\Settings\Models\Place;
use App\Modules\Trips\Models\Consignment;
use App\Modules\Trips\Models\Trip;
use App\Modules\Trips\Services\ConsignmentService;
use App\Modules\Trips\Services\TripService;
use App\Platform\Tenancy\BranchContext;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The owner app making things (M23, api-v1 §12): a trip with its load (and
 * sending it), a bill from LRs (and issuing it), money received, a paper
 * photographed. The same services, rules and permissions as the web; every
 * write runs once per Idempotency-Key.
 */
class WriteController extends Controller
{
    use RunsOnce, ValidatesLoads, ValidatesTrips;

    public function __construct(protected CompanyContext $context, private TripService $trips, private ConsignmentService $consignments) {}

    /** What the forms pick from: places, clients, trucks, crew, materials, branches, bank accounts and the fixed lists. */
    public function masters(Compliance $compliance): JsonResponse
    {
        $busy = Trip::open()->whereNotNull('truck_id')->pluck('trip_number', 'truck_id');
        $branches = app(BranchContext::class);

        return response()->json([
            'places' => Place::active()->orderBy('name')->get(['id', 'name', 'short_code']),
            'clients' => Party::active()->withRole('client')->orderBy('name')->get(['id', 'name', 'short_code', 'mobile']),
            'trucks' => Truck::inBranch()->inService()->orderBy('reg_no')->get(['id', 'reg_no', 'truck_type', 'status'])
                ->map(fn ($t) => ['id' => $t->id, 'reg_no' => $t->reg_no, 'type' => $t->truck_type, 'status' => $t->status, 'busy_on' => $busy[$t->id] ?? null]),
            'crew' => Person::inBranch()->working()->whereIn('type', ['driver', 'helper', 'other'])->orderBy('name')->get(['id', 'name', 'type', 'mobile']),
            'materials' => MasterItem::ofList('material')->where('active', true)->orderBy('name')->get(['code', 'name']),
            'branches' => $branches->choices()->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'branch_required' => $branches->limited(),
            'bank_accounts' => CompanyBankAccount::where('active', true)->orderByDesc('is_default')->get()->map(fn ($b) => ['id' => $b->id, 'label' => $b->label . ' ' . $b->masked()]),
            'freight_bases' => Consignment::BASES, 'payment_types' => Consignment::PAYMENT_TYPES, 'receipt_modes' => Receipt::MODES,
            'paper_types' => ['truck' => $this->paperTypes($compliance, 'truck'), 'person' => $this->paperTypes($compliance, 'person')],
            // Quick cash for the crew (M27).
            'expense_categories' => MasterItem::ofList('expense_category')->where('active', true)->get(['code', 'name']),
            'cash_directions' => CrewCash::DIRECTIONS, 'cash_modes' => CrewCash::MODES, 'cash_purposes' => CrewCash::PURPOSES,
            // The masters' forms (M29).
            'forms' => [
                'truck_types' => MasterItem::ofList('truck_type')->where('active', true)->get(['code', 'name']),
                'body_types' => MasterItem::ofList('body_type')->where('active', true)->get(['code', 'name']),
                'fuel_types' => Truck::FUELS, 'ownerships' => Truck::OWNERSHIP, 'truck_statuses' => Truck::STATUSES,
                'person_types' => Person::TYPES, 'pay_bases' => Person::PAY_BASES, 'place_types' => Place::TYPES,
                'regions' => \App\Support\Regions::for($this->context->company()->country),
            ],
        ]);
    }

    /** A crew member's khata in short and the trips their cash can go with (M27). */
    public function crewKhata(Person $person, CrewCash $cash, Statements $statements): JsonResponse
    {
        $ledger = LedgerAccount::where('system_key', 'person:' . $person->id)->first();
        $buckets = $ledger ? $statements->crewBuckets($ledger) : ['trip_cash' => 0, 'earnings' => 0, 'loan' => 0, 'net' => 0];

        return response()->json([
            'person' => ['id' => $person->id, 'name' => $person->name, 'type' => $person->type, 'mobile' => $person->mobile],
            'ledger_id' => $ledger?->id,
            // Debit-positive, as in the ledger: trip cash and advance > 0 are the company's money with them; earnings < 0 is what we owe them.
            'trip_cash' => round($buckets['trip_cash'], 2), 'earnings' => round($buckets['earnings'], 2), 'advance' => round($buckets['loan'], 2), 'net' => round($buckets['net'], 2),
            'words' => \App\Http\Controllers\Accounts\LedgerController::words('person', $person->name, $buckets['net']),
            'trips' => $cash->trips($person)->map(fn ($t) => ['id' => $t->id, 'number' => $t->trip_number, 'date' => $t->trip_date?->toDateString(), 'status' => $t->status])->values(),
        ]);
    }

    /** Cash given to a crew member or spent by them, into their khata (M27). */
    public function storeCrewCash(Request $request, Person $person, CrewCash $cash): JsonResponse
    {
        return $this->once($request, 'crew_cash', function () use ($request, $person, $cash) {
            if ($person->status === 'left') {
                throw ValidationException::withMessages(['person' => __(':name has left the company.', ['name' => $person->name])]);
            }
            $voucher = $cash->record($person, $request->validate($cash->rules(), $cash->messages()), $request->user());

            return ['voucher' => ['id' => $voucher->id, 'number' => $voucher->number, 'date' => $voucher->voucher_date->toDateString(), 'amount' => (float) $voucher->total, 'narration' => $voucher->narration]];
        });
    }

    /** A trip with its load (or an empty run); `send: true` sends it to the crew at once. */
    public function storeTrip(Request $request): JsonResponse
    {
        return $this->once($request, 'trip', function () use ($request) {
            $request->merge([
                'trip_date' => $request->input('trip_date', CarbonImmutable::now($this->context->company()->timezone ?: 'UTC')->toDateString()),
                'load_type' => $request->input('load_type', 'ftl'), 'truck_source' => $request->input('truck_source', 'own'),
                'trip_kind' => $request->input('trip_kind', $request->has('load') ? 'loaded' : 'empty'),
            ]);
            [$fields, $crew] = $this->validatedTrip($request);
            $load = $fields['trip_kind'] === 'empty' ? null : $this->validatedLoad($request, 'load.');
            $ewaybills = $load ? $this->ewaybillsFrom($load) : [];
            if ($load) {
                $load['freight'] = $this->consignments->freight(new Trip($fields), $load);
            }
            try {
                $trip = $this->trips->create($fields, $crew, $load, $ewaybills);
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $k) => [$k === 'lr_number' ? 'load.lr_number' : $k => $m])->all());
            }
            if ($request->boolean('send')) {
                abort_unless($request->user()->can('trip.assign'), 403);
                $this->trips->assign($trip->fresh(), $request->input('override_reason'));
            }

            return ['trip' => $this->tripAnswer($trip)];
        });
    }

    /** Send a draft trip to its crew; papers that stop it need a reason of ten letters or more. */
    public function sendTrip(Request $request, string $uuid): JsonResponse
    {
        return $this->once($request, 'trip.send', function () use ($request, $uuid) {
            $data = $request->validate(['override_reason' => ['nullable', 'string', 'max:255']]);
            $trip = Trip::inBranch()->where('uuid', $uuid)->firstOrFail();
            $this->trips->assign($trip, $data['override_reason'] ?? null);

            return ['trip' => $this->tripAnswer($trip)];
        });
    }

    /** LRs waiting to be billed, oldest first; `?party=` for one client. */
    public function unbilled(Request $request): JsonResponse
    {
        $party = $request->integer('party') ?: null;
        $loads = Consignment::with(['billingParty:id,name', 'trip:id,uuid,trip_number,from_place_id,to_place_id', 'trip.from:id,name', 'trip.to:id,name'])
            ->waitingToBill()->where('payment_type', 'to_be_billed')
            ->when($party, fn ($q) => $q->where('billing_party_id', $party))->orderBy('lr_date')->orderBy('id')->limit(200)->get();

        return response()->json(['loads' => $loads->map(fn (Consignment $c) => [
            'id' => $c->id, 'lr_number' => $c->lr_number, 'lr_date' => $c->lr_date?->toDateString(), 'status' => $c->status,
            'party_id' => $c->billing_party_id, 'party' => $c->billingParty?->name, 'trip_uuid' => $c->trip?->uuid, 'trip' => $c->trip?->trip_number,
            'route' => trim(($c->trip?->from?->name ?? '') . ' → ' . ($c->trip?->to?->name ?? '')), 'freight' => (float) $c->freight,
        ])->values()]);
    }

    /** A bill for these LRs of one client; `finalise: true` issues it at once (it gets its number). */
    public function storeInvoice(Request $request, InvoiceService $invoices): JsonResponse
    {
        return $this->once($request, 'invoice', function () use ($request, $invoices) {
            $data = $request->validate([
                'party_id' => ['required', 'integer', Rule::exists('parties', 'id')->where('company_id', $this->context->id())],
                'loads' => ['required', 'array', 'min:1', 'max:200'], 'loads.*' => ['integer'],
                'finalise' => ['nullable', 'boolean'],
            ]);
            $loads = Consignment::whereIn('id', $data['loads'])->whereHas('trip', fn ($t) => $t->inBranch())->pluck('id')->all();
            $invoice = $invoices->draftFromLoads(Party::findOrFail($data['party_id']), $loads, $request->user());
            if ($request->boolean('finalise')) {
                abort_unless($request->user()->can('invoice.finalise'), 403);
                $invoice = $invoices->finalise($invoice->fresh(), $request->user());
            }

            return ['invoice' => MoneyController::row($invoice->fresh('party'), $this->today())];
        });
    }

    public function finaliseInvoice(Request $request, Invoice $invoice, InvoiceService $invoices): JsonResponse
    {
        return $this->once($request, 'invoice.finalise', function () use ($request, $invoice, $invoices) {
            if ($invoice->status !== 'draft') {
                throw ValidationException::withMessages(['invoice' => __('This bill is issued already.')]);
            }

            return ['invoice' => MoneyController::row($invoices->finalise($invoice, $request->user())->fresh('party'), $this->today())];
        });
    }

    /**
     * A credit or debit note against an issued bill (M43), with its reason:
     * a credit note for the whole bill (its lines copied) or for an amount
     * before tax (one line, the bill's service); a debit note with its lines
     * (detention, rate difference…). Issued at once when asked and allowed;
     * the tax is the bill's, by the engine, as on the web.
     */
    public function storeNote(Request $request, Invoice $invoice, InvoiceService $invoices): JsonResponse
    {
        return $this->once($request, 'invoice.note', function () use ($request, $invoice, $invoices) {
            $data = $request->validate([
                'type' => ['required', Rule::in(['credit_note', 'debit_note'])],
                'reason' => ['required', 'string', 'min:3', 'max:500'],
                'amount' => ['nullable', 'numeric', 'min:1', 'max:999999999'],
                'lines' => ['nullable', 'required_if:type,debit_note', 'array', 'min:1', 'max:20'],
                'lines.*.description' => ['required', 'string', 'max:255'],
                'lines.*.amount' => ['required', 'numeric', 'min:1', 'max:999999999'],
                'lines.*.service_class' => ['nullable', Rule::in(array_keys(\App\Modules\Tax\Services\ServiceClasses::CLASSES))],
                'finalise' => ['nullable', 'boolean'],
            ], ['lines.required_if' => __('Give the debit note’s lines.')]);
            if ($request->boolean('finalise') && ! $request->user()->can('invoice.finalise')) {
                abort(403);
            }
            $note = $data['type'] === 'credit_note' ? $invoices->draftCreditNote($invoice, $request->user()) : $invoices->draftAgainst($invoice, 'debit_note', $request->user());
            $lines = null;
            if ($data['type'] === 'credit_note' && isset($data['amount'])) {
                $lines = [['service_class' => $note->lines->first()?->service_class ?? 'FREIGHT_ROAD', 'description' => __('Credit against :number', ['number' => $invoice->number]),
                    'detail' => mb_substr($data['reason'], 0, 255), 'unit' => 'lump', 'quantity' => 1, 'unit_rate' => (float) $data['amount']]];
            } elseif ($data['type'] === 'debit_note') {
                $lines = array_map(fn ($l) => ['service_class' => $l['service_class'] ?? 'OTHER_CHARGES', 'description' => $l['description'], 'unit' => 'lump', 'quantity' => 1,
                    'unit_rate' => (float) $l['amount']], $data['lines']);
            }
            $note = $invoices->updateDraft($note, ['reason' => $data['reason']] + ($lines ? ['lines' => $lines] : []));
            if ($request->boolean('finalise')) {
                $note = $invoices->finalise($note->fresh(), $request->user());
            }

            return ['invoice' => MoneyController::row($note->fresh('party'), $this->today()) + ['against' => $invoice->number, 'reason' => $note->reason]];
        });
    }

    /** An LR's tracking link for the client (M45): made once, the same link after; the words to send with it. */
    public function trackLoad(Consignment $consignment, \App\Modules\Trips\Services\Tracking $tracking): JsonResponse
    {
        abort_unless(\App\Modules\Trips\Models\Trip::inBranch()->whereKey($consignment->trip_id)->exists() && $consignment->lr_number && $consignment->status !== 'cancelled', 404);
        $url = $tracking->link($consignment);

        return response()->json(['url' => $url, 'message' => $tracking->message($consignment, $url)]);
    }

    /** Money received: cash, UPI, NEFT or cheque, with TDS and deductions, set against the oldest bills. */
    public function storeReceipt(Request $request, ReceiptService $receipts): JsonResponse
    {
        return $this->once($request, 'receipt', function () use ($request, $receipts) {
            $companyId = $this->context->id();
            $data = $request->validate([
                'party_id' => ['required', 'integer', Rule::exists('parties', 'id')->where('company_id', $companyId)],
                'receipt_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:' . CarbonImmutable::now($this->context->company()->timezone ?: 'UTC')->toDateString()],
                'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
                'tds_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'], 'tds_section' => ['nullable', 'string', 'max:10'],
                'deduction_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'], 'deduction_note' => ['nullable', 'string', 'max:255'],
                'mode' => ['required', Rule::in(array_keys(Receipt::MODES))],
                'reference' => ['nullable', 'required_unless:mode,cash', 'string', 'max:60'],
                'cheque_date' => ['nullable', 'required_if:mode,cheque', 'date_format:Y-m-d'], 'bank_name' => ['nullable', 'string', 'max:120'],
                'bank_account_id' => ['nullable', 'integer', Rule::exists('company_bank_accounts', 'id')->where('company_id', $companyId)],
                'narration' => ['nullable', 'string', 'max:255'],
            ], ['reference.required_unless' => __('Give the UTR, cheque or UPI reference.')]);
            $data['narration'] = trim((string) ($data['narration'] ?? '')) ?: __('Received, entered on the owner app');
            $data['allocation'] = 'oldest';
            $data['allocations'] = null;
            $receipt = $receipts->record(Party::findOrFail($data['party_id']), $data, $request->user());
            $receipt->load('allocations.invoice:id,number');

            return ['receipt' => [
                'id' => $receipt->id, 'number' => $receipt->number, 'date' => $receipt->receipt_date->toDateString(), 'amount' => (float) $receipt->amount,
                'applied' => $receipt->allocations->map(fn ($a) => ['invoice' => $a->invoice?->number, 'amount' => (float) $a->amount])->values(),
                'share_url' => $receipt->public_token ? route('documents.public', $receipt->public_token) : null,
            ]];
        });
    }

    /** A paper photographed on the phone: a truck's or a person's, with its number and expiry. */
    public function storePaper(Request $request, DocumentService $documents, Compliance $compliance): JsonResponse
    {
        return $this->once($request, 'paper', function () use ($request, $documents, $compliance) {
            $owner = $request->validate(['owner_type' => ['required', Rule::in(['truck', 'person'])], 'owner_id' => ['required', 'integer']]);
            $model = $owner['owner_type'] === 'truck' ? Truck::inBranch()->find($owner['owner_id']) : Person::inBranch()->find($owner['owner_id']);
            if (! $model) {
                throw ValidationException::withMessages(['owner_id' => __('That truck or person is not found.')]);
            }
            $data = $request->validate([
                'document_type' => ['required', Rule::in(array_keys($this->paperTypes($compliance, $owner['owner_type'])))],
                'number' => ['nullable', 'string', 'max:60'], 'authority' => ['nullable', 'string', 'max:120'],
                'issued_on' => ['nullable', 'date_format:Y-m-d'], 'valid_from' => ['nullable', 'date_format:Y-m-d'],
                'expires_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
                'amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'], 'notes' => ['nullable', 'string', 'max:2000'],
                'files' => ['required', 'array', 'min:1', 'max:6'], 'files.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:' . DocumentFiles::UPLOAD_MAX_KB],
            ], ['files.required' => __('Add the photo of the paper.')]);
            $document = $documents->create($owner['owner_type'], (int) $owner['owner_id'], collect($data)->except('files')->all(), $request->file('files', []));
            $document->load('files');

            return ['paper' => [
                'id' => $document->id, 'type' => $document->document_type, 'type_label' => $compliance->typeName($document->document_type), 'number' => $document->number,
                'expires_on' => $document->expires_on?->toDateString(), 'files' => $document->files->count(),
            ]];
        });
    }

    private function tripAnswer(Trip $trip): array
    {
        $trip = $trip->fresh(['consignments', 'from:id,name', 'to:id,name', 'truck:id,reg_no']);

        return [
            'uuid' => $trip->uuid, 'number' => $trip->trip_number, 'status' => $trip->status, 'status_label' => __(Trip::STATUSES[$trip->status] ?? $trip->status),
            'date' => $trip->trip_date?->toDateString(), 'from' => $trip->from?->name, 'to' => $trip->to?->name, 'vehicle' => $trip->truck?->reg_no, 'branch_id' => $trip->branch_id,
            'loads' => $trip->consignments->map(fn ($c) => ['id' => $c->id, 'lr_number' => $c->lr_number, 'freight' => (float) $c->freight])->values(),
        ];
    }

    /** The paper types for trucks or people (ID papers only with `people.id_documents`). */
    private function paperTypes(Compliance $compliance, string $ownerType): array
    {
        $sensitive = request()->user()?->can('people.id_documents') ?? false;

        return $compliance->types()->filter(fn ($t) => $t['owner'] === $ownerType && $t['active'] && (! $t['sensitive'] || $sensitive))->map(fn ($t) => $t['name'])->all();
    }

    private function today(): string
    {
        return CarbonImmutable::now($this->context->company()->timezone ?: 'UTC')->toDateString();
    }
}
