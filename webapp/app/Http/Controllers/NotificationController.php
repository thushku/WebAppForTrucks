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
            'notifications' => UserNotification::for($request->user())->orderByDesc('updated_at')->orderByDesc('id')->simplePaginate(30),
        ]);
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
