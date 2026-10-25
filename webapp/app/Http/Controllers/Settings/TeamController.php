<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\CompanyUser;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Auth\Identifier;
use App\Platform\Authorization\Models\RoleAssignment;
use App\Platform\Team\TeamService;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Settings → Team: who can sign in to this company, with which roles.
 * The rules (nobody gives more power than they hold, the last Owner stays)
 * live in TeamService; this class moves between screens.
 */
class TeamController extends Controller
{
    public function __construct(private CompanyContext $context, private TeamService $team, private AuditRecorder $audit) {}

    public function index(Request $request): View
    {
        $me      = $this->context->member();
        $members = CompanyUser::with('account')
            ->where('company_id', $this->context->id())
            ->when($request->query('show') !== 'left', fn ($q) => $q->where('status', '!=', 'left'))
            ->get()
            ->sortBy(fn ($m) => [$m->status === 'active' ? 0 : 1, mb_strtolower($m->label)])
            ->values();

        $roles = RoleAssignment::with('role')->whereIn('company_user_id', $members->pluck('id'))->get()->groupBy('company_user_id');

        return view('settings.team', [
            'members'   => $members,
            'roles'     => $roles,
            'me'        => $me,
            'grantable' => $this->team->grantable($me),
            'canManage' => fn (CompanyUser $m) => $this->team->canManage($me, $m),
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'    => ['required', 'string', 'min:2', 'max:150'],
            'email'   => ['required', 'email:filter', 'max:255'],
            'title'   => ['nullable', 'string', 'max:80'],
            'roles'   => ['required', 'array', 'min:1', 'max:5'],
            'roles.*' => ['string', 'max:40'],
        ]);

        $this->team->invite($this->context->member(), trim($data['name']), Identifier::normalise($data['email']), $data['roles'], $data['title'] ?? null);

        return back()->with('status', __('Invitation sent to :email.', ['email' => $data['email']]));
    }

    public function edit(CompanyUser $member): View
    {
        $this->own($member);
        $me = $this->context->member();

        return view('settings.team-member', [
            'member'    => $member->load('account'),
            'held'      => $this->team->roleCodes($member),
            'grantable' => $this->team->grantable($me),
            'canManage' => $this->team->canManage($me, $member),
            'isMe'      => $me->id === $member->id,
        ]);
    }

    public function update(Request $request, CompanyUser $member): RedirectResponse
    {
        $this->own($member);
        $data = $request->validate([
            'title'   => ['nullable', 'string', 'max:80'],
            'roles'   => ['nullable', 'array', 'max:5'],
            'roles.*' => ['string', 'max:40'],
        ]);

        $me = $this->context->member();

        if ($request->user()->can('user.edit') && $this->team->canManage($me, $member)) {
            $member->forceFill(['title' => $data['title'] ?? null])->save();
        }

        if ($request->user()->can('role.grant')) {
            $this->team->setRoles($me, $member, $data['roles'] ?? []);
        }

        return redirect()->route('settings.team')->with('status', __(':name saved.', ['name' => $member->label]));
    }

    public function suspend(CompanyUser $member): RedirectResponse
    {
        $this->own($member);
        $this->team->suspend($this->context->member(), $member);

        return back()->with('status', __(':name can no longer sign in to this company.', ['name' => $member->label]));
    }

    public function reactivate(CompanyUser $member): RedirectResponse
    {
        $this->own($member);
        $this->team->reactivate($this->context->member(), $member);

        return back()->with('status', __(':name can sign in again.', ['name' => $member->label]));
    }

    public function remove(CompanyUser $member): RedirectResponse
    {
        $this->own($member);
        $this->team->remove($this->context->member(), $member);

        return redirect()->route('settings.team')->with('status', __(':name has left the team. Their past work keeps their name.', ['name' => $member->label]));
    }

    public function resend(CompanyUser $member): RedirectResponse
    {
        $this->own($member);
        abort_unless($member->status === 'invited', 404);
        $this->team->sendInvitation($member);

        return back()->with('status', __('Invitation sent again.'));
    }

    /** Lift a sign-in lock (15 min, 1 hour or the hard lock) for a member. */
    public function unlock(CompanyUser $member): RedirectResponse
    {
        $this->own($member);
        $account = $member->account;
        $account->unlock();
        $this->audit->record('account.unlocked', "Unlocked sign-in for {$account->name}", 'account', (string) $account->id, $account->name, severity: 'notable');

        return back()->with('status', __(':name can sign in again.', ['name' => $account->name]));
    }

    /** Members are not company-scoped rows, so check the company by hand. */
    private function own(CompanyUser $member): void
    {
        abort_unless($member->company_id === $this->context->id(), 404);
    }
}
