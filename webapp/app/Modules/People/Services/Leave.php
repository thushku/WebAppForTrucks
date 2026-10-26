<?php

namespace App\Modules\People\Services;

use App\Models\Account;
use App\Modules\People\Models\LeaveRequest;
use App\Modules\People\Models\Person;
use App\Modules\Settings\Models\MasterItem;
use App\Platform\Notifications\Notices;
use App\Platform\Tenancy\CompanyContext;
use App\Support\FiscalYear;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Leave (M40, blueprint 05 §2.12). The company's leave types (Settings →
 * Lists: casual, sick, earned… with their days a year and whether they are
 * paid) and always an unpaid one. A crew member asks from the app, or the
 * office records it; someone with leave.approve approves or turns it down.
 *
 * Balances are worked out, not kept: a paid type's days a year — for the
 * part of the financial year the person is employed — less the approved
 * days in that year. Asking for more than is left is refused (the rest is
 * asked for as unpaid leave). Days are calendar days, start and end counted
 * (crew work every day of the week).
 *
 * Approved unpaid leave takes those days off a monthly salary in the pay
 * run (PayCalculator); a trip whose crew is on leave that day needs an
 * override to be sent (TripService::dispatchCheck).
 */
class Leave
{
    public const MAX_DAYS = 60;

    public const DAYS_BACK = 90;

    public function __construct(private CompanyContext $context, private Notices $notices) {}

    /**
     * The leave types to ask for, with an unpaid one when the list has none.
     *
     * @return array<string, array{name: string, paid: bool, days_per_year: int}>
     */
    public function types(): array
    {
        $types = MasterItem::ofList('leave_type')->where('active', true)->orderBy('sort')->orderBy('name')->get()
            ->mapWithKeys(fn ($i) => [$i->code => ['name' => $i->name, 'paid' => (bool) ($i->meta['paid'] ?? true), 'days_per_year' => (int) ($i->meta['days_per_year'] ?? 0)]])->all();
        if (! collect($types)->contains(fn ($t) => ! $t['paid'])) {
            $types['unpaid'] = ['name' => __('Unpaid leave'), 'paid' => false, 'days_per_year' => 0];
        }

        return $types;
    }

    public function typeName(string $code): string
    {
        return $this->types()[$code]['name'] ?? MasterItem::ofList('leave_type')->where('code', $code)->value('name') ?? ($code === 'unpaid' ? __('Unpaid leave') : $code);
    }

    /** The financial year a day falls in: leave is counted per year. */
    public function year(?CarbonImmutable $on = null): FiscalYear
    {
        return FiscalYear::containing($on ?? $this->today(), (int) ($this->context->company()->fy_start_month ?: 4));
    }

    /**
     * Each type with what the person is entitled to this year, has taken,
     * has asked for (waiting) and has left to ask for.
     *
     * @return list<array{code: string, name: string, paid: bool, entitled: ?float, taken: int, waiting: int, left: ?float}>
     */
    public function balances(Person $person, ?CarbonImmutable $on = null): array
    {
        $fy = $this->year($on);
        $held = LeaveRequest::where('person_id', $person->id)->whereIn('status', LeaveRequest::HOLDING)
            ->whereDate('from_on', '<=', $fy->end->toDateString())->whereDate('to_on', '>=', $fy->start->toDateString())->get();
        $rows = [];
        foreach ($this->types() as $code => $type) {
            $mine = $held->where('leave_type', $code);
            $taken = $mine->where('status', 'approved')->sum(fn ($r) => $this->daysWithin($r, $fy->start, $fy->end));
            $waiting = $mine->where('status', 'waiting')->sum(fn ($r) => $this->daysWithin($r, $fy->start, $fy->end));
            $entitled = $type['paid'] ? $this->entitled($person, $type['days_per_year'], $fy) : null;
            $rows[] = ['code' => $code, 'name' => $type['name'], 'paid' => $type['paid'], 'entitled' => $entitled, 'taken' => (int) $taken, 'waiting' => (int) $waiting,
                'left' => $entitled !== null ? max(0.0, $entitled - $taken - $waiting) : null];
        }

        return $rows;
    }

    public function rules(): array
    {
        return [
            'leave_type' => ['required', Rule::in(array_keys($this->types()))],
            'from_on' => ['required', 'date_format:Y-m-d'],
            'to_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_on'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['to_on.after_or_equal' => __('The last day cannot be before the first.'), 'leave_type.in' => __('Choose a leave type.')];
    }

    /**
     * Ask for leave (from the app) or record it (the office). An office
     * person who may approve can record it approved at once.
     *
     * @param array{leave_type: string, from_on: string, to_on: string, reason?: ?string} $data
     */
    public function apply(Person $person, array $data, ?Account $by, string $via = 'office', ?string $uuid = null, bool $approved = false): LeaveRequest
    {
        $from = CarbonImmutable::parse($data['from_on']);
        $to = CarbonImmutable::parse($data['to_on']);
        $days = (int) $from->diffInDays($to) + 1;
        $today = $this->today();
        if ($person->status === 'left') {
            throw ValidationException::withMessages(['person' => __(':name has left.', ['name' => $person->name])]);
        }
        if ($days > self::MAX_DAYS) {
            throw ValidationException::withMessages(['to_on' => __('At most :n days at a time.', ['n' => self::MAX_DAYS])]);
        }
        if ($from->toDateString() < $today->subDays(self::DAYS_BACK)->toDateString() || $from->toDateString() > $today->addYear()->toDateString()) {
            throw ValidationException::withMessages(['from_on' => __('Leave can be from :days days back to a year ahead.', ['days' => self::DAYS_BACK])]);
        }

        return DB::transaction(function () use ($person, $data, $by, $via, $uuid, $approved, $from, $to, $days) {
            // One request at a time per person, so two taps cannot both pass the checks.
            Person::whereKey($person->id)->lockForUpdate()->first();
            $this->checkFree($person, $from, $to);
            $this->checkBalance($person, $data['leave_type'], $from, $days);
            $request = LeaveRequest::create([
                'uuid' => $uuid ?? (string) Str::uuid(), 'person_id' => $person->id, 'leave_type' => $data['leave_type'], 'from_on' => $from->toDateString(), 'to_on' => $to->toDateString(),
                'days' => $days, 'reason' => ($data['reason'] ?? null) ?: null, 'asked_via' => $via, 'created_by' => $by?->id,
            ] + ($approved ? ['status' => 'approved', 'decided_by' => $by?->id, 'decided_at' => now()] : []));
            DB::afterCommit(fn () => $approved ? $this->notices->leaveDecided($request) : $this->notices->leaveAsked($request));

            return $request;
        });
    }

    /** Approve or turn down a waiting request; the person hears it. */
    public function decide(LeaveRequest $request, string $decision, ?string $note, Account $by): LeaveRequest
    {
        if (! in_array($decision, ['approve', 'reject'], true)) {
            throw ValidationException::withMessages(['decision' => __('Approve or turn it down.')]);
        }

        return DB::transaction(function () use ($request, $decision, $note, $by) {
            $request = LeaveRequest::lockForUpdate()->findOrFail($request->id);
            if ($request->status !== 'waiting') {
                throw ValidationException::withMessages(['leave' => __('This request is :status already.', ['status' => mb_strtolower(__(LeaveRequest::STATUSES[$request->status]))])]);
            }
            $request->update(['status' => $decision === 'approve' ? 'approved' : 'rejected', 'decided_by' => $by->id, 'decided_at' => now(), 'decision_note' => $note ? mb_substr($note, 0, 255) : null]);
            DB::afterCommit(fn () => $this->notices->leaveDecided($request));

            return $request;
        });
    }

    /**
     * Cancel a request: the person while it waits; the office also an
     * approved one that has not ended.
     */
    public function cancel(LeaveRequest $request, bool $byOffice): LeaveRequest
    {
        return DB::transaction(function () use ($request, $byOffice) {
            $request = LeaveRequest::lockForUpdate()->findOrFail($request->id);
            $ok = $request->status === 'waiting' || ($byOffice && $request->status === 'approved' && $request->to_on->toDateString() >= $this->today()->toDateString());
            if (! $ok) {
                throw ValidationException::withMessages(['leave' => $byOffice ? __('Only a waiting request, or approved leave that has not ended, can be cancelled.') : __('Only a waiting request can be cancelled; ask the office.')]);
            }
            $request->update(['status' => 'cancelled']);

            return $request;
        });
    }

    /**
     * Who of these people is on approved leave on a day of the span.
     *
     * @return Collection<int, LeaveRequest> by person id
     */
    public function onLeave(iterable $personIds, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return LeaveRequest::whereIn('person_id', collect($personIds)->all())->where('status', 'approved')
            ->whereDate('from_on', '<=', $to->toDateString())->whereDate('to_on', '>=', $from->toDateString())->orderBy('from_on')->get()->keyBy('person_id');
    }

    /** Approved unpaid leave days of a person inside the span (they come off a monthly salary). */
    public function unpaidDays(Person $person, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $unpaid = array_keys(array_filter($this->types(), fn ($t) => ! $t['paid']));
        $unpaid[] = 'unpaid';

        return (int) LeaveRequest::where('person_id', $person->id)->where('status', 'approved')->whereIn('leave_type', array_unique($unpaid))
            ->whereDate('from_on', '<=', $to->toDateString())->whereDate('to_on', '>=', $from->toDateString())->get()
            ->sum(fn ($r) => $this->daysWithin($r, $from, $to));
    }

    // ---- Helpers -----------------------------------------------------------------

    private function checkFree(Person $person, CarbonImmutable $from, CarbonImmutable $to, ?int $except = null): void
    {
        $clash = LeaveRequest::where('person_id', $person->id)->whereIn('status', LeaveRequest::HOLDING)->when($except, fn ($q) => $q->whereKeyNot($except))
            ->whereDate('from_on', '<=', $to->toDateString())->whereDate('to_on', '>=', $from->toDateString())->first();
        if ($clash) {
            throw ValidationException::withMessages(['from_on' => $clash->status === 'approved'
                ? __('Leave from :from to :to is approved already.', ['from' => $clash->from_on->format('d M'), 'to' => $clash->to_on->format('d M Y')])
                : __('Leave from :from to :to is asked for already.', ['from' => $clash->from_on->format('d M'), 'to' => $clash->to_on->format('d M Y')])]);
        }
    }

    private function checkBalance(Person $person, string $type, CarbonImmutable $from, int $days): void
    {
        $row = collect($this->balances($person, $from))->firstWhere('code', $type);
        if ($row && $row['paid'] && $days > $row['left']) {
            throw ValidationException::withMessages(['leave_type' => __('Only :n days of :type are left this year; ask for the rest as unpaid leave.', [
                'n' => rtrim(rtrim(number_format($row['left'], 1), '0'), '.'), 'type' => mb_strtolower($row['name'])])]);
        }
    }

    /** A paid type's days for the part of the year the person is employed, to the half day. */
    private function entitled(Person $person, int $perYear, FiscalYear $fy): float
    {
        $start = $person->joining_date && $person->joining_date->toDateString() > $fy->start->toDateString() ? CarbonImmutable::parse($person->joining_date->toDateString()) : $fy->start;
        $end = $person->leaving_date && $person->leaving_date->toDateString() < $fy->end->toDateString() ? CarbonImmutable::parse($person->leaving_date->toDateString()) : $fy->end;
        if ($end->lt($start)) {
            return 0.0;
        }
        $share = ((int) $start->diffInDays($end) + 1) / ((int) $fy->start->diffInDays($fy->end) + 1);

        return round($perYear * $share * 2) / 2;
    }

    private function daysWithin(LeaveRequest $request, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $a = max($request->from_on->toDateString(), $from->toDateString());
        $b = min($request->to_on->toDateString(), $to->toDateString());

        return $a > $b ? 0 : (int) CarbonImmutable::parse($a)->diffInDays(CarbonImmutable::parse($b)) + 1;
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->context->company()->timezone ?: 'UTC')->startOfDay();
    }
}
