<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Api\Owner\Concerns\RunsOnce;
use App\Http\Controllers\Controller;
use App\Modules\Fleet\Models\Fine;
use App\Modules\Fleet\Services\Fines;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The owner app's fines (M48): the register (open, paid, cancelled), the
 * drivers' black points, a fine recorded — the trip and driver found from
 * the truck and time —, paid, disputed or cancelled, each write once per
 * Idempotency-Key (§12). The web's rules (`Fleet/Services/Fines`).
 */
class FinesController extends Controller
{
    use RunsOnce;

    public function __construct(protected CompanyContext $context, private Fines $fines) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate(['status' => ['nullable', Rule::in(['open', 'paid', 'cancelled', 'all'])], 'truck_id' => ['nullable', 'integer'], 'person_id' => ['nullable', 'integer']]);
        $status = $f['status'] ?? 'open';
        $list = Fine::visible()->with(['truck:id,reg_no', 'person:id,name', 'payslip.run'])
            ->when($status === 'open', fn ($q) => $q->whereIn('status', ['unpaid', 'disputed']))->when(in_array($status, ['paid', 'cancelled'], true), fn ($q) => $q->where('status', $status))
            ->when($f['truck_id'] ?? null, fn ($q, $id) => $q->where('truck_id', $id))->when($f['person_id'] ?? null, fn ($q, $id) => $q->where('person_id', $id))
            ->orderByDesc('issued_at')->limit(300)->get();
        $names = \App\Modules\People\Models\Person::whereIn('id', ($points = $this->fines->points())->pluck('person_id'))->pluck('name', 'id');

        return response()->json([
            'fines' => $list->map(fn (Fine $fine) => $this->row($fine))->values(),
            'open' => $this->fines->open(),
            'points' => $points->map(fn ($p) => ['person' => ['id' => $p->person_id, 'name' => $names[$p->person_id] ?? null], 'points' => $p->points, 'fines' => $p->fines])->values(),
            'points_limit' => Fine::POINTS_LIMIT,
        ]);
    }

    public function show(Fine $fine): JsonResponse
    {
        abort_unless(Fine::visible()->whereKey($fine->id)->exists(), 404);

        return response()->json(['fine' => $this->row($fine->load(['truck:id,reg_no', 'person:id,name', 'trip:id,uuid,trip_number', 'payslip.run']))]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->once($request, 'fine', function () use ($request) {
            $fine = $this->fines->record($request->validate($this->fines->rules()), $request->user());

            return ['fine' => $this->row($fine->load(['truck:id,reg_no', 'person:id,name', 'trip:id,uuid,trip_number', 'payslip.run']))];
        });
    }

    public function pay(Request $request, Fine $fine): JsonResponse
    {
        abort_unless(Fine::visible()->whereKey($fine->id)->exists(), 404);

        return $this->once($request, 'fine.pay', function () use ($request, $fine) {
            $fine = $this->fines->pay($fine, $request->validate($this->fines->payRules()), $request->user());

            return ['fine' => $this->row($fine->load(['truck:id,reg_no', 'person:id,name', 'trip:id,uuid,trip_number', 'payslip.run']))];
        });
    }

    /** Dispute, back to unpaid, or cancel (with why). */
    public function status(Request $request, Fine $fine): JsonResponse
    {
        abort_unless(Fine::visible()->whereKey($fine->id)->exists(), 404);
        $data = $request->validate(['action' => ['required', 'in:dispute,reopen,cancel'], 'note' => ['nullable', 'string', 'max:255']]);
        match ($data['action']) {
            'dispute' => $this->fines->dispute($fine, (string) ($data['note'] ?? '')),
            'reopen' => $this->fines->reopen($fine),
            'cancel' => $this->fines->cancel($fine, (string) ($data['note'] ?? '')),
        };

        return response()->json(['fine' => $this->row($fine->fresh(['truck:id,reg_no', 'person:id,name', 'trip:id,uuid,trip_number', 'payslip.run']))]);
    }

    private function row(Fine $fine): array
    {
        $run = $fine->payslip?->run;

        return [
            'id' => $fine->id, 'challan_no' => $fine->challan_no, 'authority' => $fine->authority, 'offence' => $fine->offence, 'place' => $fine->place,
            'issued_at' => $fine->issued_at->toIso8601String(), 'amount' => (float) $fine->amount, 'points' => $fine->points,
            'due_on' => $fine->due_on?->toDateString(), 'status' => $fine->status, 'late' => $this->fines->isLate($fine), 'status_note' => $fine->status_note,
            'truck' => $fine->truck ? ['id' => $fine->truck->id, 'reg_no' => $fine->truck->reg_no] : null,
            'person' => $fine->person ? ['id' => $fine->person->id, 'name' => $fine->person->name] : null,
            'trip' => $fine->relationLoaded('trip') && $fine->trip ? ['uuid' => $fine->trip->uuid, 'number' => $fine->trip->trip_number] : null,
            'bears' => $fine->bears,
            // driver only: where taking it back from their pay stands — waiting, in a draft run, taken.
            'recovery' => $fine->bears !== 'driver' ? null : ($run ? ['state' => $run->status === 'draft' ? 'in_draft_run' : 'taken', 'period' => $run->periodLabel()] : ['state' => 'waiting', 'period' => null]),
            'paid_on' => $fine->paid_on?->toDateString(), 'paid_mode' => $fine->paid_mode, 'paid_reference' => $fine->paid_reference, 'notes' => $fine->notes,
        ];
    }
}
