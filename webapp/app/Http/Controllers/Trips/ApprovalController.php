<?php

namespace App\Http\Controllers\Trips;

use App\Http\Controllers\Controller;
use App\Modules\Road\Models\RoadEvent;
use App\Modules\Road\Services\Approvals;
use App\Modules\Road\Models\EventPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The approvals inbox (blueprint 04 §3.4): the crew's entries waiting for
 * the office, with their photos and flags. Approve, ask (query) or reject
 * one by one — or tick the rows without flags and approve them together.
 * The same decisions are on each trip's timeline.
 */
class ApprovalController extends Controller
{
    public const TABS = ['waiting_check' => 'Waiting for check', 'waiting_2nd' => 'Waiting for 2nd approval', 'queried' => 'Query open', 'disputed' => 'Disputed'];

    public function __construct(private Approvals $approvals) {}

    public function index(Request $request): View
    {
        $f = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(self::TABS))],
            'type'   => ['nullable', 'string', 'max:20'],
            'q'      => ['nullable', 'string', 'max:40'],
        ]);
        $status = $f['status'] ?? 'waiting_check';

        $events = $this->approvals->inbox($status)
            ->when($f['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when(filled($f['q'] ?? null), fn ($q) => $q->whereHas('trip', fn ($t) => $t->where('trip_number', 'like', '%' . addcslashes(strtoupper($f['q']), '%_\\') . '%')))
            ->paginate(100)->withQueryString();

        return view('approvals.index', [
            'events'  => $events,
            'status'  => $status,
            'filters' => $f,
            'counts'  => $this->approvals->counts(),
            'canMoney' => $request->user()->can('trip.freight_view') || $request->user()->can('event.verify'),
        ]);
    }

    public function decide(Request $request, RoadEvent $event): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'query', 'reject'])],
            'note'     => ['nullable', 'string', 'max:255'],
        ]);
        $this->approvals->decide($request->user(), $event, $data['decision'], $data['note'] ?? null);

        return back()->with('status', match ($data['decision']) {
            'approve' => __('Approved.'),
            'query'   => __('Question sent to :name.', ['name' => $event->person()->value('name')]),
            'reject'  => __('Rejected.'),
        });
    }

    public function approveMany(Request $request): RedirectResponse
    {
        $data = $request->validate(['events' => ['required', 'array', 'max:100'], 'events.*' => ['integer']], ['events.required' => __('Tick the entries to approve.')]);
        $done = $this->approvals->approveMany($request->user(), RoadEvent::with('trip.crew')->whereIn('id', $data['events'])->get());
        $left = count($data['events']) - $done;

        return back()->with('status', trans_choice(':count entry approved.|:count entries approved.', $done)
            . ($left ? ' ' . trans_choice(':count with flags or not waiting was left for a closer look.|:count with flags or not waiting were left for a closer look.', $left) : ''));
    }

    /** An entry's photo, for the inbox. */
    public function photo(RoadEvent $event, EventPhoto $photo): Response
    {
        abort_unless($photo->road_event_id === $event->id, 404);

        return response(Storage::disk('local')->get($photo->path), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=3600']);
    }
}
