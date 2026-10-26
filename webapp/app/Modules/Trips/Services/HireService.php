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
use Illuminate\Validation\Rule;
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

    /** A new challan's fields from the trip: its supplier's vehicle and driver, the loads' weight, the suggested TDS (web and owner app). */
    public function draftFor(Trip $trip): array
    {
        $trip->loadMissing(['supplier', 'supplierVehicle', 'consignments']);
        $tds = $this->tdsFor($trip->supplier);

        return [
            'challan_date'   => now($this->context->company()->timezone)->toDateString(),
            'vehicle_reg'    => $trip->supplierVehicle?->reg_no,
            'driver_name'    => $trip->supplierVehicle?->driver_name,
            'driver_mobile'  => $trip->supplierVehicle?->driver_mobile,
            'driver_licence' => $trip->supplierVehicle?->driver_licence,
            'hire_weight_mt' => $trip->consignments->where('status', '!=', 'cancelled')->sum(fn ($c) => $c->weight()) ?: null,
            'tds_section'    => $tds['section'],
            'tds_rate'       => $tds['rate'],
            'tds_nil_reason' => $tds['nil_reason'],
            'balance_days_after_pod' => $trip->supplier->payment_days,
        ];
    }

    /** The challan's fields (M42: shared by the web and the owner app). */
    public function rules(): array
    {
        $companyId = $this->context->id();

        return [
            'challan_date'   => ['required', 'date_format:Y-m-d'],
            'vehicle_reg'    => ['required', 'string', 'max:20'],
            'driver_name'    => ['nullable', 'string', 'max:120'],
            'driver_mobile'  => ['nullable', 'string', 'max:20'],
            'driver_licence' => ['nullable', 'string', 'max:30'],
            'hire_basis'     => ['required', Rule::in(['fixed', 'tonne'])],
            'hire_amount'    => ['nullable', 'required_if:hire_basis,fixed', 'numeric', 'min:1', 'max:99999999'],
            'hire_rate'      => ['nullable', 'required_if:hire_basis,tonne', 'numeric', 'min:1', 'max:9999999'],
            'hire_weight_mt' => ['nullable', 'required_if:hire_basis,tonne', 'numeric', 'min:0.001', 'max:500'],
            'broker_party_id' => ['nullable', 'integer', Rule::exists('party_roles', 'party_id')->where('company_id', $companyId)->where('role', 'broker')],
            'commission_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'commission_mode'   => ['required', Rule::in(['deducted', 'paid'])],
            'office_charge'  => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'tds_section'    => ['nullable', 'string', 'max:10'],
            'tds_rate'       => ['nullable', 'numeric', 'min:0', 'max:30'],
            'tds_nil_reason' => ['nullable', 'string', 'max:150'],
            'balance_days_after_pod' => ['nullable', 'integer', 'min:0', 'max:180'],
            'terms'          => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** The validated fields as stored: money as numbers, the registration in capitals. */
    public function prepare(array $data): array
    {
        foreach (['commission_amount', 'office_charge', 'tds_rate'] as $money) {
            $data[$money] = (float) ($data[$money] ?? 0);
        }
        $data['vehicle_reg'] = strtoupper($data['vehicle_reg']);

        return $data;
    }

    /** Money on an issued challan: detention, an advance, fuel given, a deduction, the balance paid. */
    public function lineRules(): array
    {
        return [
            'kind'      => ['required', Rule::in(array_keys(HireChallan::LINE_KINDS))],
            'amount'    => ['required', 'numeric', 'min:1', 'max:99999999'],
            'line_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:' . now($this->context->company()->timezone ?: 'UTC')->toDateString()],
            'mode'      => ['nullable', 'required_if:kind,advance,payment', Rule::in(['cash', 'upi', 'bank'])],
            'paid_to'   => ['nullable', Rule::in(['owner', 'broker', 'driver'])],
            'reference' => ['nullable', 'string', 'max:60'],
            'note'      => ['nullable', 'required_if:kind,deduction', 'string', 'max:255'],
        ];
    }

    public function lineMessages(): array
    {
        return ['note.required_if' => __('Say what the deduction is for.'), 'mode.required_if' => __('How was it paid?')];
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
                'number'    => $this->numbering->assignInBranch('hire_challan', $challan->challan_date->copy()->setTime(12, 0), \App\Modules\Trips\Models\Trip::whereKey($challan->trip_id)->value('branch_id')),
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
