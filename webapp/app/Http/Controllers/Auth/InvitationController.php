<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveCompany;
use App\Models\CompanyUser;
use App\Platform\Auth\Models\PasswordToken;
use App\Platform\Auth\PasswordTokens;
use App\Platform\Team\TeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The link in an invitation email.
 *
 * Someone new chooses their name and password here (opening the link proves
 * the address, so it counts as verified). Someone who already has a login
 * signs in first and then joins with one click: the link alone never joins
 * an existing login to a company.
 */
class InvitationController extends Controller
{
    public function __construct(private PasswordTokens $tokens, private TeamService $team) {}

    public function show(Request $request, string $token): View|RedirectResponse
    {
        [$row, $member] = $this->resolve($token);

        if (! $member) {
            return redirect()->route('login')->with('error', __('This invitation link has expired or was already used. Ask for a new one.'));
        }

        $account = $member->account;
        $user    = Auth::guard('web')->user();

        if ($account->password_hash !== null && (! $user || $user->id !== $account->id)) {
            if ($user) {
                return view('auth.invitation', ['member' => $member, 'token' => $token, 'mode' => 'other_user']);
            }
            $request->session()->put('url.intended', route('invite.show', $token));

            return view('auth.invitation', ['member' => $member, 'token' => $token, 'mode' => 'sign_in']);
        }

        return view('auth.invitation', [
            'member' => $member,
            'token'  => $token,
            'mode'   => $account->password_hash === null ? 'new' : 'join',
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        [, $member] = $this->resolve($token);
        if (! $member) {
            return redirect()->route('login')->with('error', __('This invitation link has expired or was already used. Ask for a new one.'));
        }

        $account = $member->account;
        $isNew   = $account->password_hash === null;
        $user    = Auth::guard('web')->user();

        if ($isNew) {
            $data = $request->validate([
                'name'     => ['required', 'string', 'min:2', 'max:150'],
                'password' => ['required', 'string', 'max:1024', 'confirmed', Password::defaults()],
            ]);
        } elseif (! $user || $user->id !== $account->id) {
            return redirect()->route('invite.show', $token);
        }

        if (! $this->tokens->consume($token, 'invite')) {
            return redirect()->route('login')->with('error', __('This invitation link has expired or was already used. Ask for a new one.'));
        }

        $this->team->accept($member, $isNew ? trim($data['name']) : null, $isNew ? Hash::make($data['password']) : null);

        if (! $user) {
            Auth::guard('web')->login($account->fresh());
            $request->session()->regenerate();
            $request->session()->put('splash', true);
        }
        $request->session()->put(ResolveCompany::SESSION_KEY, $member->company_id);

        return redirect()->route('dashboard')->with('status', __('Welcome to :company.', ['company' => $member->company->name]));
    }

    /** @return array{0: ?PasswordToken, 1: ?CompanyUser} */
    private function resolve(string $token): array
    {
        if (strlen($token) > 128) {
            return [null, null];
        }

        $row    = $this->tokens->peek($token, 'invite');
        $member = $row && $row->subject_type === 'company_user'
            ? CompanyUser::with(['account', 'company'])->where('id', $row->subject_id)->where('status', 'invited')->first()
            : null;

        return [$row, $member?->company?->isUsable() ? $member : null];
    }
}
