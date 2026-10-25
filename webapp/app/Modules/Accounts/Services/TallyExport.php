<?php

namespace App\Modules\Accounts\Services;

use App\Modules\Accounts\Models\AccountGroup;
use App\Modules\Accounts\Models\LedgerAccount;
use App\Modules\Accounts\Models\Voucher;
use Carbon\CarbonImmutable;
use XMLWriter;

/**
 * The book as a Tally import file (blueprint 07 §1: Tally XML for Indian
 * accountants): the groups and ledgers, then every voucher of the period
 * with its ledger entries. Our groups map onto Tally's reserved groups
 * (Sundry Debtors, Duties & Taxes…); our own sub-groups are created under
 * them. Debits go as negative amounts with ISDEEMEDPOSITIVE Yes, credits
 * positive with No, the way Tally writes its own exports.
 *
 * Import into a copy of the company in Tally first and compare the trial
 * balance (verify): Tally versions differ in what they accept.
 */
class TallyExport
{
    /** Our group code => Tally's reserved group. Groups not listed are created under their parent. */
    public const RESERVED = [
        'capital' => 'Capital Account', 'reserves' => 'Reserves & Surplus', 'loans_liability' => 'Loans (Liability)', 'current_liabilities' => 'Current Liabilities',
        'sundry_creditors' => 'Sundry Creditors', 'duties_taxes' => 'Duties & Taxes', 'provisions' => 'Provisions', 'suspense' => 'Suspense A/c',
        'fixed_assets' => 'Fixed Assets', 'current_assets' => 'Current Assets', 'sundry_debtors' => 'Sundry Debtors', 'cash_in_hand' => 'Cash-in-Hand',
        'bank_accounts' => 'Bank Accounts', 'loans_advances' => 'Loans & Advances (Asset)', 'direct_income' => 'Direct Incomes', 'indirect_income' => 'Indirect Incomes',
        'direct_expenses' => 'Direct Expenses', 'indirect_expenses' => 'Indirect Expenses',
    ];

    public function xml(string $companyName, CarbonImmutable $from, CarbonImmutable $to): string
    {
        $groups = AccountGroup::orderBy('sort_order')->get()->keyBy('id');
        $ledgers = LedgerAccount::orderBy('name')->get()->keyBy('id');
        $vouchers = Voucher::with('lines')->whereBetween('voucher_date', [$from->toDateString(), $to->toDateString()])->orderBy('voucher_date')->orderBy('id')->get();
        $groupName = fn (AccountGroup $g) => self::RESERVED[$g->code] ?? $g->name;
        // Tally keys ledgers by name: a crew member and a party of the same name get told apart.
        $taken = $groups->map($groupName)->flip()->all();
        $names = [];
        foreach ($ledgers as $ledger) {
            $name = $ledger->name;
            if (isset($taken[$name])) {
                $name .= ' (' . ($ledger->kind === 'person' ? 'crew' : $ledger->kind) . ' ' . $ledger->id . ')';
            }
            $taken[$name] = true;
            $names[$ledger->id] = $name;
        }

        $x = new XMLWriter();
        $x->openMemory();
        $x->setIndent(true);
        $x->startDocument('1.0', 'UTF-8');
        $x->startElement('ENVELOPE');
        $x->startElement('HEADER');
        $x->writeElement('TALLYREQUEST', 'Import Data');
        $x->endElement();
        $x->startElement('BODY');
        $x->startElement('IMPORTDATA');
        $x->startElement('REQUESTDESC');
        $x->writeElement('REPORTNAME', 'All Masters');
        $x->startElement('STATICVARIABLES');
        $x->writeElement('SVCURRENTCOMPANY', $companyName);
        $x->endElement();
        $x->endElement();
        $x->startElement('REQUESTDATA');

        // Masters: our own groups (Tally's reserved ones exist already), then the ledgers.
        foreach ($groups as $group) {
            if (isset(self::RESERVED[$group->code])) {
                continue;
            }
            $parent = $group->parent_id ? $groups[$group->parent_id] ?? null : null;
            $x->startElement('TALLYMESSAGE');
            $x->startElement('GROUP');
            $x->writeAttribute('NAME', $group->name);
            $x->writeAttribute('ACTION', 'Create');
            $this->nameList($x, $group->name);
            $x->writeElement('PARENT', $parent ? $groupName($parent) : '');
            $x->endElement();
            $x->endElement();
        }
        foreach ($ledgers as $ledger) {
            $x->startElement('TALLYMESSAGE');
            $x->startElement('LEDGER');
            $x->writeAttribute('NAME', $names[$ledger->id]);
            $x->writeAttribute('ACTION', 'Create');
            $this->nameList($x, $names[$ledger->id]);
            $x->writeElement('PARENT', $groupName($groups[$ledger->account_group_id]));
            $x->writeElement('ISBILLWISEON', $ledger->kind === 'party' ? 'Yes' : 'No');
            // Tally's opening balance: debit negative.
            $x->writeElement('OPENINGBALANCE', $this->amount(-(float) $ledger->opening_balance));
            $x->endElement();
            $x->endElement();
        }

        // Vouchers.
        foreach ($vouchers as $voucher) {
            $money = $voucher->lines->contains(fn ($l) => in_array($ledgers[$l->ledger_account_id]->kind ?? '', ['cash', 'bank'], true));
            $type = match ($voucher->voucher_type) {
                'sales' => 'Sales',
                'credit_note' => 'Credit Note',
                'receipt' => $money ? 'Receipt' : 'Journal',
                'payment' => $money ? 'Payment' : 'Journal',
                default => 'Journal',
            };
            $x->startElement('TALLYMESSAGE');
            $x->startElement('VOUCHER');
            $x->writeAttribute('VCHTYPE', $type);
            $x->writeAttribute('ACTION', 'Create');
            $x->writeElement('DATE', $voucher->voucher_date->format('Ymd'));
            $x->writeElement('VOUCHERTYPENAME', $type);
            $x->writeElement('VOUCHERNUMBER', $voucher->number);
            if ($voucher->reference) {
                $x->writeElement('REFERENCE', $voucher->reference);
            }
            $x->writeElement('NARRATION', $voucher->narration);
            foreach ($voucher->lines as $line) {
                $debit = (float) $line->debit > 0;
                $x->startElement('ALLLEDGERENTRIES.LIST');
                $x->writeElement('LEDGERNAME', $names[$line->ledger_account_id]);
                $x->writeElement('ISDEEMEDPOSITIVE', $debit ? 'Yes' : 'No');
                $x->writeElement('AMOUNT', $this->amount($debit ? -(float) $line->debit : (float) $line->credit));
                $x->endElement();
            }
            $x->endElement();
            $x->endElement();
        }

        $x->endElement(); // REQUESTDATA
        $x->endElement(); // IMPORTDATA
        $x->endElement(); // BODY
        $x->endElement(); // ENVELOPE
        $x->endDocument();

        return $x->outputMemory();
    }

    private function nameList(XMLWriter $x, string $name): void
    {
        $x->startElement('NAME.LIST');
        $x->writeElement('NAME', $name);
        $x->endElement();
    }

    private function amount(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
