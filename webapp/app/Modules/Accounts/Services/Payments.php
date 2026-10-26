<?php

namespace App\Modules\Accounts\Services;

use App\Models\Account;
use App\Modules\Accounts\Models\LedgerAccount;
use App\Modules\Accounts\Models\Voucher;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Parties\Models\Party;
use App\Modules\People\Models\Person;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * A payment voucher (PV/26-27/00001): money paid to a vendor (the pump's
 * monthly bill, the workshop), a truck supplier outside a hire challan, a
 * crew member's earnings (bata, salary), or any expense ledger — from cash or
 * one of our bank accounts. One balanced voucher; the payee's ledger shows
 * it. The web's Accounts → Payment and the owner app (M31) both post here.
 */
class Payments
{
    public function __construct(private Books $books, private ChartOfAccounts $chart, private CompanyContext $context) {}

    /**
     * Who can be paid: parties and people, and expense, liability or asset
     * ledgers (never cash or bank). Suppliers, vendors and brokers and the
     * working crew are payable before anything is posted to them: their
     * ledger is made here.
     */
    public function payees(): Collection
    {
        $this->chart->ensure();
        Party::active()->whereHas('roles', fn ($q) => $q->whereIn('role', ['supplier', 'vendor', 'broker']))
            ->whereNotIn('id', LedgerAccount::whereNotNull('party_id')->select('party_id'))->with('roles')->get()->each(fn ($p) => $this->chart->party($p));
        Person::working()->whereNotIn('id', LedgerAccount::whereNotNull('person_id')->select('person_id'))->get()->each(fn ($p) => $this->chart->person($p));

        return LedgerAccount::with('group:id,name,nature')->where('active', true)->whereNotIn('kind', ['cash', 'bank'])->orderBy('name')
            ->get(['id', 'name', 'kind', 'account_group_id'])
            ->filter(fn ($l) => in_array($l->kind, ['party', 'person'], true) || in_array($l->group?->nature, ['expense', 'liability', 'asset'], true))->values();
    }

    public function rules(): array
    {
        $company = $this->context->id();
        $today = CarbonImmutable::now($this->context->company()->timezone ?: 'UTC')->toDateString();

        return [
            'ledger_account_id' => ['required', 'integer', Rule::exists('ledger_accounts', 'id')->where('company_id', $company)->whereNotIn('kind', ['cash', 'bank'])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999'],
            'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:' . $today],
            'mode' => ['required', Rule::in(['cash', 'bank'])],
            'bank_account_id' => ['nullable', 'integer', Rule::exists('company_bank_accounts', 'id')->where('company_id', $company)],
            'reference' => ['nullable', 'string', 'max:60', 'required_if:mode,bank'],
            'narration' => ['required', 'string', 'min:3', 'max:255'],
            'truck_id' => ['nullable', 'integer', Rule::exists('trucks', 'id')->where('company_id', $company)],
        ];
    }

    /**
     * An expense paid for one truck (a repair, a spare part) can say which:
     * the payee's line then carries the truck, so the truck's profit and its
     * fixed costs a day (M37) count it. A paper's premium typed on the paper
     * is counted from the paper, so it should not be tagged as well.
     *
     * @param array{ledger_account_id: int, amount: mixed, paid_on: string, mode: string, bank_account_id?: ?int, reference?: ?string, narration: string, truck_id?: ?int} $data
     */
    public function pay(array $data, Account $by): Voucher
    {
        $payee = LedgerAccount::findOrFail($data['ledger_account_id']);
        $truck = ! empty($data['truck_id']) ? Truck::findOrFail($data['truck_id']) : null;
        $centres = $truck ? ['truck_id' => $truck->id, 'branch_id' => $truck->branch_id] : [];

        return $this->books->post([
            'type' => 'payment', 'series' => 'payment_voucher', 'date' => $data['paid_on'], 'reference' => $data['reference'] ?? null,
            'narration' => __('Paid to :name: :what', ['name' => $payee->name, 'what' => $data['narration']]), 'created_by' => $by->id,
        ], [
            ['ledger' => $payee, 'debit' => (float) $data['amount'], 'bucket' => $payee->kind === 'person' ? 'earnings' : null] + $centres,
            ['ledger' => $this->chart->money($data['mode'], $data['bank_account_id'] ?? null), 'credit' => (float) $data['amount']],
        ]);
    }
}
