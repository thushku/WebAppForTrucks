<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Authorization\Models\Role;
use App\Platform\Authorization\Models\RolePermission;
use App\Platform\Authorization\PermissionCatalogue;
use App\Platform\Authorization\PermissionResolver;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Settings → Roles: what each role may do, as a grid of permissions grouped
 * by area. The Owner role always holds everything and is not edited here.
 *
 * Nobody can put into a role a permission they do not hold themselves, and
 * elevated permissions (bank details, voids, Owner role…) need
 * `role.grant_elevated` as well.
 */
class RoleController extends Controller
{
    public function __construct(private CompanyContext $context, private PermissionResolver $resolver, private AuditRecorder $audit) {}

    public function index(): View
    {
        return view('settings.roles', [
            'roles'  => Role::withCount('assignments')->with('permissions')->orderBy('level')->orderBy('name')->get(),
            'groups' => self::groups(),
        ]);
    }

    public function edit(Role $role): View
    {
        $mine = $this->resolver->permissionsFor($this->context->member());

        return view('settings.role', [
            'role'     => $role->load('permissions'),
            'granted'  => $role->code === 'owner' ? PermissionCatalogue::codes() : $role->permissions->where('allow', true)->pluck('permission_code')->all(),
            'groups'   => self::groups(),
            'mine'     => $mine,
            'editable' => $this->mayEdit($role),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_unless($this->mayEdit($role), 403);

        $data = $request->validate([
            'name'          => ['required', 'string', 'min:2', 'max:60'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalogue::codes())],
        ]);

        $wanted = array_values(array_unique($data['permissions'] ?? []));
        $before = $role->permissions()->where('allow', true)->pluck('permission_code')->all();
        $this->assertMayChange(array_merge(array_diff($wanted, $before), array_diff($before, $wanted)));

        DB::transaction(function () use ($role, $data, $wanted, $before) {
            $role->update(['name' => trim($data['name'])]);
            $role->permissions()->whereIn('permission_code', array_diff($before, $wanted))->delete();
            foreach (array_diff($wanted, $before) as $code) {
                $role->permissions()->updateOrCreate(['permission_code' => $code], ['allow' => true]);
            }
        });

        $this->resolver->forget();
        $added   = array_values(array_diff($wanted, $before));
        $removed = array_values(array_diff($before, $wanted));
        if ($added || $removed) {
            $this->audit->record('role.changed', "Changed the {$role->name} role", 'role', (string) $role->id, $role->name,
                changes: ['added' => $added, 'removed' => $removed], severity: 'notable');
        }

        return redirect()->route('settings.roles')->with('status', __('Role saved.'));
    }

    /** A new role, starting as a copy of an existing one. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'copy' => ['required', 'integer'],
        ]);

        $source = Role::with('permissions')->findOrFail($data['copy']);
        abort_if($source->code === 'owner', 422, __('Copy another role: the Owner role holds everything.'));
        $codes = $source->permissions->where('allow', true)->pluck('permission_code')->all();
        $this->assertMayChange($codes);

        $role = DB::transaction(function () use ($data, $source, $codes) {
            $base = Str::slug($data['name'], '_') ?: 'role';
            $code = $base;
            for ($i = 2; Role::where('code', $code)->exists(); $i++) {
                $code = $base . '_' . $i;
            }

            $role = Role::create(['code' => Str::limit($code, 40, ''), 'name' => trim($data['name']), 'level' => max(1, $source->level), 'is_system' => false]);
            foreach ($codes as $permission) {
                $role->permissions()->create(['permission_code' => $permission, 'allow' => true]);
            }

            return $role;
        });

        return redirect()->route('settings.roles.edit', $role)->with('status', __('Role created from :role. Adjust what it may do.', ['role' => $source->name]));
    }

    /** @return array<string, array<string, array{0: string, 1: bool}>> area => code => [description, elevated] */
    public static function groups(): array
    {
        $groups = [];
        foreach (PermissionCatalogue::PERMISSIONS as $code => $spec) {
            $groups[self::area($code)][$code] = $spec;
        }

        return $groups;
    }

    private static function area(string $code): string
    {
        return match (Str::before($code, '.')) {
            'dashboard', 'map' => 'Everyday',
            'trip', 'event'    => 'Trips',
            'truck', 'document', 'complaint' => 'Fleet',
            'people', 'leave'  => 'People',
            'party'            => 'Parties',
            'lr', 'invoice', 'hire' => 'Invoices & LR',
            'payment', 'ledger', 'statement', 'accounts' => 'Money',
            'payroll'          => 'Payroll',
            'report'           => 'Reports',
            'settings'         => 'Settings',
            'user', 'role', 'security', 'audit', 'device' => 'Team & security',
            default            => 'Account',
        };
    }

    /** Not the Owner role, and not a role more senior than your own. */
    private function mayEdit(Role $role): bool
    {
        return $role->code !== 'owner'
            && auth()->user()->can('role.define')
            && $role->level >= $this->resolver->levelFor($this->context->member());
    }

    /** @param string[] $codes permissions being added or taken away */
    private function assertMayChange(array $codes): void
    {
        $member   = $this->context->member();
        $mine     = $this->resolver->permissionsFor($member);
        $elevated = in_array('role.grant_elevated', $mine, true);

        foreach ($codes as $code) {
            if (! in_array($code, $mine, true) || (PermissionCatalogue::isElevated($code) && ! $elevated)) {
                throw ValidationException::withMessages(['permissions' => __('You cannot give or take away “:permission”, because you do not hold it yourself.', ['permission' => __(PermissionCatalogue::PERMISSIONS[$code][0] ?? $code)])]);
            }
        }
    }
}
