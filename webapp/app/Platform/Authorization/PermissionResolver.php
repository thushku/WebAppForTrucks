<?php

namespace App\Platform\Authorization;

use App\Models\CompanyUser;
use App\Platform\Authorization\Models\RoleAssignment;

/**
 * "Does this member hold this permission?" — everything asks this, nothing
 * asks about role names (ported from Clinic Ledger).
 *
 * Memoised for the life of the request: it sits on every check, often
 * dozens per page while a list is drawn.
 */
class PermissionResolver
{
    /** @var array<int, array{permissions: string[], level: int, branch_ids: ?array}> */
    private array $memo = [];

    public function has(CompanyUser $member, string $code): bool
    {
        if (! $member->isActive()) {
            return false;
        }

        return in_array($code, $this->resolve($member)['permissions'], true);
    }

    public function hasAny(CompanyUser $member, array $codes): bool
    {
        foreach ($codes as $code) {
            if ($this->has($member, $code)) {
                return true;
            }
        }

        return false;
    }

    /** @return string[] */
    public function permissionsFor(CompanyUser $member): array
    {
        return $this->resolve($member)['permissions'];
    }

    /** The most senior level held. Lower is more senior; 0 is the owner. */
    public function levelFor(CompanyUser $member): int
    {
        return $this->resolve($member)['level'];
    }

    /**
     * The branches this member is limited to, or null for the whole company.
     * A role given for one branch only limits what that role grants.
     *
     * @return int[]|null
     */
    public function branchScope(CompanyUser $member): ?array
    {
        return $this->resolve($member)['branch_ids'];
    }

    /** Discard the memo — after a grant changes mid-request. */
    public function forget(?CompanyUser $member = null): void
    {
        if ($member === null) {
            $this->memo = [];

            return;
        }

        unset($this->memo[$member->id]);
    }

    /** @return string[] permission prefixes the company's plan leaves out */
    private function blockedByPlan(int $companyId): array
    {
        $planId = \Illuminate\Support\Facades\DB::table('companies')->where('id', $companyId)->value('plan_id');

        return $planId ? (\App\Platform\Billing\Plan::find($planId)?->blockedPrefixes() ?? []) : [];
    }

    private function resolve(CompanyUser $member): array
    {
        if (isset($this->memo[$member->id])) {
            return $this->memo[$member->id];
        }

        // Explicitly filtered by the member's own company, so this works the
        // same before and after a company is put into context (sign-in).
        $assignments = RoleAssignment::withoutGlobalScopes()
            ->with(['role' => fn ($q) => $q->withoutGlobalScopes(), 'role.permissions'])
            ->where('company_user_id', $member->id)
            ->where('company_id', $member->company_id)
            ->active()
            ->get();

        $permissions = [];
        $level       = 9;
        $branchIds   = [];
        $wholeCompany = false;

        foreach ($assignments as $assignment) {
            $role = $assignment->role;
            if (! $role) {
                continue;
            }

            $level = min($level, (int) $role->level);
            $assignment->branch_id === null ? $wholeCompany = true : $branchIds[] = (int) $assignment->branch_id;

            foreach ($role->permissions as $rp) {
                if ($rp->allow) {
                    $permissions[$rp->permission_code] = true;
                }
            }
        }

        // Explicit denials beat grants from any other role: a company that
        // takes settings.bank away from a role expects it gone, not brought
        // back by a second role somebody forgot about.
        foreach ($assignments as $assignment) {
            foreach ($assignment->role?->permissions ?? [] as $rp) {
                if (! $rp->allow) {
                    unset($permissions[$rp->permission_code]);
                }
            }
        }

        // The company's plan may leave modules out (M17): their permissions go, for everybody.
        $blocked = $this->blockedByPlan((int) $member->company_id);
        if ($blocked) {
            $permissions = array_filter($permissions, fn ($v, $code) => ! array_filter($blocked, fn ($prefix) => str_starts_with($code, $prefix)), ARRAY_FILTER_USE_BOTH);
        }

        return $this->memo[$member->id] = [
            'permissions' => array_keys($permissions),
            'level'       => $level,
            'branch_ids'  => $wholeCompany || $branchIds === [] ? null : array_values(array_unique($branchIds)),
        ];
    }
}
