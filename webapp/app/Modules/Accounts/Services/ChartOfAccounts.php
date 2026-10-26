<?php

namespace App\Modules\Accounts\Services;

use App\Modules\Accounts\Models\AccountGroup;
use App\Modules\Accounts\Models\LedgerAccount;
use App\Modules\Parties\Models\Party;
use App\Modules\People\Models\Person;
use App\Modules\Settings\Models\CompanyBankAccount;
use App\Modules\Settings\Models\MasterItem;
use App\Modules\Tax\Services\ServiceClasses;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Facades\DB;

/**
 * The chart of accounts (blueprint 07 §1): a ready-made tree per country —
 * Tally-style groups in India, IFRS-style names in the UAE — and the ledgers
 * the system posts to. Nothing has to be set up by hand: a group or system
 * ledger is made the first time it is needed, and so is the ledger of each
 * client, supplier, vendor, crew member, bank account, expense category and
 * tax. The accountant may rename ledgers and add their own; system keys stay.
 */
class ChartOfAccounts
{
    /** code => [name, nature, parent code, sort] */
    public const GROUPS = [
        'capital'             => ['Capital account', 'equity', null, 10],
        'reserves'            => ['Reserves and surplus', 'equity', 'capital', 11],
        'loans_liability'     => ['Loans (liability)', 'liability', null, 20],
        'current_liabilities' => ['Current liabilities', 'liability', null, 30],
        'sundry_creditors'    => ['Sundry creditors', 'liability', 'current_liabilities', 31],
        'duties_taxes'        => ['Duties and taxes', 'liability', 'current_liabilities', 32],
        'provisions'          => ['Provisions', 'liability', 'current_liabilities', 33],
        'suspense'            => ['Suspense', 'liability', null, 39],
        'fixed_assets'        => ['Fixed assets', 'asset', null, 40],
        'current_assets'      => ['Current assets', 'asset', null, 50],
        'sundry_debtors'      => ['Sundry debtors', 'asset', 'current_assets', 51],
        'cash_in_hand'        => ['Cash in hand', 'asset', 'current_assets', 52],
        'bank_accounts'       => ['Bank accounts', 'asset', 'current_assets', 53],
        'loans_advances'      => ['Loans and advances (asset)', 'asset', 'current_assets', 54],
        'crew_accounts'       => ['Crew accounts', 'asset', 'loans_advances', 55],
        'direct_income'       => ['Freight and operating income', 'income', null, 60],
        'indirect_income'     => ['Indirect income', 'income', null, 61],
        'direct_expenses'     => ['Trip and running expenses', 'expense', null, 70],
        'maintenance'         => ['Truck maintenance', 'expense', 'direct_expenses', 71],
        'indirect_expenses'   => ['Office and indirect expenses', 'expense', null, 72],
    ];

    /** Group names that differ in the UAE (IFRS wording). */
    public const GROUP_NAMES = [
        'AE' => [
            'capital' => 'Equity', 'reserves' => 'Retained earnings', 'sundry_creditors' => 'Trade payables', 'duties_taxes' => 'VAT and other taxes',
            'sundry_debtors' => 'Trade receivables', 'cash_in_hand' => 'Cash', 'loans_advances' => 'Advances and prepayments',
            'direct_income' => 'Revenue', 'indirect_income' => 'Other income', 'direct_expenses' => 'Cost of revenue',
            'indirect_expenses' => 'General and administrative expenses', 'loans_liability' => 'Borrowings',
        ],
    ];

    /** system key => [name, group code, kind] */
    public const SYSTEM = [
        'cash'               => ['Office cash', 'cash_in_hand', 'cash'],
        'bank'               => ['Bank (account not chosen)', 'bank_accounts', 'bank'],
        'freight_income'     => ['Freight income', 'direct_income', 'general'],
        'commission_income'  => ['Commission earned', 'indirect_income', 'general'],
        'office_charges'     => ['Office charges recovered (munshiana)', 'indirect_income', 'general'],
        'supplier_deductions' => ['Deductions recovered from truck suppliers', 'indirect_income', 'general'],
        'road_receipts'      => ['Money received on the road (to be explained)', 'suspense', 'general'],
        'tds_receivable'     => ['TDS receivable (deducted by clients)', 'loans_advances', 'tax'],
        'tds_payable'        => ['TDS payable (on lorry hire and contracts)', 'duties_taxes', 'tax'],
        'client_deductions'  => ['Deductions by clients (shortage, damage, late)', 'direct_expenses', 'general'],
        'round_off'          => ['Round off', 'indirect_expenses', 'general'],
        'lorry_hire'         => ['Lorry hire (hired trucks)', 'direct_expenses', 'general'],
        'commission_expense' => ['Brokerage and commission paid', 'direct_expenses', 'general'],
        'bata'               => ['Bata and daily allowance', 'direct_expenses', 'general'],
        'salaries'           => ['Salaries and wages', 'direct_expenses', 'general'],
        'bank_charges'       => ['Bank charges', 'indirect_expenses', 'general'],
        'bank_interest'      => ['Bank interest received', 'indirect_income', 'general'],
        'staff_recoveries'   => ['Recoveries from staff (fines, damage)', 'indirect_income', 'general'],
        'fuel_card'          => ['Fuel cards', 'loans_advances', 'general'],
        'fastag'             => ['FASTag / Salik / Darb wallet', 'loans_advances', 'general'],
        'vendor_credit'      => ['Pump and vendor credit (vendor not named)', 'sundry_creditors', 'general'],
        'accident_repairs'   => ['Accident repairs', 'maintenance', 'general'],
        'opening_difference' => ['Difference in opening balances', 'suspense', 'general'],
        'owners_capital'     => ['Owner’s capital', 'capital', 'general'],
        'loan_interest'      => ['Interest on truck loans', 'indirect_expenses', 'general'],
    ];

    /** Expense category group (master list meta) => chart group. */
    private const EXPENSE_GROUPS = ['running' => 'direct_expenses', 'maintenance' => 'maintenance', 'office' => 'indirect_expenses'];

    /** @var array<string, LedgerAccount> per company and key, within one call chain */
    private array $memo = [];

    public function __construct(private CompanyContext $context) {}

    /** The whole default tree and the system ledgers, made where missing. Safe to run again. */
    public function ensure(): void
    {
        foreach (array_keys(self::GROUPS) as $code) {
            $this->group($code);
        }
        foreach (array_keys(self::SYSTEM) as $key) {
            $this->ledger($key);
        }
    }

    public function group(string $code): AccountGroup
    {
        [$name, $nature, $parent, $sort] = self::GROUPS[$code] ?? throw new \InvalidArgumentException("Unknown account group {$code}.");
        $country = $this->context->company()->country;

        return AccountGroup::firstOrCreate(['code' => $code], [
            'name' => self::GROUP_NAMES[$country][$code] ?? $name, 'nature' => $nature, 'sort_order' => $sort, 'is_system' => true,
            'parent_id' => $parent ? $this->group($parent)->id : null,
        ]);
    }

    /** A system ledger by its key (cash, freight_income…), made the first time. */
    public function ledger(string $key): LedgerAccount
    {
        [$name, $group, $kind] = self::SYSTEM[$key] ?? throw new \InvalidArgumentException("Unknown system ledger {$key}.");

        return $this->keyed($key, fn () => ['name' => $name, 'account_group_id' => $this->group($group)->id, 'kind' => $kind]);
    }

    /** A client's, supplier's or vendor's ledger. A party that is a client sits with the debtors, any other with the creditors. */
    public function party(Party $party): LedgerAccount
    {
        return $this->keyed('party:' . $party->id, function () use ($party) {
            $client = $party->relationLoaded('roles') ? $party->roles->contains('role', 'client') : $party->roles()->where('role', 'client')->exists();

            return ['name' => $party->name, 'account_group_id' => $this->group($client ? 'sundry_debtors' : 'sundry_creditors')->id, 'kind' => 'party', 'party_id' => $party->id];
        });
    }

    /** A truck's loan (M35): what is still owed to the financier, paid down by the EMIs' principal. */
    public function truckLoan(\App\Modules\Fleet\Models\Truck $truck): LedgerAccount
    {
        return $this->keyed('truck_loan:' . $truck->id, fn () => ['name' => trim(__('Truck loan') . ' — ' . $truck->reg_no . ($truck->financier ? ' (' . $truck->financier . ')' : '')),
            'account_group_id' => $this->group('loans_liability')->id, 'kind' => 'general']);
    }

    /** A crew member's personal account (khata): trip cash and earnings are buckets of it. */
    public function person(Person $person): LedgerAccount
    {
        return $this->keyed('person:' . $person->id, fn () => ['name' => $person->name, 'account_group_id' => $this->group('crew_accounts')->id, 'kind' => 'person', 'person_id' => $person->id]);
    }

    /** One of our bank accounts; without one, the general bank ledger. */
    public function bank(?int $bankAccountId = null): LedgerAccount
    {
        $bank = $bankAccountId ? CompanyBankAccount::find($bankAccountId) : CompanyBankAccount::where('active', true)->orderByDesc('is_default')->first();
        if (! $bank) {
            return $this->ledger('bank');
        }

        return $this->keyed('bank:' . $bank->id, fn () => ['name' => $bank->label . ' ' . $bank->masked(), 'account_group_id' => $this->group('bank_accounts')->id, 'kind' => 'bank', 'company_bank_account_id' => $bank->id]);
    }

    /** Where money paid or received in this mode goes: cash, or the bank. */
    public function money(?string $mode, ?int $bankAccountId = null): LedgerAccount
    {
        return in_array($mode, [null, '', 'cash'], true) ? $this->ledger('cash') : $this->bank($bankAccountId);
    }

    /** The expense ledger of a category of the company's list (fuel, toll, repairs…). */
    public function expense(?string $category): LedgerAccount
    {
        $category = $category ?: 'other';

        return $this->keyed('expense:' . $category, function () use ($category) {
            $item = MasterItem::where('list', 'expense_category')->where('code', $category)->first();
            $group = self::EXPENSE_GROUPS[$item?->meta['group'] ?? 'running'] ?? 'direct_expenses';

            return ['name' => $item?->name ?? ucfirst(str_replace('_', ' ', $category)), 'account_group_id' => $this->group($group)->id, 'kind' => 'general'];
        });
    }

    /** The income ledger of a charge on a bill: freight, or each other charge class (detention, loading recovered…). */
    public function income(string $serviceClass): LedgerAccount
    {
        if ($serviceClass === 'FREIGHT_ROAD') {
            return $this->ledger('freight_income');
        }

        return $this->keyed('income:' . $serviceClass, fn () => ['name' => ServiceClasses::label($serviceClass), 'account_group_id' => $this->group('direct_income')->id, 'kind' => 'general']);
    }

    /** Income typed by the office on a trip (TripOfficeEntry::INCOME). */
    public function officeIncome(string $category, string $label): LedgerAccount
    {
        if ($category === 'commission') {
            return $this->ledger('commission_income');
        }

        return $this->keyed('trip_income:' . $category, fn () => ['name' => $label, 'account_group_id' => $this->group(in_array($category, ['detention', 'extra_freight', 'loading'], true) ? 'direct_income' : 'indirect_income')->id, 'kind' => 'general']);
    }

    /** Output tax we charged (CGST, SGST, IGST, VAT…), one ledger per tax. */
    public function outputTax(string $code): LedgerAccount
    {
        $code = strtoupper($code);

        return $this->keyed('output_tax:' . $code, fn () => ['name' => __('Output :tax', ['tax' => $code]), 'account_group_id' => $this->group('duties_taxes')->id, 'kind' => 'tax']);
    }

    // ---- Helpers --------------------------------------------------------------------

    /** Find by system key, or make it — once, even when two requests ask at the same moment. */
    private function keyed(string $key, callable $attributes): LedgerAccount
    {
        $memoKey = $this->context->id() . '|' . $key;
        if (isset($this->memo[$memoKey]) && $this->memo[$memoKey]->exists) {
            return $this->memo[$memoKey];
        }

        $ledger = LedgerAccount::where('system_key', $key)->first();
        if (! $ledger) {
            $ledger = DB::transaction(fn () => LedgerAccount::where('system_key', $key)->lockForUpdate()->first()
                ?? LedgerAccount::create($attributes() + ['system_key' => $key, 'is_system' => ! str_contains($key, ':') || str_starts_with($key, 'output_tax:')]));
        }

        return $this->memo[$memoKey] = $ledger;
    }
}
