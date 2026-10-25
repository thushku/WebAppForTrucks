<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The financial year a date falls in, for an account whose year starts on
 * a given month and day (India 1 April, UK 6 April, Australia 1 July…).
 *
 * The key is what numbering tokens print: "2026-27" for a year that
 * straddles two calendar years, "2026" when it is the calendar year.
 */
final class FiscalYear
{
    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly string $key,
    ) {}

    public static function containing(CarbonInterface $date, int $startMonth = 1, int $startDay = 1): self
    {
        $date  = CarbonImmutable::instance($date)->startOfDay();
        $start = CarbonImmutable::create($date->year, max(1, min(12, $startMonth)), 1, 0, 0, 0, $date->getTimezone())
            ->setDay(min(max(1, $startDay), CarbonImmutable::create($date->year, $startMonth, 1)->daysInMonth));

        if ($start->gt($date)) {
            $start = $start->subYear();
        }

        $end = $start->addYear()->subDay();

        $key = $start->year === $end->year
            ? (string) $start->year
            : $start->year . '-' . $end->format('y');

        return new self($start, $end, $key);
    }

    /** "FY 2026–27 · 1 Apr 2026 – 31 Mar 2027" */
    public function label(): string
    {
        $range = $this->start->year === $this->end->year
            ? $this->start->translatedFormat('j M') . ' – ' . $this->end->translatedFormat('j M Y')
            : $this->start->translatedFormat('j M Y') . ' – ' . $this->end->translatedFormat('j M Y');

        return 'FY ' . str_replace('-', '–', $this->key) . ' · ' . $range;
    }

    public function contains(CarbonInterface $date): bool
    {
        return $date->between($this->start, $this->end);
    }
}
