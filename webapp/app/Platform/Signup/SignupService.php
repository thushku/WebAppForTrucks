<?php

namespace App\Platform\Signup;

use App\Models\Account;
use App\Models\CompanyUser;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Authorization\RoleProvisioner;
use App\Platform\Settings\PlatformSettings;
use App\Platform\Tenancy\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * A new customer signs up: a company on a free trial, its owner's login,
 * the owner's membership and the standard roles — all in one transaction,
 * so a failure halfway leaves nothing behind.
 *
 * The trial length comes from the super-admin's setting (D20), 14 days by
 * default, with no card asked for.
 */
class SignupService
{
    /** Country presets for a new company (more countries are data, not code). */
    public const COUNTRIES = [
        'IN' => ['currency' => 'INR', 'timezone' => 'Asia/Kolkata', 'fy_start_month' => 4],
        'AE' => ['currency' => 'AED', 'timezone' => 'Asia/Dubai', 'fy_start_month' => 1],
    ];

    public function __construct(
        private RoleProvisioner $roles,
        private PlatformSettings $settings,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param array{company: string, country: string, name: string, email: string, mobile: ?string, password: string} $data
     * @return array{account: Account, company: Company}
     */
    public function register(array $data): array
    {
        $preset = self::COUNTRIES[$data['country']] ?? self::COUNTRIES['IN'];

        return DB::transaction(function () use ($data, $preset) {
            $account = new Account([
                'name'   => $data['name'],
                'email'  => $data['email'],
                'mobile' => $data['mobile'] ?? null,
            ]);
            $account->password_hash = Hash::make($data['password']);
            $account->save();

            $company = Company::create([
                'name'             => $data['company'],
                'country'          => $data['country'],
                'currency'         => $preset['currency'],
                'timezone'         => $preset['timezone'],
                'fy_start_month'   => $preset['fy_start_month'],
                'status'           => 'trial',
                'trial_ends_at'    => now()->addDays($this->settings->trialDays())->endOfDay(),
                'owner_account_id' => $account->id,
            ]);

            $member = CompanyUser::create([
                'company_id' => $company->id,
                'account_id' => $account->id,
                'status'     => 'active',
                'joined_at'  => now(),
                'title'      => 'Owner',
            ]);

            $this->roles->provision($company);
            $this->roles->grant($member, 'owner');

            $this->audit->record('company.signed_up', "{$company->name} signed up ({$data['country']}) with {$account->email}", 'company', (string) $company->id, $company->name, companyId: $company->id);

            return ['account' => $account, 'company' => $company];
        });
    }
}
