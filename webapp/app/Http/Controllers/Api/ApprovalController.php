<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Road\Models\EventPhoto;
use App\Modules\Road\Models\RoadEvent;
use App\Modules\Road\Services\Approvals;
use App\Modules\Road\Services\EventChecks;
use App\Modules\Road\Services\EventTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * The owner app's approvals list (blueprint 05 §3): the same decisions as the
 * web inbox — approve, ask, reject — from the phone, with the photos.
 */
class ApprovalController extends Controller
{
    public function __construct(private Approvals $approvals) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(RoadEvent::OPEN)]]);

        $events = $this->approvals->inbox($data['status'] ?? null)->limit(200)->get();

        return response()->json([
            'counts' => $this->approvals->counts(),
            'events' => $events->map(fn (RoadEvent $e) => [
                'uuid' => $e->uuid, 'trip' => ['uuid' => $e->trip->uuid, 'number' => $e->trip->trip_number],
                'by' => $e->person->name, 'type' => $e->type, 'label' => EventTypes::label($e->type),
                'amount' => $e->amount !== null ? (float) $e->amount : null, 'payment_mode' => $e->payment_mode, 'odometer' => $e->odometer,
                'data' => $e->data, 'note' => $e->note, 'at' => $e->trusted_at->toIso8601String(), 'lat' => $e->lat, 'lng' => $e->lng,
                'status' => $e->status, 'question' => $e->status === 'queried' ? $e->decision_note : null, 'second_reject_reason' => $e->second_reject_reason,
                'flags' => collect($e->flags ?? [])->map(fn ($f) => ['code' => $f, 'label' => __(EventChecks::LABELS[$f] ?? $f)])->values(),
                'photos' => $e->photos->map(fn ($p) => ['slot' => $p->slot, 'url' => route('api.approvals.photo', [$e->uuid, $p->id]), 'same_as_before' => $p->duplicate_of !== null])->values(),
                'photos_expected' => $e->photos_expected,
                'actions' => $this->approvals->actions($e),
            ])->values(),
        ]);
    }

    public function decide(Request $request, string $uuid): JsonResponse
    {
        $data  = $request->validate(['decision' => ['required', Rule::in(['approve', 'query', 'reject'])], 'note' => ['nullable', 'string', 'max:255']]);
        $event = RoadEvent::with('trip.crew')->where('uuid', $uuid)->firstOrFail();

        $this->approvals->decide($request->user(), $event, $data['decision'], $data['note'] ?? null);

        return response()->json(['uuid' => $event->uuid, 'status' => $event->status]);
    }

    public function photo(string $uuid, EventPhoto $photo): Response
    {
        $event = RoadEvent::where('uuid', $uuid)->firstOrFail();
        abort_unless($photo->road_event_id === $event->id, 404);

        return response(Storage::disk('local')->get($photo->path), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=3600']);
    }
}
