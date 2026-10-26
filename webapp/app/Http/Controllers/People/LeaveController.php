<?php

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Modules\People\Models\LeaveRequest;
use App\Modules\People\Models\Person;
use App\Modules\People\Services\Leave;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * People → Leave (M40): the requests — those waiting first — to approve or
 * turn down, and leave recorded by the office for someone (approved at once
 * when the recorder may approve leave).
 */
class LeaveController extends Controller
{
    public function __construct(private CompanyContext $context, private Leave $leave) {}

    public function index(Request $request): View
    {
        // The waiting ones first, while there are any; "all" shows every request.
        $asked = (string) $request->query('status');
        $status = array_key_exists($asked, LeaveRequest::STATUSES) ? $asked : ($asked === 'all' ? '' : (LeaveRequest::where('status', 'waiting')->exists() ? 'waiting' : ''));
        $people = Person::inBranch()->working()->orderBy('name')->get(['id', 'name', 'type']);

        return view('people.leave', [
            'status' => $status,
            'requests' => LeaveRequest::with(['person:id,name,type', 'decider:id,name'])->whereIn('person_id', Person::inBranch()->select('id'))
                ->when($status, fn ($q) => $q->where('status', $status))->orderByRaw("status = 'waiting' desc")->orderByDesc('from_on')->orderByDesc('id')->paginate(40)->withQueryString(),
            'counts' => LeaveRequest::whereIn('person_id', Person::inBranch()->select('id'))->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'people' => $people, 'types' => $this->leave->types(), 'service' => $this->leave,
            'onLeave' => $this->leave->onLeave($people->pluck('id'), $this->today(), $this->today()),
            'chosen' => (int) $request->query('person'), 'today' => $this->today(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->leave->rules() + ['person_id' => ['required', 'integer', Rule::exists('people', 'id')->where('company_id', $this->context->id())]], $this->leave->messages());
        $person = Person::inBranch()->findOrFail($data['person_id']);
        $approve = $request->user()->can('leave.approve');
        $this->leave->apply($person, $data, $request->user(), 'office', null, $approve);

        return redirect()->route('people.leave')->with('status', $approve ? __('Leave recorded for :name, approved.', ['name' => $person->name]) : __('Leave asked for :name; it waits for approval.', ['name' => $person->name]));
    }

    public function decide(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $this->visible($leave);
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'note' => ['nullable', 'string', 'max:255']]);
        $this->leave->decide($leave, $data['decision'], $data['note'] ?? null, $request->user());

        return back()->with('status', $data['decision'] === 'approve' ? __('Leave approved.') : __('Leave turned down.'));
    }

    public function cancel(LeaveRequest $leave): RedirectResponse
    {
        $this->visible($leave);
        $this->leave->cancel($leave, true);

        return back()->with('status', __('Leave cancelled.'));
    }

    /** A branch-limited person works only on their branch's people. */
    private function visible(LeaveRequest $leave): void
    {
        abort_unless(Person::inBranch()->whereKey($leave->person_id)->exists(), 404);
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->context->company()->timezone ?: 'UTC')->startOfDay();
    }
}
