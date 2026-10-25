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

    public function read(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => ['required_without:all', 'array', 'max:200'], 'ids.*' => ['integer'], 'all' => ['nullable', 'boolean']]);
        UserNotification::for($request->user())->whereNull('read_at')
            ->when(! ($data['all'] ?? false), fn ($q) => $q->whereIn('id', $data['ids'] ?? []))
            ->update(['read_at' => now()]);

        return response()->json(['unread' => UserNotification::for($request->user())->whereNull('read_at')->count()]);
    }
}
