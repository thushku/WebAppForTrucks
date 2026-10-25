<?php

namespace App\Console\Commands;

use App\Modules\Tax\Services\TaxPresets;
use App\Platform\Tenancy\Company;
use Illuminate\Console\Command;

/**
 * Give every company its country's tax preset (India GST, UAE VAT). Safe to
 * run again: it only adds what a company is missing and never changes a
 * code, rate or rule the company has.
 */
class TaxPresetsApply extends Command
{
    protected $signature = 'fleet:tax-presets {--company= : One company id only}';

    protected $description = 'Add the country tax presets (codes, dated rates, rules) to companies that miss them';

    public function handle(TaxPresets $presets): int
    {
        $companies = Company::query()->when($this->option('company'), fn ($q, $id) => $q->whereKey($id))->get();
        foreach ($companies as $company) {
            $presets->apply($company);
            $this->line("{$company->name} ({$company->country}): done");
        }

        return self::SUCCESS;
    }
}
