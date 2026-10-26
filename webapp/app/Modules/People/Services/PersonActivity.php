<?php

namespace App\Modules\People\Services;

use App\Modules\People\Models\Person;
use App\Modules\Road\Models\DrivingShift;
use App\Modules\Road\Models\RoadEvent;
use App\Modules\Road\Services\DrivingHours;
use App\Modules\Settings\Services\CompanySettings;
use App\Modules\Trips\Models\Trip;
use App\Platform\Tenancy\CompanyContext;
use App\Support\FiscalYear;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * One crew member's work (M41), for the person page's Trips and Driving
 * hours tabs and the owner app: the trips they were crew on, with their
 * role and what they spent; their time at the wheel (less recorded rests)
 * shift by shift and day by day, against the company's limit for driving
 * without a break.
 */
class PersonActivity
{
    public function __construct(private CompanyContext $context, private CompanySettings $settings) {}

    /** This financial year to today. @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function defaultPeriod(): array
    {
        $today = $this->today();

        return [FiscalYear::containing($today, (int) ($this->context->company()->fy_start_month ?: 4))->start, $today];
    }

    /** @return Collection<int, object> latest first */
    public function trips(Person $person, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $trips = Trip::with(['from:id,name', 'to:id,name', 'truck:id,reg_no', 'supplierVehicle:id,reg_no', 'crew' => fn ($q) => $q->where('person_id', $person->id)])
            ->whereHas('crew', fn ($q) => $q->where('person_id', $person->id)->whereNull('rejected_at'))
            ->whereBetween('trip_date', [$from->toDateString(), $to->toDateString()])->orderByDesc('trip_date')->orderByDesc('id')->limit(300)->get();
        $spent = RoadEvent::where('person_id', $person->id)->whereIn('trip_id', $trips->pluck('id'))->whereNotNull('amount')->where('type', '!=', 'money_received')
            ->whereNotIn('status', ['rejected', 'cancelled'])->groupBy('trip_id')->selectRaw('trip_id, count(*) as n, sum(amount) as total')->get()->keyBy('trip_id');

        return $trips->map(fn (Trip $t) => (object) [
            'trip' => $t, 'role' => $t->crew->first()?->role, 'route' => collect([$t->from?->name, $t->to?->name])->filter()->join(' → '),
            'vehicle' => $t->truck?->reg_no ?? $t->supplierVehicle?->reg_no,
            'km' => $t->start_odometer && $t->end_odometer && $t->end_odometer > $t->start_odometer ? $t->end_odometer - $t->start_odometer : null,
            'entries' => (int) ($spent[$t->id]->n ?? 0), 'spent' => round((float) ($spent[$t->id]->total ?? 0), 2),
        ]);
    }

    /**
     * Shifts at the wheel in the period, the hours of each day, and the
     * totals; a shift longer than the company's limit without a break is
     * marked.
     *
     * @return array{shifts: Collection<int, object>, days: Collection<string, int>, minutes: int, km: int, days_driven: int, longest: int, over: int, limit: int}
     */
    public function driving(Person $person, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $tz = $this->context->company()->timezone ?: 'UTC';
        $limit = (int) ($this->settings->get('max_continuous_driving_minutes') ?: 300);
        $shifts = DrivingShift::with('trip:id,trip_number,uuid')->where('person_id', $person->id)
            ->whereBetween('started_at', [CarbonImmutable::parse($from->toDateString(), $tz)->startOfDay()->utc(), CarbonImmutable::parse($to->toDateString(), $tz)->endOfDay()->utc()])
            ->orderByDesc('started_at')->get();
        // At the wheel less the rests the crew recorded on each trip (DrivingHours).
        $hours = app(DrivingHours::class);
        $rests = $shifts->pluck('trip')->filter()->unique('id')->mapWithKeys(fn ($trip) => [$trip->id => $hours->rests($trip)]);
        $shifts = $shifts->map(function (DrivingShift $s) use ($hours, $rests, $limit, $tz) {
            $minutes = $hours->minutes($s, $rests[$s->trip_id] ?? collect());

            return (object) ['shift' => $s, 'minutes' => $minutes, 'over' => $minutes > $limit, 'open' => $s->ended_at === null,
                'km' => $s->start_odometer && $s->end_odometer ? max(0, $s->end_odometer - $s->start_odometer) : null,
                'day' => CarbonImmutable::instance($s->started_at)->setTimezone($tz)->toDateString()];
        });
        // Each shift's time at the wheel spread over the calendar days it covers (a night drive counts on both days).
        $days = collect();
        foreach ($shifts as $r) {
            $start = CarbonImmutable::instance($r->shift->started_at)->setTimezone($tz);
            $end = CarbonImmutable::instance($r->shift->ended_at ?? now())->setTimezone($tz);
            $elapsed = max(1, $start->diffInMinutes($end));
            for ($day = $start->startOfDay(); $day->lt($end); $day = $day->addDay()) {
                $part = $day->max($start)->diffInMinutes($day->addDay()->min($end));
                $days[$day->toDateString()] = ($days[$day->toDateString()] ?? 0) + (int) round($r->minutes * $part / $elapsed);
            }
        }
        $days = $days->filter()->sortKeysDesc();

        return [
            'shifts' => $shifts, 'days' => $days, 'minutes' => (int) $shifts->sum('minutes'), 'km' => (int) $shifts->sum('km'),
            'days_driven' => $days->count(), 'longest' => (int) ($shifts->max('minutes') ?? 0), 'over' => $shifts->where('over', true)->count(), 'limit' => $limit,
        ];
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->context->company()->timezone ?: 'UTC')->startOfDay();
    }
}
