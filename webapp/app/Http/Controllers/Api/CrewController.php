<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Models\LedgerAccount;
use App\Modules\Accounts\Services\Statements;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentFile;
use App\Modules\Documents\Services\Compliance;
use App\Modules\People\Models\Person;
use App\Modules\Road\Models\EventPhoto;
use App\Modules\Road\Models\RoadEvent;
use App\Modules\Road\Services\CrewAccess;
use App\Modules\Road\Services\CrewCommands;
use App\Modules\Road\Services\GpsService;
use App\Modules\Road\Services\PhotoService;
use App\Modules\Road\Services\RoadEventService;
use App\Modules\Road\Services\SyncService;
use App\Modules\Trips\Models\Trip;
use App\Platform\Devices\ApiCommand;
use App\Platform\Devices\DeviceTokens;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The crew app's API (blueprint 01 §4): one sync to read, and pushes for
 * entries, photos, GPS and commands. Every push is safe to send twice.
 *
 * Answers for a batch are per item: one bad entry never blocks the rest of
 * a day's queue.
 */
class CrewController extends Controller
{
    public function __construct(private CrewAccess $access, private DeviceTokens $devices) {}

    public function sync(Request $request, SyncService $sync): JsonResponse
    {
        $person = $this->person();
        $since  = $request->filled('since') ? CarbonImmutable::parse((string) $request->query('since'))->utc() : null;
        $this->devices->seen($request, ['last_sync_at' => now()]);

        return response()->json($sync->pull($person, $since));
    }

    /** A batch of entries from the phone's queue, oldest first. */
    public function events(Request $request, RoadEventService $events): JsonResponse
    {
        $person = $this->person();
        if (! $this->access->canRecord()) {
            return $this->refused(__('You are not allowed to record entries.'), 403);
        }
        $data = $request->validate([
            'sent_uptime_ms' => ['nullable', 'integer', 'min:0'],
            'events'         => ['required', 'array', 'min:1', 'max:100'],
            'events.*'       => ['array'],
        ]);

        $batch   = ['sent_uptime_ms' => $data['sent_uptime_ms'] ?? null];
        $results = collect($data['events'])->map(fn ($e) => $events->push($person, $e, $batch))->values();

        return response()->json(['results' => $results, 'server_time' => now()->toIso8601String()]);
    }

    /** Change one's own entry before it is checked (the old values are kept). */
    public function edit(Request $request, string $uuid, RoadEventService $events): JsonResponse
    {
        $person = $this->person();
        $event  = $this->event($uuid);
        $event  = $events->edit($person, $event, $request->only(['amount', 'payment_mode', 'odometer', 'note', 'data']));

        return response()->json(['uuid' => $event->uuid, 'status' => $event->status, 'flags' => $event->flags ?? []]);
    }

    public function cancel(Request $request, string $uuid, RoadEventService $events): JsonResponse
    {
        $person = $this->person();
        $data   = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);
        $event  = $this->event($uuid);
        if ($event->status !== 'cancelled') {
            $events->cancel($person, $event, $data['reason']);
        }

        return response()->json(['uuid' => $event->uuid, 'status' => 'cancelled']);
    }

    /** One photo of an entry. The entry may have been sent hours before. */
    public function photo(Request $request, string $uuid, PhotoService $photos): JsonResponse
    {
        $person = $this->person();
        $data   = $request->validate([
            'slot'     => ['required', 'string', 'in:' . implode(',', EventPhoto::SLOTS)],
            'photo'    => ['required', 'file', 'max:8192'],
            'taken_at' => ['nullable', 'date'],
        ]);
        $event = $this->event($uuid);
        if ($event->person_id !== $person->id) {
            return $this->refused(__('Only the person who made the entry adds its photos.'), 403);
        }

        $stored = $photos->store($event, $data['slot'], $request->file('photo'), $data['taken_at'] ?? null);
        $event->refresh();

        return response()->json([
            'uuid' => $event->uuid, 'slot' => $data['slot'], 'result' => $stored['result'],
            'photos_received' => $event->photos_received, 'photos_expected' => $event->photos_expected,
        ], $stored['result'] === 'saved' ? 201 : 200);
    }

    public function gps(Request $request, GpsService $gps): JsonResponse
    {
        $person = $this->person();
        $data   = $request->validate([
            'batch'  => ['required', 'uuid'],
            'points' => ['required', 'array', 'min:1', 'max:' . GpsService::MAX_POINTS],
        ]);

        // A batch sent twice (the answer was lost) is stored once.
        if ($kept = ApiCommand::where('uuid', $data['batch'])->first()) {
            return response()->json($kept->result + ['replayed' => true]);
        }
        $result = $gps->store($person, $data['points']);
        ApiCommand::create(['uuid' => $data['batch'], 'company_id' => $person->company_id, 'account_id' => $request->user()->id, 'name' => 'gps.batch', 'http_status' => 200, 'result' => $result]);
        $this->devices->seen($request, ['last_gps_at' => now()]);

        return response()->json($result);
    }

    /** A batch of commands from the phone's queue, run in order. */
    public function commands(Request $request, CrewCommands $commands): JsonResponse
    {
        $person = $this->person();
        $data   = $request->validate([
            'sent_uptime_ms' => ['nullable', 'integer', 'min:0'],
            'commands'       => ['required', 'array', 'min:1', 'max:50'],
            'commands.*'     => ['array'],
        ]);

        $batch   = ['sent_uptime_ms' => $data['sent_uptime_ms'] ?? null];
        $results = collect($data['commands'])->map(fn ($c) => $commands->run($person, $c, $batch))->values();

        return response()->json(['results' => $results, 'server_time' => now()->toIso8601String()]);
    }

    /**
     * A paper's file to show at a checkpost: the papers of the truck on one
     * of their open trips, or their own.
     */
    /**
     * My Khata (blueprint 05 §6.1): what the crew member holds of the
     * company's trip cash, what the company owes them (bata, salary), the net,
     * and their statement — from the books, the same as the office sees.
     * Amounts are from the person's side: `holding` is cash they carry for
     * the company, `owed_to_me` what the company owes them.
     */
    public function money(Request $request, Statements $statements): JsonResponse
    {
        $person = $this->person();
        $f = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $company = app(\App\Platform\Tenancy\CompanyContext::class)->company();
        $today = CarbonImmutable::now($company->timezone ?: 'UTC')->startOfDay();
        $to = isset($f['to']) ? CarbonImmutable::parse($f['to']) : $today;
        $from = isset($f['from']) ? CarbonImmutable::parse($f['from']) : $to->subDays(90);

        // No ledger yet (nothing posted for this person): everything is zero.
        $ledger = LedgerAccount::where('person_id', $person->id)->first();
        $buckets = $ledger ? $statements->crewBuckets($ledger, $to) : ['trip_cash' => 0, 'earnings' => 0, 'loan' => 0, 'net' => 0];
        $statement = $ledger ? $statements->ledger($ledger, $from, $to) : ['opening' => 0, 'rows' => collect(), 'closing' => 0];

        return response()->json([
            'currency' => $company->currency,
            'holding' => round(max(0, $buckets['trip_cash']), 2),
            'owed_to_me' => round(max(0, -$buckets['earnings']), 2),
            // An advance against my pay (M27), taken back from a payslip.
            'advance' => round(max(0, $buckets['loan']), 2),
            // Positive: I give this much back; negative: the company pays me this much.
            'net' => round($buckets['net'], 2),
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'opening' => round((float) $statement['opening'], 2), 'closing' => round((float) $statement['closing'], 2),
            'lines' => $statement['rows']->map(fn ($r) => [
                'date' => (string) $r->voucher_date, 'what' => $r->note ?: $r->narration, 'reference' => $r->reference, 'bucket' => $r->bucket,
                // given: money handed to me (advance, salary paid); credited: what counts for me (spent for the company, bata earned, cash returned).
                'given' => round((float) $r->debit, 2), 'credited' => round((float) $r->credit, 2),
                'balance' => round((float) $r->balance, 2), 'cancelled' => $r->status === 'cancelled',
            ])->values(),
            // My payslips of approved pay runs (M16).
            'payslips' => \App\Modules\Payroll\Models\Payslip::with('run')->where('person_id', $person->id)->where('status', '!=', 'cancelled')
                ->whereHas('run', fn ($q) => $q->whereIn('status', ['approved', 'paid']))->orderByDesc('id')->limit(12)->get()
                ->map(fn ($p) => ['period' => $p->run->periodLabel(), 'from' => $p->run->period_from->toDateString(), 'to' => $p->run->period_to->toDateString(),
                    'basis' => $p->pay_basis, 'units' => (float) $p->units, 'base' => (float) $p->base_pay, 'additions' => (float) $p->additions, 'deductions' => (float) $p->deductions,
                    'lines' => $p->lines ?? [], 'net' => (float) $p->net, 'status' => $p->status, 'paid_amount' => $p->paid_amount !== null ? (float) $p->paid_amount : null,
                    'paid_on' => $p->paid_at?->toDateString(), 'paid_mode' => $p->paid_mode])->values(),
        ]);
    }

    public function paperFile(Document $document, DocumentFile $file, Compliance $compliance): Response
    {
        $person = $this->person();
        $truckIds = Trip::open()->whereHas('crew', fn ($q) => $q->where('person_id', $person->id))->pluck('truck_id')->filter();

        $allowed = ($document->owner_type === 'truck' && $truckIds->contains($document->owner_id) && ! $compliance->isSensitive($document->document_type))
            || ($document->owner_type === 'person' && (int) $document->owner_id === $person->id);
        if (! $allowed || (int) $file->document_id !== $document->id || (int) $file->version !== (int) $document->version) {
            abort(404);
        }

        return response(Storage::disk('local')->get($file->path), 200, [
            'Content-Type' => $file->mime, 'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    // ---- Helpers ---------------------------------------------------------------

    private function person(): Person
    {
        return $this->access->person() ?? throw new \Illuminate\Http\Exceptions\HttpResponseException(
            $this->refused(__('This login is not crew in this company. Use the owner app.'), 403, 'not_crew')
        );
    }

    /** An entry on one of this person's trips. */
    private function event(string $uuid): RoadEvent
    {
        $event = RoadEvent::with('trip.crew')->where('uuid', $uuid)->first();
        if (! $event || ! $event->trip->crew->contains('person_id', $this->person()->id)) {
            throw ValidationException::withMessages(['event' => __('That entry is not on your trip.')]);
        }

        return $event;
    }

    private function refused(string $message, int $status, string $code = 'forbidden'): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => $code], $status);
    }
}
