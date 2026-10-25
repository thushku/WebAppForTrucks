<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Settings\PlatformSettings;
use App\Platform\Tenancy\Company;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The console's first screens: companies, locked logins, platform settings.
 * Plans, prices, coupons, storage clean-up and database export/import come
 * with milestone M13 (blueprint 08).
 */
class HomeController extends Controller
{
    public function __construct(private PlatformSettings $settings, private AuditRecorder $audit) {}

    public function home(): View
    {
        return view('platform.home', [
            'counts' => [
                'companies' => Company::count(),
                'trial'     => Company::where('status', 'trial')->count(),
                'active'    => Company::where('status', 'active')->count(),
                'accounts'  => Account::count(),
            ],
            'companies' => Company::with('owner')->latest()->limit(50)->get(),
            'locked'    => Account::whereNotNull('locked_hard_at')
                ->orWhere('locked_until', '>', now())->orderByDesc('updated_at')->limit(50)->get(),
            'failures'  => DB::table('login_attempts')->where('succeeded', false)
                ->where('attempted_at', '>', now()->subDay())->count(),
        ]);
    }

    /** Super-admin intervention: open a locked login (website guideline §4). */
    public function unlock(Account $account): RedirectResponse
    {
        $account->unlock();
        $this->audit->record('security.unlocked', "Console unlocked {$account->name}'s login", 'account', (string) $account->id, $account->name, severity: 'sensitive');

        return back()->with('status', __(':name can sign in again.', ['name' => $account->name]));
    }

    public function settings(): View
    {
        return view('platform.settings', [
            'values'    => collect(array_keys(PlatformSettings::DEFAULTS))->mapWithKeys(fn ($k) => [$k => $this->settings->get($k)]),
            'languages' => Locales::NATIVE,
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'captcha_mode'        => ['required', Rule::in(['always', 'after_failures'])],
            'trial_days'          => ['required', 'integer', 'min:1', 'max:365'],
            'brand_name'          => ['nullable', 'string', 'max:60'],
            'support_email'       => ['nullable', 'email:filter', 'max:255'],
            'support_phone'       => ['nullable', 'string', 'max:20', 'regex:/^[+0-9 ()-]+$/'],
            'support_whatsapp'    => ['nullable', 'string', 'max:20', 'regex:/^[+0-9 ()-]+$/'],
            'enabled_languages'   => ['required', 'array', 'min:1'],
            'enabled_languages.*' => [Rule::in(array_keys(Locales::NATIVE))],
        ]);

        // English is always available: it is the language every text exists in.
        $data['enabled_languages'] = array_values(array_unique(array_merge(['en'], $data['enabled_languages'])));

        DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                $this->settings->set($key, $value === '' ? null : $value);
            }
        });

        $this->audit->record('platform.settings_saved', 'Console settings changed', changes: $data, severity: 'notable');

        return back()->with('status', __('Settings saved.'));
    }
}
