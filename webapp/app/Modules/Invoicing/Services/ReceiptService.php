<?php

namespace App\Modules\Invoicing\Services;

use App\Models\Account;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\Receipt;
use App\Modules\Invoicing\Models\ReceiptAllocation;
use App\Modules\Parties\Models\Party;
use App\Modules\Settings\Services\DocumentNumbering;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Money received from a client (blueprint 10 §6.2, §7; ported from Clinic
 * Ledger's PaymentService). One receipt can settle many bills:
 *
 *   gross settled = money received + TDS the client deducted + what else
 *                   they kept (shortage, damage, late POD)
 *
 * applied to the bills named, or oldest first; the TDS and deductions are
 * spread over the bills in the same proportion (so each bill knows its TDS
 * for Form 26AS matching). Money left over is the client's advance. The
 * receipt is numbered at once; it is voided, never deleted, and a void puts
 * every bill back as it was.
 */
class ReceiptService
{
    public function __construct(
        private CompanyContext $context,
        private DocumentNumbering $numbering,
        private BalanceService $balances,
        private DocumentLinks $links,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param array{receipt_date: string, amount: float, tds_amount?: float, tds_section?: ?string, deduction_amount?: float, deduction_note?: ?string,
     *   mode: string, reference?: ?string, cheque_date?: ?string, bank_name?: ?string, bank_account_id?: ?int, narration: string,
     *   allocations?: array<int, float>|null} $data allocations: invoice id => gross to apply; empty = oldest first
     */
    public function record(Party $party, array $data, Account $by): Receipt
    {
        $amount    = round((float) $data['amount'], 3);
        $tds       = round((float) ($data['tds_amount'] ?? 0), 3);
        $deduction = round((float) ($data['deduction_amount'] ?? 0), 3);
        $gross     = round($amount + $tds + $deduction, 3);
        if ($gross <= 0) {
            throw ValidationException::withMessages(['amount' => __('Enter the amount received.')]);
        }
        if ($deduction > 0 && mb_strlen(trim((string) ($data['deduction_note'] ?? ''))) < 3) {
            throw ValidationException::withMessages(['deduction_note' => __('Say what the client deducted (shortage, damage…).')]);
        }

        return DB::transaction(function () use ($party, $data, $by, $amount, $tds, $deduction, $gross) {
            $open = Invoice::where('party_id', $party->id)->whereIn('status', Invoice::OPEN_STATUSES)->where('doc_type', '!=', 'credit_note')
                ->where('balance_due', '>', 0)->orderBy('due_date')->orderBy('issue_date')->orderBy('id')->lockForUpdate()->get();

            // What goes to which bill.
            $plan = [];
            $left = $gross;
            if (! empty($data['allocations'])) {
                foreach ($data['allocations'] as $invoiceId => $apply) {
                    $apply = round((float) $apply, 3);
                    if ($apply <= 0) {
                        continue;
                    }
                    $bill = $open->firstWhere('id', (int) $invoiceId) ?? throw ValidationException::withMessages(['allocations' => __('A bill chosen is not open for :name.', ['name' => $party->name])]);
                    if ($apply > (float) $bill->balance_due + 0.005) {
                        throw ValidationException::withMessages(['allocations' => __(':number has only :left left to pay.', ['number' => $bill->number, 'left' => number_format((float) $bill->balance_due, 2)])]);
                    }
                    $plan[$bill->id] = $apply;
                    $left -= $apply;
                }
                if ($left < -0.005) {
                    throw ValidationException::withMessages(['allocations' => __('More is applied to bills than the receipt settles.')]);
                }
            } else {
                foreach ($open as $bill) {
                    if ($left <= 0.005) {
                        break;
                    }
                    $apply = round(min($left, (float) $bill->balance_due), 3);
                    $plan[$bill->id] = $apply;
                    $left -= $apply;
                }
            }
            $applied = round($gross - max(0, $left), 3);
            if (($tds + $deduction) > $applied + 0.005) {
                throw ValidationException::withMessages(['tds_amount' => __('TDS and deductions are settled against bills; apply them to a bill.')]);
            }

            $receipt = Receipt::create([
                'number' => $this->numbering->assign('receipt', CarbonImmutable::parse($data['receipt_date'])->setTime(12, 0)),
                'party_id' => $party->id, 'receipt_date' => $data['receipt_date'], 'currency' => $this->context->company()->currency,
                'amount' => $amount, 'tds_amount' => $tds, 'tds_section' => $data['tds_section'] ?? null,
                'deduction_amount' => $deduction, 'deduction_note' => $data['deduction_note'] ?? null,
                'unapplied' => round(max(0, $left), 3), 'mode' => $data['mode'], 'reference' => $data['reference'] ?? null,
                'cheque_date' => $data['cheque_date'] ?? null, 'bank_name' => $data['bank_name'] ?? null, 'bank_account_id' => $data['bank_account_id'] ?? null,
                'narration' => $data['narration'], 'created_by' => $by->id,
            ]);

            // TDS and deductions in proportion to what each bill takes; the last bill takes the rounding.
            $ids = array_keys($plan);
            [$tdsLeft, $dedLeft] = [$tds, $deduction];
            foreach ($ids as $k => $invoiceId) {
                $share = $applied > 0 ? $plan[$invoiceId] / $applied : 0;
                $last  = $k === count($ids) - 1;
                $t = $last ? round($tdsLeft, 3) : round($tds * $share, 3);
                $d = $last ? round($dedLeft, 3) : round($deduction * $share, 3);
                [$tdsLeft, $dedLeft] = [$tdsLeft - $t, $dedLeft - $d];

                ReceiptAllocation::create(['receipt_id' => $receipt->id, 'invoice_id' => $invoiceId, 'amount' => round($plan[$invoiceId] - $t - $d, 3), 'tds' => $t, 'deduction' => $d]);

                $bill = $open->firstWhere('id', $invoiceId);
                $bill->forceFill(['amount_paid' => round((float) $bill->amount_paid + $plan[$invoiceId], 3)]);
                $bill->settle(CarbonImmutable::parse($data['receipt_date']));
                $bill->save();
            }

            $receipt->forceFill(['public_token' => $this->links->create('receipt', $receipt->id)])->save();
            $this->balances->recompute($party->id);
            $this->audit->record('receipt.recorded', "{$by->name} recorded {$receipt->number} from {$party->name}: {$receipt->currency} " . number_format($amount, 2)
                . ($tds > 0 ? ' + TDS ' . number_format($tds, 2) : '') . ($deduction > 0 ? ' + deductions ' . number_format($deduction, 2) : ''),
                'receipt', (string) $receipt->id, $receipt->number, severity: 'notable');

            return $receipt->fresh('allocations.invoice');
        });
    }

    public function void(Receipt $receipt, string $reason, Account $by): void
    {
        if ($receipt->isVoid()) {
            throw ValidationException::withMessages(['receipt' => __('This receipt is already void.')]);
        }
        if (mb_strlen(trim($reason)) < 5) {
            throw ValidationException::withMessages(['reason' => __('Write why it is voided (a cheque bounced…).')]);
        }

        DB::transaction(function () use ($receipt, $reason) {
            foreach ($receipt->allocations()->with('invoice')->get() as $allocation) {
                $bill = Invoice::whereKey($allocation->invoice_id)->lockForUpdate()->first();
                $bill->forceFill(['amount_paid' => round(max(0, (float) $bill->amount_paid - $allocation->total()), 3)]);
                $bill->settle();
                $bill->save();
            }
            $receipt->forceFill(['status' => 'void', 'voided_at' => now(), 'void_reason' => mb_substr($reason, 0, 255), 'unapplied' => 0])->save();
            $this->balances->recompute($receipt->party_id);
        });

        $this->audit->record('receipt.voided', "{$by->name} voided {$receipt->number}", 'receipt', (string) $receipt->id, $receipt->number, reason: $reason, severity: 'sensitive');
    }
}
