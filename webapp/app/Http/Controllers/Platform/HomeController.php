<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Settings\PlatformSettings;
use App\Platform\Tenancy\Company;
use App\Platform\Website\ContactMessage;
use App\Platform\Website\SiteText;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The console's first screens: the overview (companies, locked logins,
 * messages from the website's Contact page) and the platform settings.
 * Plans, companies' subscriptions and the system pages have their own
 * controllers (M13).
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
            'messages'  => ContactMessage::whereNull('handled_at')->orderByDesc('id')->limit(50)->get(),
        ]);
    }

    /** A website message answered. */
    public function handled(Request $request, ContactMessage $message): RedirectResponse
    {
        $message->forceFill(['handled_at' => now(), 'handled_by_admin' => $request->user('platform')->id])->save();

        return back()->with('status', __('Marked as answered.'));
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
            'texts'     => app(SiteText::class),
        ]);
    }

    /** The website's texts and the app download link (decision D20: set here, not in code). */
    public function saveWebsite(Request $request): RedirectResponse
    {
        $rules = ['crew_app_url' => ['nullable', 'url:https', 'max:255']];
        foreach (array_keys(SiteText::PAGES) as $page) {
            $rules['site_' . $page] = ['nullable', 'string', 'max:30000'];
        }
        $data = $request->validate($rules);

        $texts = app(SiteText::class);
        DB::transaction(function () use ($data, $request, $texts) {
            foreach ($data as $key => $value) {
                // A page left as shipped (or reset to it) keeps following the shipped text.
                $page = str_starts_with($key, 'site_') ? substr($key, 5) : null;
                $shipped = $page && ($request->boolean('reset_' . $page) || $texts->isShipped($page, (string) $value));
                $this->settings->set($key, $shipped || blank($value) ? null : trim(strip_tags((string) $value)));
            }
        });
        $this->audit->record('platform.website_saved', 'Website texts or app link changed', severity: 'notable');

        return back()->with('status', __('Website saved.'));
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
