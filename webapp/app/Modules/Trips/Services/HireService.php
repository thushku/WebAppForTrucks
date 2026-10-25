<?php

namespace App\Modules\Trips\Services;

use App\Modules\Parties\Models\Party;
use App\Modules\Settings\Services\DocumentNumbering;
use App\Modules\Trips\Models\BrokerCommission;
use App\Modules\Trips\Models\HireChallan;
use App\Modules\Trips\Models\HireChallanLine;
use App\Modules\Trips\Models\Trip;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hired trucks: the lorry hire challan and its money (blueprint 09 §4,
 * 10 §6.1, 12 §5).
 *
 * TDS (India, section 393(1), old 194C — see Tax\Services\Withholding) is
 * suggested, never forced: nil when the supplier
 * has given this year's declaration that they own ten trucks or fewer (a
 * party document "≤10-truck declaration" valid today); otherwise 1 % for an
 * individual or HUF (PAN holder type P or H) and 2 % for others. The
 * thresholds (₹30,000 a payment, ₹1,00,000 a year) and the final rate are
 * the accountant's call — this needs a CA check before go-live (verify).
 */
class HireService
{
    public function __construct(private DocumentNumbering $numbering, private CompanyContext $context, private AuditRecorder $audit) {}

    /** @return array{section: ?string, rate: float, nil_reason: ?string, label: ?string} */
    public function tdsFor(Party $supplier, ?\Carbon\CarbonInterface $on = null): array
    {
        $tds = app(\App\Modules\Tax\Services\Withholding::class)->onPayment($supplier, $on);

        return ['section' => $tds['section'], 'rate' => $tds['rate'], 'nil_reason' => $tds['nil_reason'], 'label' => $tds['label']];
    }

    /** @param array<string, mixed> $data */
    public function save(Trip $trip, ?HireChallan $challan, array $data): HireChallan
    {
        if ($trip->truck_source !== 'hired') {
            throw ValidationException::withMessages(['challan' => __('Only a trip with a hired truck has a hire challan.')]);
        }
        if ($challan && $challan->status !== 'draft') {
            throw ValidationException::withMessages(['challan' => __('An issued challan is not changed; add a line (detention, deduction…) instead.')]);
        }

        return DB::transaction(function () use ($trip, $challan, $data) {
            $data['hire_amount'] = $data['hire_basis'] === 'tonne'
                ? round((float) $data['hire_rate'] * (float) $data['hire_weight_mt'], 2)
                : round((float) $data['hire_amount'], 2);
            $data['tds_amount'] = round($data['hire_amount'] * (float) ($data['tds_rate'] ?? 0) / 100, 2);

            $challan ??= new HireChallan(['trip_id' => $trip->id, 'created_by' => auth()->id(), 'status' => 'draft']);
            $challan->fill($data + ['supplier_party_id' => $trip->supplier_party_id])->save();

            // The commission on this challan, kept in step with the broker register.
            BrokerCommission::where('hire_challan_id', $challan->id)->delete();
            if ($challan->broker_party_id && (float) $challan->commission_amount > 0) {
                BrokerCommission::create([
                    'broker_party_id' => $challan->broker_party_id, 'trip_id' => $trip->id, 'hire_challan_id' => $challan->id,
                    'direction' => $challan->commission_mode === 'deducted' ? 'deducted' : 'payable', 'basis' => 'fixed', 'amount' => $challan->commission_amount,
                ]);
            }

            return $challan;
        });
    }

    public function issue(HireChallan $challan): void
    {
        if ($challan->status !== 'draft') {
            throw ValidationException::withMessages(['challan' => __('This challan is already issued.')]);
        }

        DB::transaction(function () use ($challan) {
            $challan->forceFill([
                'number'    => $this->numbering->assign('hire_challan', $challan->challan_date->copy()->setTime(12, 0)),
                'status'    => 'issued',
                'issued_at' => now(),
            ])->save();
        });
        $this->audit->record('hire_challan.issued', "Issued hire challan {$challan->number}", 'hire_challan', (string) $challan->id, $challan->number);
    }

    /** Money on an issued challan. When the balance reaches zero after a payment, the challan is settled. */
    public function addLine(HireChallan $challan, array $line): HireChallanLine
    {
        if ($challan->status !== 'issued') {
            throw ValidationException::withMessages(['challan' => $challan->status === 'draft' ? __('Issue the challan first.') : __('This challan is :status.', ['status' => mb_strtolower(__(HireChallan::STATUSES[$challan->status]))])]);
        }
        $balance = $challan->balance();
        if (in_array($line['kind'], ['advance', 'payment', 'fuel', 'deduction'], true) && (float) $line['amount'] > $balance + 0.004) {
            throw ValidationException::withMessages(['amount' => __('That is more than the balance of :balance.', ['balance' => \App\Support\Money::company($balance)])]);
        }

        return DB::transaction(function () use ($challan, $line) {
            $row = HireChallanLine::create($line + ['hire_challan_id' => $challan->id, 'created_by' => auth()->id()]);
            $challan->unsetRelation('lines');
            if ($line['kind'] === 'payment' && abs($challan->balance()) < 0.005) {
                $challan->forceFill(['status' => 'settled', 'settled_at' => now()])->save();
            }

            return $row;
        });
    }
}
