<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Modules\People\Models\Person;
use App\Modules\People\Services\ActivationService;
use App\Platform\Auth\Identifier;
use App\Platform\Tenancy\Company;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Console\Command;

/**
 * A crew member's activation code from the command line, for building and
 * testing the phone app (docs/handover/local-setup.md): the same code the
 * people page makes, logged the same way. Without --company it uses the demo
 * company (php artisan fleet:demo).
 */
class ActivationCodeIssue extends Command
{
    protected $signature = 'fleet:code {mobile : The crew member\'s mobile, e.g. 9847100001} {--company= : The company\'s public id; the demo company when left out}';

    protected $description = 'Make an activation code for a crew member (for the phone app)';

    public function handle(CompanyContext $context, ActivationService $activation): int
    {
        $company = $this->option('company')
            ? Company::where('uuid', $this->option('company'))->first()
            : Company::where('owner_account_id', Account::where('email', FleetDemo::EMAIL)->value('id'))->first();
        if (! $company) {
            $this->error($this->option('company') ? 'No company with that id.' : 'No demo company: run php artisan fleet:demo first, or give --company.');

            return self::FAILURE;
        }

        return $context->runAs($company, function () use ($company, $activation) {
            $mobile = Identifier::mobile((string) $this->argument('mobile'), $company->country);
            $person = $mobile ? Person::where('mobile', $mobile)->first() : null;
            if (! $person) {
                $this->error("Nobody in {$company->name} has the mobile {$this->argument('mobile')}.");

                return self::FAILURE;
            }

            $code = $activation->issue($person);
            $this->info("{$person->name} ({$person->mobile}) at {$company->name}: activation code {$code} — works once, for " . ActivationService::LIFETIME_HOURS . ' hours.');

            return self::SUCCESS;
        });
    }
}
