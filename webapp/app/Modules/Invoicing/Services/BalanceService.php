<?php

namespace App\Modules\Invoicing\Services;

use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\PartyBalance;
use App\Modules\Invoicing\Models\Receipt;
use Carbon\CarbonImmutable;

/**
 * A client's balance, worked out again from its documents in the same
 * transaction that changed them (ported from Clinic Ledger): outstanding and
 * overdue from open bills, advance from receipts not applied and credit
 * notes their invoice could not take. Lists read this row; nobody sums
 * thousands of bills on a page.
 */
class BalanceService
{
    public function recompute(int $partyId): PartyBalance
    {
        $today = CarbonImmutable::today();
        $open  = Invoice::where('party_id', $partyId)->whereIn('status', Invoice::OPEN_STATUSES)->where('doc_type', '!=', 'credit_note')
            ->get(['id', 'balance_due', 'due_date']);

        $advance = (float) Receipt::where('party_id', $partyId)->where('status', 'posted')->sum('unapplied')
            + (float) Invoice::where('party_id', $partyId)->where('doc_type', 'credit_note')->whereNotNull('finalized_at')->where('status', '!=', 'void')
                ->selectRaw('coalesce(sum(total - credit_applied), 0) as left_over')->value('left_over');

        return PartyBalance::updateOrCreate(['party_id' => $partyId], [
            'outstanding' => round((float) $open->sum('balance_due'), 3),
            'overdue'     => round((float) $open->filter(fn ($i) => $i->due_date->lt($today))->sum('balance_due'), 3),
            'advance'     => round($advance, 3),
            'open_bills'  => $open->where('balance_due', '>', 0)->count(),
            'oldest_due'  => $open->where('balance_due', '>', 0)->min('due_date'),
            'updated_at'  => now(),
        ]);
    }
}
