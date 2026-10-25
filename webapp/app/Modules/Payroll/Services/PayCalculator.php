<?php

namespace App\Modules\Payroll\Services;

use App\Modules\People\Models\Person;
use App\Modules\Road\Models\DrivingShift;
use App\Modules\Trips\Models\TripCrew;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;

/**
 * A person's base pay for a period, from their pay basis (13-parity #24):
 *
 *  · monthly — the salary for each calendar month the period touches, for the
 *    days of that month inside the period and inside their employment
 *    (joining and leaving dates); no attendance yet, so every such day counts;
 *  · per trip — trips they were crew on that ended in the period;
 *  · per km — kilometres they drove (their driving shifts) in the period.
 *
 * Days are the company's calendar days.
 */
class PayCalculator
{
    public function __construct(private CompanyContext $context) {}

    /** @return array{basis: string, units: float, rate: float, base: float, note: ?string} */
    public function base(Person $person, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $basis = $person->pay_basis ?: 'monthly';
        $rate = (float) match ($basis) { 'per_trip' => $person->per_trip_rate, 'per_km' => $person->per_km_rate, default => $person->monthly_salary };
        // Inside the employment only.
        $start = $person->joining_date && $person->joining_date->gt($from) ? CarbonImmutable::parse($person->joining_date->toDateString()) : $from;
        $end = $person->leaving_date && $person->leaving_date->lt($to) ? CarbonImmutable::parse($person->leaving_date->toDateString()) : $to;
        if ($end->lt($start)) {
            return ['basis' => $basis, 'units' => 0.0, 'rate' => $rate, 'base' => 0.0, 'note' => __('Not employed in this period.')];
        }

        return match ($basis) {
            'per_trip' => $this->perTrip($person, $start, $end, $rate),
            'per_km' => $this->perKm($person, $start, $end, $rate),
            default => $this->monthly($start, $end, $rate),
        };
    }

    private function monthly(CarbonImmutable $start, CarbonImmutable $end, float $salary): array
    {
        $days = 0;
        $pay = 0.0;
        for ($month = $start->startOfMonth(); $month->lte($end); $month = $month->addMonthNoOverflow()) {
            $a = $start->max($month);
            $b = $end->min($month->endOfMonth()->startOfDay());
            $n = (int) $a->diffInDays($b) + 1;
            $days += $n;
            $pay += $salary * $n / $month->daysInMonth;
        }

        return ['basis' => 'monthly', 'units' => (float) $days, 'rate' => $salary, 'base' => round($pay, 2), 'note' => null];
    }

    private function perTrip(\App\Modules\People\Models\Person $person, CarbonImmutable $start, CarbonImmutable $end, float $rate): array
    {
        [$a, $b] = $this->utc($start, $end);
        $trips = TripCrew::where('person_id', $person->id)->whereNull('rejected_at')
            ->whereHas('trip', fn ($q) => $q->whereIn('status', ['ended', 'settled', 'closed'])->whereBetween('ended_at', [$a, $b]))->count();

        return ['basis' => 'per_trip', 'units' => (float) $trips, 'rate' => $rate, 'base' => round($trips * $rate, 2), 'note' => null];
    }

    private function perKm(\App\Modules\People\Models\Person $person, CarbonImmutable $start, CarbonImmutable $end, float $rate): array
    {
        [$a, $b] = $this->utc($start, $end);
        $km = (int) DrivingShift::where('person_id', $person->id)->whereNotNull('ended_at')->whereBetween('started_at', [$a, $b])
            ->get(['start_odometer', 'end_odometer'])->sum(fn ($s) => $s->start_odometer && $s->end_odometer ? max(0, $s->end_odometer - $s->start_odometer) : 0);

        return ['basis' => 'per_km', 'units' => (float) $km, 'rate' => $rate, 'base' => round($km * $rate, 2), 'note' => null];
    }

    /** The company's days as UTC moments: the start of the first, the end of the last. */
    private function utc(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $tz = $this->context->company()->timezone ?: 'UTC';

        return [CarbonImmutable::parse($start->toDateString(), $tz)->startOfDay()->utc(), CarbonImmutable::parse($end->toDateString(), $tz)->endOfDay()->utc()];
    }
}
