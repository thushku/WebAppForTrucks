<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\CompanyUser;
use App\Modules\Settings\Models\Branch;
use App\Modules\Settings\Models\Place;
use App\Platform\Authorization\RoleProvisioner;
use App\Platform\Signup\SignupService;
use App\Platform\Tenancy\Company;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * A demo company to look around in: Sharma Roadlines, Kochi, with a Mumbai
 * branch, the places its trucks go to, and a small office team. Each later
 * milestone adds its own demo data here (trucks, crew, trips, bills).
 *
 * Refuses to run in production. Running it again rebuilds the demo company.
 */
class FleetDemo extends Command
{
    protected $signature = 'fleet:demo {--fresh : Delete the demo company first}';

    protected $description = 'Build the demo company (demo@fleet.test / Demo-Pass-2026)';

    public const EMAIL    = 'demo@fleet.test';
    public const PASSWORD = 'Demo-Pass-2026';

    public function handle(SignupService $signup, RoleProvisioner $roles, CompanyContext $context): int
    {
        if (app()->isProduction()) {
            $this->error('Not in production.');

            return self::FAILURE;
        }

        $existing = Account::where('email', self::EMAIL)->first();
        if ($existing && ! $this->option('fresh')) {
            $this->info('The demo company exists. Use --fresh to build it again.');

            return self::SUCCESS;
        }
        if ($existing) {
            $this->forget($existing);
        }

        ['account' => $owner, 'company' => $company] = $signup->register([
            'company' => 'Sharma Roadlines', 'country' => 'IN', 'name' => 'Rajesh Sharma',
            'email' => self::EMAIL, 'mobile' => '+919847000001', 'password' => self::PASSWORD,
        ]);
        $owner->forceFill(['email_verified_at' => now()])->save();
        $company->forceFill([
            'legal_name' => 'Sharma Roadlines Private Limited', 'short_code' => 'SRL',
            'address_line1' => '14/220, Container Road', 'address_line2' => 'Vallarpadam', 'city' => 'Kochi',
            'state_code' => '32', 'postal_code' => '682504', 'phone' => '+91 484 2400100', 'email' => 'office@sharmaroadlines.test',
        ])->save();

        $context->runAs($company, function () use ($company, $roles) {
            Branch::where('code', 'HO')->update(['name' => 'Kochi head office', 'city' => 'Kochi', 'state_code' => '32']);
            Branch::create(['name' => 'Mumbai branch', 'code' => 'BOM', 'city' => 'Bhiwandi', 'state_code' => '27']);

            foreach ([
                ['Kochi', 'COK', 'city', '32', 9.9312, 76.2673], ['Mumbai', 'BOM', 'city', '27', 19.0760, 72.8777],
                ['Bhiwandi', 'BHW', 'godown', '27', 19.2813, 73.0483], ['Chennai', 'MAA', 'city', '33', 13.0827, 80.2707],
                ['Bengaluru', 'BLR', 'city', '29', 12.9716, 77.5946], ['Coimbatore', 'CJB', 'city', '33', 11.0168, 76.9558],
                ['Hyderabad', 'HYD', 'city', '36', 17.3850, 78.4867], ['Pune', 'PNQ', 'city', '27', 18.5204, 73.8567],
                ['Walayar checkpost', 'WLR', 'border', '32', 10.8470, 76.8390], ['Kumbalam toll plaza', 'KBM', 'toll', '32', 9.9080, 76.3160],
            ] as [$name, $code, $type, $state, $lat, $lng]) {
                Place::create(['name' => $name, 'short_code' => $code, 'type' => $type, 'state_code' => $state, 'city' => $type === 'city' ? $name : null, 'lat' => $lat, 'lng' => $lng]);
            }

            foreach ([['Anita Menon', 'anita@fleet.test', 'accountant', 'Accounts'], ['Vikram Rao', 'vikram@fleet.test', 'manager', 'Operations manager']] as [$name, $email, $role, $title]) {
                $account = Account::firstOrNew(['email' => $email]);
                $account->name = $name;
                $account->password_hash = Hash::make(self::PASSWORD);
                $account->email_verified_at = now();
                $account->save();
                $member = CompanyUser::create(['company_id' => $company->id, 'account_id' => $account->id, 'status' => 'active', 'joined_at' => now(), 'title' => $title]);
                $roles->grant($member, $role);
            }
        });

        $this->info('Demo company ready. Sign in as ' . self::EMAIL . ' / ' . self::PASSWORD . ' (also anita@ and vikram@fleet.test, same password).');

        return self::SUCCESS;
    }

    /** Remove the old demo company and its people (only rows the demo made). */
    private function forget(Account $owner): void
    {
        DB::transaction(function () use ($owner) {
            foreach (Company::where('owner_account_id', $owner->id)->get() as $company) {
                DB::table('role_assignments')->where('company_id', $company->id)->delete();
                DB::table('role_permissions')->whereIn('role_id', DB::table('roles')->where('company_id', $company->id)->pluck('id'))->delete();
                DB::table('roles')->where('company_id', $company->id)->delete();
                DB::table('company_users')->where('company_id', $company->id)->delete();
                $company->delete();
            }
            Account::whereIn('email', [self::EMAIL, 'anita@fleet.test', 'vikram@fleet.test'])->delete();
        });
    }
}
