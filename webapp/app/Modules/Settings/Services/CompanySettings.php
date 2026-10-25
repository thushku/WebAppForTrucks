<?php

namespace App\Modules\Settings\Services;

use App\Platform\Audit\AuditRecorder;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Facades\DB;

/**
 * A company's rules, with defaults in code (blueprint 02 §3, 03).
 *
 * Read through this class only, so every screen and the phone API see the
 * same value. A rule nobody has changed is not stored at all; the default
 * applies.
 */
class CompanySettings
{
    /** key => [default, type, label] */
    public const DEFAULTS = [
        // Approvals (03 §6, decisions D5–D7, A4).
        'dual_approval_enabled'     => [true, 'bool', 'Payments without receipt need the second driver\'s approval'],
        'single_driver_approver'    => ['manager', 'string', 'Second approver when a trip has one driver'],
        'auto_approve_toll_below'   => [0, 'money', 'Approve tolls automatically below this amount (0 = never)'],
        // What the crew see and must do.
        'freight_visible_to_crew'   => [false, 'bool', 'Crew can see the freight amount'],
        'helpers_record_events'     => [false, 'bool', 'Helpers may record road events'],
        'driver_change_photo'       => [false, 'bool', 'Odometer photo required at every driver change'],
        'fuel_photos_required'      => [3, 'int', 'Photos required for a fuel entry (odometer, pump meter, receipt)'],
        'app_lock_minutes'          => [3, 'int', 'Phone app locks after this many minutes in the background'],
        'stop_prompt_minutes'       => [15, 'int', 'Ask "who is driving now?" after a stop of this many minutes'],
        'gps_silence_alert_minutes' => [25, 'int', 'Alert when a crew phone sends no GPS for this many minutes'],
        // Driving limits (country law sets the floor; the owner can be stricter).
        'max_continuous_driving_minutes' => [300, 'int', 'Alert after this much driving without a break (minutes)'],
        'max_daily_driving_minutes'      => [600, 'int', 'Alert after this much driving in a day (minutes)'],
        // Money.
        'bata_rule'                 => ['per_day', 'string', 'Bata (daily allowance) rule: per_day / per_trip / per_km / route'],
        'bata_driver_amount'        => [600, 'money', 'Bata per day for a driver'],
        'bata_helper_amount'        => [400, 'money', 'Bata per day for a helper'],
        'cost_allocation_method'    => ['freight', 'string', 'Share trip costs between consignments by: freight / weight / tonne_km'],
        'cash_limit_per_payee_day'  => [35000, 'money', 'Warn when cash to one payee in a day is above this (India goods-carriage limit)'],
        // Tax and invoices (blueprint 11).
        'invoice_round_total'       => [true, 'bool', 'Round invoice totals to the whole rupee / dirham (a separate round-off line)'],
        'tax_evidence_policy'       => ['block', 'string', 'Zero-rated lines without their papers: block the invoice, or warn and mark it "pending evidence"'],
        'tds_declaration_given'     => [false, 'bool', 'We give clients the declaration of 10 trucks or fewer (no TDS on our freight)'],
        // Reminders (M18, blueprint 10).
        'reminders_enabled'         => [false, 'bool', 'Email clients a reminder when a bill is overdue'],
        'reminder_days'             => ['3,15,30', 'string', 'Days after the due date to remind (comma-separated)'],
    ];

    /** Where a country's law or custom starts somewhere else. */
    public const COUNTRY_DEFAULTS = [
        'AE' => [
            'bata_driver_amount'       => 50,
            'bata_helper_amount'       => 30,
            'cash_limit_per_payee_day' => 0,
            'invoice_round_total'      => false,
        ],
    ];

    /** @var array<int, array<string, mixed>> per company id */
    private array $memo = [];

    public function __construct(private CompanyContext $context, private AuditRecorder $audit) {}

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? null;
    }

    /** @return array<string, mixed> every rule with its current value */
    public function all(): array
    {
        $values = $this->load();

        // Cast on the way out too: JSON gives 750 back for a stored 750.0.
        return collect(self::DEFAULTS)->mapWithKeys(fn ($spec, $key) => [$key => $this->cast($key, array_key_exists($key, $values) ? $values[$key] : $this->defaultFor($key))])->all();
    }

    /** The value a rule has until the company changes it. */
    public function defaultFor(string $key): mixed
    {
        $country = $this->context->company()->country;

        return self::COUNTRY_DEFAULTS[$country][$key] ?? self::DEFAULTS[$key][0] ?? null;
    }

    /** Save several rules at once (one transaction, one audit line). */
    public function save(array $changes): void
    {
        $companyId = $this->context->id();
        $before    = $this->all();
        $diff      = [];

        DB::transaction(function () use ($changes, $companyId, $before, &$diff) {
            foreach ($changes as $key => $value) {
                if (! isset(self::DEFAULTS[$key])) {
                    continue;
                }
                $value = $this->cast($key, $value);
                if ($before[$key] === $value) {
                    continue;
                }
                DB::table('company_settings')->updateOrInsert(
                    ['company_id' => $companyId, 'key' => $key],
                    ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()],
                );
                $diff[$key] = ['from' => $before[$key], 'to' => $value];
            }
        });

        unset($this->memo[$companyId]);

        if ($diff) {
            $this->audit->record('settings.rules_changed', 'Changed company rules: ' . implode(', ', array_keys($diff)), 'company', (string) $companyId, changes: $diff, severity: 'notable');
        }
    }

    public function cast(string $key, mixed $value): mixed
    {
        return match (self::DEFAULTS[$key][1] ?? 'string') {
            'bool'  => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'int'   => (int) $value,
            'money' => round((float) $value, 2),
            default => (string) $value,
        };
    }

    private function load(): array
    {
        $id = $this->context->id();

        return $this->memo[$id] ??= DB::table('company_settings')->where('company_id', $id)
            ->pluck('value', 'key')->map(fn ($v) => json_decode((string) $v, true))->all();
    }
}
