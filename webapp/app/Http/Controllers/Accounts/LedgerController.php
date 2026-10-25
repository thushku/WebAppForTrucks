<?php

namespace App\Http\Controllers\Accounts;

use App\Modules\Accounts\Models\LedgerAccount;
use App\Modules\Accounts\Models\VoucherLine;
use App\Modules\Accounts\Services\Statements;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The ledgers (blueprint 09): every client, truck supplier, vendor and crew
 * member with their balance in plain words, and each one's running
 * statement — the same book the accountant reads, seen per person or party.
 */
class LedgerController extends AccountsController
{
    /** tab => label */
    public const TABS = [
        'clients'   => 'Clients',
        'suppliers' => 'Suppliers and vendors',
        'crew'      => 'Crew',
    ];

    public function index(Request $request, Statements $statements): View
    {
        $f = $request->validate(['tab' => ['nullable', Rule::in(array_keys(self::TABS))], 'q' => ['nullable', 'string', 'max:60']]);
        $tab = $f['tab'] ?? 'clients';
        $all = $statements->balances($this->today())->filter(fn ($l) => in_array($l->kind, ['party', 'person'], true));
        $counts = [
            'clients'   => $all->where('group_code', 'sundry_debtors')->count(),
            'suppliers' => $all->where('kind', 'party')->where('group_code', '!=', 'sundry_debtors')->count(),
            'crew'      => $all->where('kind', 'person')->count(),
        ];
        $rows = match ($tab) {
            'clients'   => $all->where('group_code', 'sundry_debtors'),
            'suppliers' => $all->where('kind', 'party')->where('group_code', '!=', 'sundry_debtors'),
            default     => $all->where('kind', 'person'),
        };
        if (filled($f['q'] ?? null)) {
            $rows = $rows->filter(fn ($l) => mb_stripos($l->name, $f['q']) !== false);
        }

        return view('accounts.ledgers.index', [
            'tab' => $tab, 'filters' => $f, 'counts' => $counts, 'rows' => $rows->sortByDesc(fn ($l) => abs($l->balance))->values(),
            'receivable' => round($all->where('group_code', 'sundry_debtors')->where('balance', '>', 0)->sum('balance'), 2),
            'payable' => round(-$all->where('kind', 'party')->where('balance', '<', 0)->sum('balance'), 2),
            'withCrew' => round($all->where('kind', 'person')->where('balance', '>', 0)->sum('balance'), 2),
        ]);
    }

    public function show(Request $request, LedgerAccount $ledger, Statements $statements): View
    {
        abort_unless($request->user()->canAny(['ledger.view', 'accounts.view']), 403);
        [$from, $to, $fy] = $this->period($request);
        $bucket = $request->validate(['bucket' => ['nullable', Rule::in(array_keys(VoucherLine::BUCKETS))]])['bucket'] ?? null;
        $ledger->load(['group', 'party', 'person']);

        return view('accounts.ledgers.show', [
            'ledger' => $ledger, 'from' => $from, 'to' => $to, 'fy' => $fy, 'bucket' => $bucket,
            'statement' => $statements->ledger($ledger, $from, $to, $bucket),
            'buckets' => $ledger->kind === 'person' ? $statements->crewBuckets($ledger) : null,
        ]);
    }

    public function export(Request $request, LedgerAccount $ledger, Statements $statements): StreamedResponse
    {
        abort_unless($request->user()->canAny(['ledger.view', 'accounts.view']), 403);
        [$from, $to] = $this->period($request);
        $s = $statements->ledger($ledger, $from, $to, $request->query('bucket') ?: null);
        $rows = [[$from->format('d-m-Y'), '', '', __('Opening balance'), '', '', $s['opening']]];
        foreach ($s['rows'] as $r) {
            $rows[] = [CarbonImmutable::parse($r->voucher_date)->format('d-m-Y'), $r->number, $r->reference, $r->narration . ($r->note ? ' · ' . $r->note : ''), (float) $r->debit ?: '', (float) $r->credit ?: '', $r->balance];
        }
        $rows[] = [$to->format('d-m-Y'), '', '', __('Closing balance'), $s['debit'], $s['credit'], $s['closing']];

        return $this->csv('Ledger-' . $ledger->name . '-' . $from->format('Ymd') . '-' . $to->format('Ymd'),
            [__('Date'), __('Voucher'), __('Reference'), __('Particulars'), __('Debit'), __('Credit'), __('Balance (Dr +)')], $rows);
    }

    /**
     * A balance in plain words (09 §0.1): "UltraTech will give you ₹36,000",
     * "You will give Raju Transport ₹7,245", "Ravi Kumar will give you ₹975";
     * any other ledger in Dr / Cr.
     */
    public static function words(string $kind, string $name, float $balance): string
    {
        $amount = Money::company(abs($balance));
        if (abs($balance) < 0.005) {
            return __('Settled');
        }

        if (! in_array($kind, ['party', 'person'], true)) {
            return $amount . ' ' . ($balance > 0 ? __('Dr') : __('Cr'));
        }

        return $balance > 0 ? __(':name will give you :amount', ['name' => $name, 'amount' => $amount]) : __('You will give :name :amount', ['name' => $name, 'amount' => $amount]);
    }
}
