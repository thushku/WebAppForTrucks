<?php

namespace App\Modules\Accounts\Services;

use App\Models\Account;
use App\Modules\Accounts\Models\Voucher;
use App\Modules\People\Models\Person;
use App\Modules\Settings\Models\MasterItem;
use App\Modules\Trips\Models\Trip;
use App\Modules\Trips\Models\TripAdvance;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Quick cash for a crew member (M27, 13-parity #23), from the office or the
 * owner app, straight into the person's khata:
 *
 *  · "Given to them" — cash, UPI or bank handed over. For a trip it is that
 *    trip's advance (the same record as on the trip page, so the trip's
 *    settlement counts it). Without a trip it is either an advance against
 *    their pay (the loan bucket, taken back from a payslip) or paying what
 *    the company owes them (their earnings).
 *  · "Spent for the company" — their own money spent on our behalf: the
 *    expense, tagged with the trip and truck when given, and the company owes
 *    it to them with their pay (earnings). It is not part of a trip's cash
 *    settlement: what they spend on the road they enter in the crew app.
 *
 * A wrong entry is reversed in the books like any voucher, never edited.
 */
class CrewCash
{
    public const DIRECTIONS = ['received' => 'Given to them', 'spent' => 'Spent for the company'];

    public const MODES = ['cash' => 'Cash', 'upi' => 'UPI', 'bank' => 'Bank transfer'];

    /** What money given without a trip is for. */
    public const PURPOSES = ['advance' => 'An advance against pay', 'dues' => 'Paying what we owe them'];

    /** Trips a crew member's cash can be tagged with: sent and not yet settled. */
    public const TRIP_STATUSES = ['assigned', 'accepted', 'running', 'ended'];

    public function __construct(private Books $books, private ChartOfAccounts $chart, private AuditRecorder $audit, private CompanyContext $context) {}

    /** The person's trips to choose from, newest first. */
    public function trips(Person $person): Collection
    {
        return $this->tripsOf($person)->orderByDesc('trip_date')->orderByDesc('id')->limit(20)->get(['id', 'trip_number', 'trip_date', 'status', 'truck_id', 'branch_id']);
    }

    /** Trips this person is on that are sent and not settled. */
    private function tripsOf(Person $person): \Illuminate\Database\Eloquent\Builder
    {
        return Trip::whereIn('status', self::TRIP_STATUSES)->whereHas('crew', fn ($q) => $q->where('person_id', $person->id));
    }

    /** The same rules for the web form and the owner app. */
    public function rules(): array
    {
        $company = $this->context->id();
        $today = CarbonImmutable::now($this->context->company()->timezone ?: 'UTC')->toDateString();

        return [
            'direction' => ['required', Rule::in(array_keys(self::DIRECTIONS))],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'remark' => ['required', 'string', 'min:3', 'max:200'],
            'on' => ['required', 'date_format:Y-m-d', 'before_or_equal:' . $today],
            'trip_id' => ['nullable', 'integer'],
            'mode' => ['nullable', 'required_if:direction,received', Rule::in(array_keys(self::MODES))],
            'purpose' => ['nullable', Rule::in(array_keys(self::PURPOSES))],
            'category' => ['nullable', 'required_if:direction,spent', Rule::exists('master_items', 'code')->where('company_id', $company)->where('list', 'expense_category')],
            'reference' => ['nullable', 'string', 'max:60'],
            'bank_account_id' => ['nullable', 'integer', Rule::exists('company_bank_accounts', 'id')->where('company_id', $company)],
        ];
    }

    /** The messages that go with rules(). */
    public function messages(): array
    {
        return ['category.required_if' => __('Choose what it was spent on.'), 'mode.required_if' => __('Choose how it was given.'), 'remark.required' => __('Write what it is for.'),
            'on.before_or_equal' => __('The date cannot be after today.')];
    }

    /**
     * Record one entry (validated with rules()) and return its voucher.
     *
     * @param array{direction: string, amount: mixed, remark: string, on: string, trip_id?: ?int, mode?: ?string, purpose?: ?string, category?: ?string, reference?: ?string, bank_account_id?: ?int} $data
     */
    public function record(Person $person, array $data, Account $by): Voucher
    {
        $amount = round((float) $data['amount'], 2);
        $remark = trim($data['remark']);
        $on = CarbonImmutable::parse($data['on'])->startOfDay();
        $reference = ($data['reference'] ?? null) ?: null;
        $mode = $data['mode'] ?? 'cash';
        $trip = null;
        if ($data['trip_id'] ?? null) {
            $trip = $this->tripsOf($person)->find((int) $data['trip_id']);
            if (! $trip) {
                throw ValidationException::withMessages(['trip_id' => __('Choose one of :name’s trips that is not settled yet.', ['name' => $person->name])]);
            }
        }
        if ($data['direction'] === 'received' && $mode !== 'cash' && ! $reference) {
            throw ValidationException::withMessages(['reference' => __('Give the UPI or bank reference.')]);
        }

        return DB::transaction(function () use ($person, $data, $by, $amount, $remark, $on, $reference, $mode, $trip) {
            if ($data['direction'] === 'received' && $trip) {
                // The trip's own advance: posted by the book keeper, counted by the settlement.
                $advance = TripAdvance::create(['trip_id' => $trip->id, 'person_id' => $person->id, 'amount' => $amount, 'mode' => $mode,
                    'given_on' => $on->toDateString(), 'reference' => $reference, 'note' => $remark, 'created_by' => $by->id]);
                $voucher = Voucher::where('source_type', 'trip_advance')->where('source_id', $advance->id)->firstOrFail();
            } else {
                $voucher = $data['direction'] === 'received' ? $this->given($person, $data, $by, $amount, $remark, $on, $reference, $mode) : $this->spent($person, $data, $by, $amount, $remark, $on, $reference, $trip);
            }
            $this->audit->record('crew.cash_' . $data['direction'], ($data['direction'] === 'received' ? "Gave {$person->name} " : "{$person->name} spent ") . number_format($amount, 2) . " — {$remark}" . ($trip ? " ({$trip->trip_number})" : ''),
                'person', (string) $person->id, $person->name, severity: 'notable');

            return $voucher;
        });
    }

    /** Money given without a trip: an advance against pay, or paying what we owe them. */
    private function given(Person $person, array $data, Account $by, float $amount, string $remark, CarbonImmutable $on, ?string $reference, string $mode): Voucher
    {
        $dues = ($data['purpose'] ?? 'advance') === 'dues';

        return $this->books->post([
            'type' => 'payment', 'series' => 'payment_voucher', 'date' => $on, 'reference' => $reference, 'created_by' => $by->id,
            'narration' => ($dues ? __('Paid to :name: :what', ['name' => $person->name, 'what' => $remark]) : __('Advance against pay to :name: :what', ['name' => $person->name, 'what' => $remark])),
        ], [
            ['ledger' => $this->chart->person($person), 'debit' => $amount, 'bucket' => $dues ? 'earnings' : 'loan', 'note' => $remark, 'branch_id' => $person->branch_id],
            ['ledger' => $this->chart->money($mode, $data['bank_account_id'] ?? null), 'credit' => $amount],
        ]);
    }

    /** Their own money spent for us: the expense, and the company owes it with their pay. */
    private function spent(Person $person, array $data, Account $by, float $amount, string $remark, CarbonImmutable $on, ?string $reference, ?Trip $trip): Voucher
    {
        $centres = $trip ? ['trip_id' => $trip->id, 'truck_id' => $trip->truck_id, 'branch_id' => $trip->branch_id] : ['branch_id' => $person->branch_id];
        $category = MasterItem::where('list', 'expense_category')->where('code', $data['category'])->value('name') ?? $data['category'];

        return $this->books->post([
            'type' => 'journal', 'series' => 'journal', 'date' => $on, 'created_by' => $by->id,
            'reference' => $reference ?? $trip?->trip_number,
            'narration' => __(':name spent for the company (:category): :what', ['name' => $person->name, 'category' => $category, 'what' => $remark]),
        ], [
            ['ledger' => $this->chart->expense($data['category']), 'debit' => $amount, 'note' => $remark] + $centres,
            ['ledger' => $this->chart->person($person), 'credit' => $amount, 'bucket' => 'earnings', 'note' => $remark] + $centres,
        ]);
    }
}
