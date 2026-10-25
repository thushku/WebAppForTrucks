<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Services\CompanySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Trip rules: dual approval on or off (D7), who gives the second approval on
 * a one-driver trip (A4), photos, driving limits, bata. Grouped on screen as
 * the owner thinks about them.
 */
class RulesController extends Controller
{
    /** Group title => keys, in screen order. */
    public const GROUPS = [
        'Approvals'          => ['dual_approval_enabled', 'single_driver_approver', 'auto_approve_toll_below'],
        'Crew app'           => ['freight_visible_to_crew', 'helpers_record_events', 'fuel_photos_required', 'driver_change_photo', 'app_lock_minutes', 'stop_prompt_minutes', 'gps_silence_alert_minutes'],
        'Driving limits'     => ['max_continuous_driving_minutes', 'max_daily_driving_minutes'],
        'Bata and money'     => ['bata_rule', 'bata_driver_amount', 'bata_helper_amount', 'cost_allocation_method', 'cash_limit_per_payee_day'],
    ];

    public const CHOICES = [
        'single_driver_approver' => ['manager' => 'A manager approves instead', 'none' => 'No second approval needed'],
        'bata_rule'              => ['per_day' => 'Per day on the road', 'per_trip' => 'Per trip', 'per_km' => 'Per km', 'route' => 'Fixed amount per route'],
        'cost_allocation_method' => ['freight' => 'In proportion to freight', 'weight' => 'By weight', 'tonne_km' => 'By tonne-km'],
    ];

    public function __construct(private CompanySettings $settings) {}

    public function edit(): View
    {
        return view('settings.rules', [
            'values'   => $this->settings->all(),
            'defaults' => CompanySettings::DEFAULTS,
            'groups'   => self::GROUPS,
            'choices'  => self::CHOICES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (CompanySettings::DEFAULTS as $key => [, $type]) {
            $rules[$key] = match ($type) {
                'bool'  => ['nullable', 'boolean'],
                'int'   => ['required', 'integer', 'min:0', 'max:100000'],
                'money' => ['required', 'numeric', 'min:0', 'max:100000000'],
                default => ['required', Rule::in(array_keys(self::CHOICES[$key] ?? []))],
            };
        }
        $rules['fuel_photos_required'] = ['required', 'integer', 'min:0', 'max:3'];
        $rules['app_lock_minutes']     = ['required', 'integer', 'min:1', 'max:60'];

        $data = $request->validate($rules);

        // Unticked boxes are not sent at all.
        foreach (CompanySettings::DEFAULTS as $key => [, $type]) {
            if ($type === 'bool') {
                $data[$key] = $request->boolean($key);
            }
        }

        $this->settings->save($data);

        return back()->with('status', __('Rules saved. Phones pick them up at their next sync.'));
    }
}
