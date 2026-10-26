<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Api\Owner\Concerns\RunsOnce;
use App\Http\Controllers\Controller;
use App\Modules\People\Models\LeaveRequest;
use App\Modules\People\Models\Person;
use App\Modules\People\Services\Leave;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The owner app's leave (M40): the requests to approve, a person's leave,
 * and the decision — once per Idempotency-Key (§12). The web's rules
 * (`People/Services/Leave`).
 */
class LeaveController extends Controller
{
    use RunsOnce;

    public function __construct(protected CompanyContext $context, private Leave $leave) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate(['status' => ['nullable', Rule::in(array_keys(LeaveRequest::STATUSES))]]);

        return response()->json(['requests' => LeaveRequest::with('person:id,name,type')->whereIn('person_id', Person::inBranch()->select('id'))
            ->where('status', $f['status'] ?? 'waiting')->orderBy('from_on')->limit(200)->get()->map(fn ($r) => $this->row($r))->values()]);
    }

    public function person(Person $person): JsonResponse
    {
        return response()->json([
            'person' => ['id' => $person->id, 'name' => $person->name],
            'types' => array_values($this->leave->balances($person)),
            'requests' => LeaveRequest::where('person_id', $person->id)->orderByDesc('from_on')->orderByDesc('id')->limit(30)->get()->map(fn ($r) => $this->row($r))->values(),
        ]);
    }

    public function decide(Request $request, LeaveRequest $leave): JsonResponse
    {
        abort_unless(Person::inBranch()->whereKey($leave->person_id)->exists(), 404);

        return $this->once($request, 'leave.decide', function () use ($request, $leave) {
            $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'note' => ['nullable', 'string', 'max:255']]);

            return ['leave' => $this->row($this->leave->decide($leave, $data['decision'], $data['note'] ?? null, $request->user())->load('person:id,name,type'))];
        });
    }

    private function row(LeaveRequest $r): array
    {
        return ['id' => $r->id, 'uuid' => $r->uuid, 'person' => $r->relationLoaded('person') ? ['id' => $r->person_id, 'name' => $r->person?->name] : ['id' => $r->person_id],
            'type' => $r->leave_type, 'type_name' => $this->leave->typeName($r->leave_type), 'from' => $r->from_on->toDateString(), 'to' => $r->to_on->toDateString(),
            'days' => $r->days, 'reason' => $r->reason, 'via' => $r->asked_via, 'status' => $r->status, 'status_label' => __(LeaveRequest::STATUSES[$r->status]), 'note' => $r->decision_note];
    }
}
