<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Platform\Notifications\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The phone's inbox (M20; api-v1 §11): the same notices the push brought,
 * for the company in X-Company, newest first, 30 at a time; and marking
 * them read (which also clears the app's badge).
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['before' => ['nullable', 'integer', 'min:1'], 'limit' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $limit = (int) ($data['limit'] ?? 30);
        $items = UserNotification::for($request->user())->when($data['before'] ?? null, fn ($q, $before) => $q->where('id', '<', $before))
            ->orderByDesc('id')->limit($limit + 1)->get();

        return response()->json([
            'unread' => UserNotification::for($request->user())->whereNull('read_at')->count(),
            'items' => $items->take($limit)->map->toApi()->values(),
            'more' => $items->count() > $limit,
        ]);
    }

    /** My notification settings (M26): `push` lists the groups that reach the phone; quiet hours for all but high notices. */
    public function settings(\App\Platform\Notifications\NotificationSettings $settings): JsonResponse
    {
        $mine = $settings->of(app(\App\Platform\Tenancy\CompanyContext::class)->member());

        return response()->json([
            'groups' => collect(\App\Platform\Notifications\NotificationSettings::GROUPS)->map(fn ($g, $key) => ['key' => $key, 'name' => __($g[0]), 'about' => __($g[1]), 'push' => ! in_array($key, $mine['muted'], true)])->values(),
            'quiet_from' => $mine['quiet_from'], 'quiet_to' => $mine['quiet_to'],
        ]);
    }

    public function saveSettings(Request $request, \App\Platform\Notifications\NotificationSettings $settings): JsonResponse
    {
        $data = $request->validate(\App\Http\Controllers\NotificationController::settingsRules());
        $groups = array_keys(\App\Platform\Notifications\NotificationSettings::GROUPS);
        $settings->save(app(\App\Platform\Tenancy\CompanyContext::class)->member(), array_values(array_diff($groups, $data['push'] ?? [])), $data['quiet_from'] ?? null, $data['quiet_to'] ?? null);

        return $this->settings($settings);
    }

    public function read(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => ['required_without:all', 'array', 'max:200'], 'ids.*' => ['integer'], 'all' => ['nullable', 'boolean']]);
        UserNotification::for($request->user())->whereNull('read_at')
            ->when(! ($data['all'] ?? false), fn ($q) => $q->whereIn('id', $data['ids'] ?? []))
            ->update(['read_at' => now()]);

        return response()->json(['unread' => UserNotification::for($request->user())->whereNull('read_at')->count()]);
    }
}
