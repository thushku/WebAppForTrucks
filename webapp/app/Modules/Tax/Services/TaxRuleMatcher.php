<?php

namespace App\Modules\Tax\Services;

use App\Modules\Tax\Models\TaxRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Freight facts → the rule that decides a line's tax (blueprint 11 §3 step
 * 5). A rule matches when every condition it sets equals the fact; among the
 * matches the one with the most conditions wins, then the higher priority,
 * then the newer row. No match is an error, never 0%.
 */
class TaxRuleMatcher
{
    /** @var array<string, Collection<int, TaxRule>> */
    private array $rules = [];

    /**
     * @param array{service_class: string, gta_option?: ?string, transport_type?: ?string, carrier_role?: ?string, buyer_registered?: ?bool, buyer_category?: ?string} $facts
     */
    public function match(string $regime, array $facts, CarbonInterface $on): ?TaxRule
    {
        $day = $on->toDateString();

        return $this->rules($regime)
            ->filter(fn (TaxRule $r) => ($r->valid_from === null || $r->valid_from->toDateString() <= $day) && ($r->valid_to === null || $r->valid_to->toDateString() >= $day))
            ->filter(function (TaxRule $r) use ($facts) {
                foreach (TaxRule::CONDITIONS as $condition) {
                    if ($r->{$condition} !== null && $r->{$condition} !== ($facts[$condition] ?? null)) {
                        return false;
                    }
                }

                return true;
            })
            ->sortBy([fn ($a, $b) => $b->specificity() <=> $a->specificity(), fn ($a, $b) => $b->priority <=> $a->priority, fn ($a, $b) => $b->id <=> $a->id])
            ->first();
    }

    /** @return Collection<int, TaxRule> */
    private function rules(string $regime): Collection
    {
        $key = app(\App\Platform\Tenancy\CompanyContext::class)->id() . ':' . $regime;

        return $this->rules[$key] ??= TaxRule::where('regime', $regime)->where('active', true)->get();
    }
}
