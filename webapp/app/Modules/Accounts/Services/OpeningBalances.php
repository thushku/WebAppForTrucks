<?php

namespace App\Modules\Accounts\Services;

use App\Modules\Accounts\Models\LedgerAccount;
use App\Modules\Parties\Models\Party;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Tenancy\CompanyContext;
use App\Support\FiscalYear;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The balances the company starts with (M19): what each party, crew member,
 * bank and the cash held when the book started here. Kept on the ledgers
 * (positive: debit), dated the first day of the financial year; whatever does
 * not balance goes to "Difference in opening balances", so the trial balance
 * always agrees and the difference stays visible until it is explained.
 * Not changed once the books are locked.
 */
class OpeningBalances
{
    /**
     * Only what the balance sheet carries has an opening balance: income and
     * expenses start each year at nothing. A company starting mid-year puts
     * the year's profit so far in "Reserves and surplus".
     */
    public const NATURES = ['asset', 'liability', 'equity'];

    public function __construct(private CompanyContext $context, private ChartOfAccounts $chart, private Books $books, private AuditRecorder $audit) {}

    /** @param array<int, float> $balances ledger id => amount (positive debit, negative credit) */
    public function save(array $balances): float
    {
        if ($this->books->lockedUntil()) {
            throw ValidationException::withMessages(['opening' => __('The books are locked. Unlock them (Accounts → Lock) to change opening balances.')]);
        }
        $difference = $this->chart->ledger('opening_difference');
        $company = $this->context->company();
        $start = FiscalYear::containing(CarbonImmutable::now($company->timezone ?: 'UTC'), (int) ($company->fy_start_month ?: 4))->start;

        return DB::transaction(function () use ($balances, $difference, $start) {
            $changed = 0;
            $sheet = fn ($q) => $q->whereIn('nature', self::NATURES);
            foreach (LedgerAccount::whereIn('id', array_keys($balances))->where('id', '!=', $difference->id)->whereHas('group', $sheet)->lockForUpdate()->get() as $ledger) {
                $amount = round((float) $balances[$ledger->id], 3);
                if (abs($amount - (float) $ledger->opening_balance) < 0.0005) {
                    continue;
                }
                $ledger->forceFill(['opening_balance' => $amount, 'opening_date' => $amount ? $start->toDateString() : null])->save();
                $changed++;
            }
            // What does not balance sits in one visible ledger.
            $rest = round((float) LedgerAccount::where('id', '!=', $difference->id)->sum('opening_balance'), 3);
            $difference->forceFill(['opening_balance' => -$rest, 'opening_date' => $rest ? $start->toDateString() : null])->save();
            if ($changed) {
                $this->audit->record('accounts.opening_saved', "Opening balances changed on {$changed} ledger(s); difference " . number_format($rest, 2), 'ledger', null, severity: 'notable');
            }

            return $rest;
        });
    }

    /**
     * Rows of a CSV with a name and a debit or credit (or one signed amount),
     * matched to ledgers by name — a party without a ledger yet gets one.
     *
     * @return array{balances: array<int, float>, unknown: string[]}
     */
    public function fromCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        $header = null;
        while (($cells = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $cells = array_map(fn ($c) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $c)), $cells);
            if ($header === null) {
                $lower = array_map('mb_strtolower', $cells);
                if (in_array('name', $lower, true) || in_array('ledger', $lower, true) || in_array('party', $lower, true)) {
                    $header = $lower;
                }

                continue;
            }
            $row = [];
            foreach ($header as $i => $column) {
                $row[$column] = $cells[$i] ?? '';
            }
            $name = $row['name'] ?? $row['ledger'] ?? $row['party'] ?? '';
            if ($name === '') {
                continue;
            }
            $num = fn ($v) => (float) str_replace([',', ' '], '', (string) $v);
            $amount = isset($row['amount']) ? $num($row['amount']) * (preg_match('/cr/i', (string) ($row['type'] ?? $row['dr/cr'] ?? 'dr')) ? -1 : 1)
                : $num($row['debit'] ?? $row['dr'] ?? 0) - $num($row['credit'] ?? $row['cr'] ?? 0);
            $rows[] = [$name, round($amount, 3)];
        }
        fclose($handle);
        if ($header === null) {
            throw ValidationException::withMessages(['file' => __('The file needs a header row with Name and Debit / Credit (or Amount and Type).')]);
        }

        $ledgers = LedgerAccount::whereHas('group', fn ($q) => $q->whereIn('nature', self::NATURES))->get(['id', 'name'])->keyBy(fn ($l) => mb_strtolower($l->name));
        $balances = [];
        $unknown = [];
        foreach ($rows as [$name, $amount]) {
            $ledger = $ledgers[mb_strtolower($name)] ?? null;
            if (! $ledger && ($party = Party::whereRaw('lower(name) = ?', [mb_strtolower($name)])->first())) {
                $ledger = $this->chart->party($party);
            }
            if (! $ledger) {
                $unknown[] = $name;

                continue;
            }
            $balances[$ledger->id] = ($balances[$ledger->id] ?? 0) + $amount;
        }

        return ['balances' => $balances, 'unknown' => $unknown];
    }
}
