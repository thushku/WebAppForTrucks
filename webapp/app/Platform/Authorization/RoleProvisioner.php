<?php

namespace App\Platform\Authorization;

use App\Models\CompanyUser;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Authorization\Models\Role;
use App\Platform\Authorization\Models\RoleAssignment;
use App\Platform\Tenancy\Company;
use Illuminate\Support\Facades\DB;

/**
 * Gives a new company its standard roles, and gives people roles.
 *
 * Roles are copied into the company (not shared), so a company can rename
 * "Manager" to "Dispatcher" or take a permission away without touching
 * anyone else.
 */
class RoleProvisioner
{
    public function __construct(
        private PermissionResolver $resolver,
        private AuditRecorder $audit,
    ) {}

    /** Create any standard role the company does not have yet. Safe to run again. */
    public function provision(Company $company): void
    {
        DB::transaction(function () use ($company) {
            foreach (PermissionCatalogue::ROLES as $code => [$name, $level]) {
                $role = Role::withoutGlobalScopes()->firstOrCreate(
                    ['company_id' => $company->id, 'code' => $code],
                    ['name' => $name, 'level' => $level, 'is_system' => true],
                );

                if ($role->wasRecentlyCreated) {
                    foreach (PermissionCatalogue::defaultsFor($code) as $permission) {
                        $role->permissions()->create(['permission_code' => $permission, 'allow' => true]);
                    }
                }
            }
        });
    }

    /** Give a member a role (optionally for one branch only). */
    public function grant(CompanyUser $member, string $roleCode, ?int $branchId = null): RoleAssignment
    {
        $role = Role::withoutGlobalScopes()
            ->where('company_id', $member->company_id)
            ->where('code', $roleCode)
            ->firstOrFail();

        $assignment = RoleAssignment::withoutGlobalScopes()->firstOrCreate([
            'company_id'      => $member->company_id,
            'company_user_id' => $member->id,
            'role_id'         => $role->id,
            'branch_id'       => $branchId,
        ]);

        $this->resolver->forget($member);

        if ($assignment->wasRecentlyCreated) {
            $this->audit->record(
                'role.granted',
                "Gave the {$role->name} role to {$member->label}",
                'company_user', (string) $member->id, $member->label,
                companyId: $member->company_id,
            );
        }

        return $assignment;
    }

    public function revoke(CompanyUser $member, string $roleCode): void
    {
        $role = Role::withoutGlobalScopes()
            ->where('company_id', $member->company_id)
            ->where('code', $roleCode)
            ->first();

        if (! $role) {
            return;
        }

        $gone = RoleAssignment::withoutGlobalScopes()
            ->where('company_user_id', $member->id)
            ->where('role_id', $role->id)
            ->delete();

        $this->resolver->forget($member);

        if ($gone) {
            $this->audit->record(
                'role.revoked',
                "Took the {$role->name} role from {$member->label}",
                'company_user', (string) $member->id, $member->label,
                companyId: $member->company_id,
            );
        }
    }
}
