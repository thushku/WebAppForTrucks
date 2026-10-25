<?php

namespace App\Platform\Team;

use App\Models\Account;
use App\Models\CompanyUser;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Auth\PasswordTokens;
use App\Platform\Authorization\Models\Role;
use App\Platform\Authorization\Models\RoleAssignment;
use App\Platform\Authorization\PermissionResolver;
use App\Platform\Authorization\RoleProvisioner;
use App\Platform\Tenancy\Company;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * The team: inviting people, changing their roles, suspending and removing
 * them (blueprint 02 §4).
 *
 * Two rules keep this safe:
 *  · Nobody hands out more power than they hold. A role is "more powerful"
 *    when its level number is lower (Owner 0, Manager 1 … Helper 7); the
 *    Owner role also needs `role.grant_elevated`.
 *  · A company never loses its last active Owner.
 *
 * Office people (who use the web app) are invited by email here. Crew who only
 * use the phone app are added under People with an activation code (M4).
 */
class TeamService
{
    public function __construct(
        private CompanyContext $context,
        private RoleProvisioner $roles,
        private PermissionResolver $resolver,
        private PasswordTokens $tokens,
        private AuditRecorder $audit,
    ) {}

    /**
     * Invite someone by email with one or more roles. An address that already
     * has a login (in this or another company) joins with that login.
     *
     * @param string[] $roleCodes
     */
    public function invite(CompanyUser $by, string $name, string $email, array $roleCodes, ?string $title = null): CompanyUser
    {
        $company = $this->context->company();
        $roles   = $this->grantableRoles($by, $roleCodes);

        $member = DB::transaction(function () use ($company, $name, $email, $roles, $title) {
            $account = Account::where('email', $email)->lockForUpdate()->first();
            if (! $account) {
                $account = new Account(['name' => $name, 'email' => $email]);
                $account->save();
            }

            $member = CompanyUser::where('company_id', $company->id)->where('account_id', $account->id)->lockForUpdate()->first();
            if ($member && $member->status !== 'left') {
                throw ValidationException::withMessages(['email' => __('This person is already in the team.')]);
            }

            $member ??= new CompanyUser(['company_id' => $company->id, 'account_id' => $account->id]);
            $member->forceFill(['status' => 'invited', 'title' => $title, 'joined_at' => null])->save();
            $member->setRelation('account', $account);

            foreach ($roles as $role) {
                $this->roles->grant($member, $role->code);
            }

            return $member;
        });

        $this->audit->record('user.invited', "Invited {$name} ({$email}) as " . $roles->pluck('name')->join(', '), 'company_user', (string) $member->id, $name);
        $this->sendInvitation($member);

        return $member;
    }

    /** Email (again) the link that lets an invited person join. */
    public function sendInvitation(CompanyUser $member): void
    {
        $company = $this->context->company();
        $token   = $this->tokens->issue('company_user', $member->id, 'invite');
        $link    = route('invite.show', $token);
        $email   = (string) $member->account->email;

        Mail::raw(
            __("You have been invited to join :company.\n\nOpen this link to accept (it works for 7 days):\n:link\n\nIf you were not expecting this, ignore this message.", ['company' => $company->name, 'link' => $link]),
            fn ($m) => $m->to($email)->subject(__('Join :company', ['company' => $company->name])),
        );
    }

    /**
     * Replace a member's roles.
     *
     * @param string[] $roleCodes
     */
    public function setRoles(CompanyUser $by, CompanyUser $member, array $roleCodes): void
    {
        $this->assertCanManage($by, $member);
        $wanted = $this->grantableRoles($by, $roleCodes)->pluck('code')->all();

        DB::transaction(function () use ($by, $member, $wanted) {
            $current = $this->roleCodes($member);

            foreach (array_diff($current, $wanted) as $code) {
                $this->assertGrantable($by, $this->role($code));
                $this->roles->revoke($member, $code);
            }
            foreach (array_diff($wanted, $current) as $code) {
                $this->roles->grant($member, $code);
            }

            $this->assertOwnerRemains();
        });
    }

    public function suspend(CompanyUser $by, CompanyUser $member): void
    {
        $this->assertCanManage($by, $member);
        if ($by->id === $member->id) {
            throw ValidationException::withMessages(['member' => __('You cannot suspend yourself.')]);
        }

        DB::transaction(function () use ($member) {
            $member->forceFill(['status' => 'suspended'])->save();
            $this->assertOwnerRemains();
        });
        $this->resolver->forget($member);
        $this->audit->record('user.suspended', "Suspended {$member->label}", 'company_user', (string) $member->id, $member->label, severity: 'notable');
    }

    public function reactivate(CompanyUser $by, CompanyUser $member): void
    {
        $this->assertCanManage($by, $member);
        $member->forceFill(['status' => $member->joined_at ? 'active' : 'invited'])->save();
        $this->audit->record('user.reactivated', "Reactivated {$member->label}", 'company_user', (string) $member->id, $member->label);
    }

    /** The person leaves the team; their past work stays under their name. */
    public function remove(CompanyUser $by, CompanyUser $member): void
    {
        $this->assertCanManage($by, $member);
        if ($by->id === $member->id) {
            throw ValidationException::withMessages(['member' => __('You cannot remove yourself.')]);
        }

        DB::transaction(function () use ($member) {
            RoleAssignment::where('company_user_id', $member->id)->delete();
            $member->forceFill(['status' => 'left'])->save();
            $this->assertOwnerRemains();
        });
        $this->resolver->forget($member);
        $this->audit->record('user.removed', "Removed {$member->label} from the team", 'company_user', (string) $member->id, $member->label, severity: 'notable');
    }

    /** Accept an invitation: the membership becomes active (and a new login gets its password). */
    public function accept(CompanyUser $member, ?string $name = null, ?string $passwordHash = null): void
    {
        DB::transaction(function () use ($member, $name, $passwordHash) {
            $account = $member->account;
            if ($passwordHash !== null) {
                $account->password_hash = $passwordHash;
                $account->email_verified_at ??= now();
                if ($name) {
                    $account->name = $name;
                }
                $account->save();
            }

            $member->forceFill(['status' => 'active', 'joined_at' => now()])->save();
        });

        $this->audit->record('user.joined', "{$member->label} accepted the invitation", 'company_user', (string) $member->id, $member->label, companyId: $member->company_id);
    }

    // ---- Who may do what ---------------------------------------------------------

    /** The most powerful level the person holds (0 = Owner). */
    public function levelOf(CompanyUser $member): int
    {
        return $this->resolver->levelFor($member);
    }

    /** May `$by` change `$member`? Only people at their own level or below. */
    public function canManage(CompanyUser $by, CompanyUser $member): bool
    {
        return $member->company_id === $by->company_id && $this->levelOf($member) >= $this->levelOf($by);
    }

    /** Roles `$by` may hand out. */
    public function grantable(CompanyUser $by)
    {
        $level    = $this->levelOf($by);
        $elevated = $this->resolver->has($by, 'role.grant_elevated');

        return Role::orderBy('level')->orderBy('name')->get()
            ->filter(fn (Role $r) => $r->level >= $level && ($r->code !== 'owner' || $elevated))
            ->values();
    }

    /** @return string[] */
    public function roleCodes(CompanyUser $member): array
    {
        return RoleAssignment::where('company_user_id', $member->id)->with('role')->get()->pluck('role.code')->filter()->unique()->values()->all();
    }

    private function grantableRoles(CompanyUser $by, array $codes)
    {
        $codes = array_values(array_unique(array_filter($codes)));
        if ($codes === []) {
            throw ValidationException::withMessages(['roles' => __('Choose at least one role.')]);
        }

        $roles = Role::whereIn('code', $codes)->get();
        if ($roles->count() !== count($codes)) {
            throw ValidationException::withMessages(['roles' => __('Choose roles from the list.')]);
        }
        $roles->each(fn (Role $r) => $this->assertGrantable($by, $r));

        return $roles;
    }

    private function assertGrantable(CompanyUser $by, Role $role): void
    {
        if (! $this->grantable($by)->contains('id', $role->id)) {
            throw ValidationException::withMessages(['roles' => __('You cannot give or take away the :role role.', ['role' => $role->name])]);
        }
    }

    private function assertCanManage(CompanyUser $by, CompanyUser $member): void
    {
        if (! $this->canManage($by, $member)) {
            throw ValidationException::withMessages(['member' => __('You cannot change someone with a higher role than yours.')]);
        }
    }

    /** Inside a transaction: refuse the change if no active Owner would be left. */
    private function assertOwnerRemains(): void
    {
        $owner = $this->role('owner');

        $left = RoleAssignment::where('role_id', $owner->id)
            ->whereHas('member', fn ($q) => $q->where('status', 'active'))
            ->count();

        if ($left === 0) {
            throw ValidationException::withMessages(['roles' => __('The company must keep at least one active Owner.')]);
        }
    }

    private function role(string $code): Role
    {
        return Role::where('code', $code)->firstOrFail();
    }
}
