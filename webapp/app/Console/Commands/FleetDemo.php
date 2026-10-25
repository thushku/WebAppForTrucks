<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\CompanyUser;
use App\Modules\Parties\Models\PartyContact;
use App\Modules\Parties\Models\SupplierVehicle;
use App\Modules\Parties\Services\PartyService;
use App\Modules\Parties\Services\TaxIds;
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

    public function handle(SignupService $signup, RoleProvisioner $roles, CompanyContext $context, PartyService $parties): int
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

        $context->runAs($company, function () use ($company, $roles, $parties, $owner) {
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

            $this->parties($parties, $owner->id);

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

    /** Clients, a supplier with hired trucks, vendors, and approved rates. */
    private function parties(PartyService $parties, int $ownerId): void
    {
        $gstin = fn (string $state, string $pan) => $state . $pan . '1Z' . TaxIds::gstinCheckCharacter($state . $pan . '1Z');

        $acc = $parties->save(null, ['name' => 'ACC Limited', 'legal_name' => 'ACC Limited', 'short_code' => 'ACC', 'country' => 'IN',
            'city' => 'Mumbai', 'mobile' => '+919820000101', 'email' => 'logistics@acc.test', 'credit_days' => 45, 'credit_limit' => 2500000,
            'due_basis' => 'submission', 'tds_deducted' => true, 'tds_section' => '194C', 'tds_rate' => 2, 'billing_cycle' => 'monthly',
            'pod_required' => 'original', 'created_by' => $ownerId], ['client', 'consignor', 'consignee'], $gstin('27', 'AAACA5678K'));
        $parties->addTaxId($acc, ['tax_id' => $gstin('32', 'AAACA5678K'), 'country' => 'IN', 'address' => 'Cement terminal, Vallarpadam, Kochi']);
        PartyContact::create(['party_id' => $acc->id, 'name' => 'Suresh Nair', 'role' => 'dispatch', 'mobile' => '+919847000202', 'is_primary' => true]);
        PartyContact::create(['party_id' => $acc->id, 'name' => 'Meena Iyer', 'role' => 'accounts', 'email' => 'payables@acc.test']);

        $parties->save(null, ['name' => 'UltraTech Cement', 'short_code' => 'ULT', 'country' => 'IN', 'city' => 'Chennai', 'credit_days' => 30,
            'created_by' => $ownerId], ['client'], $gstin('33', 'AAACU9876M'));
        $parties->save(null, ['name' => 'Kerala Agro Foods', 'short_code' => 'KAF', 'country' => 'IN', 'city' => 'Thrissur', 'state_code' => '32',
            'created_by' => $ownerId], ['client', 'consignor'], null);

        $raju = $parties->save(null, ['name' => 'Raju Transport', 'country' => 'IN', 'city' => 'Bhiwandi', 'state_code' => '27', 'mobile' => '+919890000303',
            'supplier_type' => 'owner_operator', 'pan' => 'ABCPR1234K', 'rating' => 4, 'payment_days' => 15, 'created_by' => $ownerId], ['supplier'], null);
        SupplierVehicle::create(['party_id' => $raju->id, 'reg_no' => 'MH-04-KL-7788', 'truck_type' => '12 wheels', 'capacity_mt' => 21, 'rc_owner_name' => 'Raju Patil',
            'driver_name' => 'Ganesh', 'driver_mobile' => '+919890000404']);

        $parties->save(null, ['name' => 'Indian Oil — Aluva Highway', 'country' => 'IN', 'city' => 'Aluva', 'state_code' => '32', 'vendor_category' => 'fuel_pump',
            'lat' => 10.1004, 'lng' => 76.3570, 'payment_days' => 7, 'created_by' => $ownerId], ['vendor'], null);
        $parties->save(null, ['name' => 'Malabar Truck Works', 'country' => 'IN', 'city' => 'Kochi', 'state_code' => '32', 'vendor_category' => 'workshop',
            'udyam_number' => 'UDYAM-KL-07-0012345', 'udyam_type' => 'micro', 'payment_days' => 30, 'created_by' => $ownerId], ['vendor'], null);

        $places = \App\Modules\Settings\Models\Place::pluck('id', 'short_code');
        $contract = \App\Modules\Parties\Models\RateContract::create(['party_id' => $acc->id, 'name' => 'Cement 2026', 'valid_from' => now()->startOfMonth()->toDateString(), 'created_by' => $ownerId]);
        foreach ([['COK', 'BOM', 'tonne', 3400, 21], ['COK', 'BHW', 'tonne', 3300, 21], ['COK', 'MAA', 'tonne', 1900, 21], ['COK', 'BLR', 'trip', 52000, null]] as [$from, $to, $basis, $rate, $min]) {
            $contract->lines()->create(['from_place_id' => $places[$from], 'to_place_id' => $places[$to], 'material' => 'cement', 'basis' => $basis, 'rate' => $rate,
                'min_weight_mt' => $min, 'detention_free_hours' => 24, 'detention_rate_per_day' => 2500]);
        }
        auth()->setUser(\App\Models\Account::find($ownerId));
        $parties->approve($contract);
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
