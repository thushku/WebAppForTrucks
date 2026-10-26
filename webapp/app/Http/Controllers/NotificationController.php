<?php

namespace App\Http\Controllers;

use App\Platform\Notifications\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The bell (M20): what this person was told in this company, newest first; opening one marks it read. */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => UserNotification::for($request->user())->inboxOrder()->simplePaginate(30),
        ]);
    }

    /** My notifications (M26): which groups reach my phone, and quiet hours. */
    public function settings(\App\Platform\Notifications\NotificationSettings $settings): View
    {
        return view('notifications.settings', ['settings' => $settings->of(app(\App\Platform\Tenancy\CompanyContext::class)->member())]);
    }

    public function saveSettings(Request $request, \App\Platform\Notifications\NotificationSettings $settings): RedirectResponse
    {
        $data = $request->validate(self::settingsRules());
        $groups = array_keys(\App\Platform\Notifications\NotificationSettings::GROUPS);
        $settings->save(app(\App\Platform\Tenancy\CompanyContext::class)->member(), array_values(array_diff($groups, $data['push'] ?? [])), $data['quiet_from'] ?? null, $data['quiet_to'] ?? null);

        return back()->with('status', __('Saved. The inbox keeps every notice; these choices are only for your phone.'));
    }

    /** The settings form's rules, shared with the phone API. */
    public static function settingsRules(): array
    {
        return [
            'push' => ['nullable', 'array'], 'push.*' => [\Illuminate\Validation\Rule::in(array_keys(\App\Platform\Notifications\NotificationSettings::GROUPS))],
            'quiet_from' => ['nullable', 'date_format:H:i', 'required_with:quiet_to'], 'quiet_to' => ['nullable', 'date_format:H:i', 'required_with:quiet_from', 'different:quiet_from'],
        ];
    }

    public function open(Request $request, int $notification): RedirectResponse
    {
        $note = UserNotification::for($request->user())->findOrFail($notification);
        if (! $note->read_at) {
            $note->forceFill(['read_at' => now()])->saveQuietly();
        }

        // Only a path on this site (no open redirect).
        return $note->url && str_starts_with($note->url, '/') && ! str_starts_with($note->url, '//') ? redirect($note->url) : redirect()->route('notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        UserNotification::for($request->user())->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', __('All marked as read.'));
    }
}
