<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CompanyUser;
use App\Modules\People\Models\Person;
use App\Modules\People\Services\ActivationService;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Auth\AuthenticationService;
use App\Platform\Auth\CodeSender;
use App\Platform\Auth\Identifier;
use App\Platform\Auth\LockLadder;
use App\Platform\Auth\Models\OtpChallenge;
use App\Platform\Auth\OtpService;
use App\Platform\Devices\DeviceTokens;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Signing a phone in (blueprint 02 §1, 01 §4.1).
 *
 *  · Crew: mobile number + the 8-digit activation code the office shows
 *    (no SMS cost). One code, one phone.
 *  · Owner and office: mobile number + a one-time code by SMS/WhatsApp
 *    (decision D21; the "log" driver until the provider is set up).
 *
 * Both get a device token (DeviceTokens) and the list of companies the login
 * works for; the app sends X-Company to pick one (P7). A wrong code and an
 * unknown number get the same answer.
 */
class AuthController extends Controller
{
    public function __construct(private DeviceTokens $tokens, private AuditRecorder $audit) {}

    public function activate(Request $request, ActivationService $activation, CompanyContext $context): JsonResponse
    {
        $data = $request->validate([
            'mobile' => ['required', 'string', 'max:20'],
            'code'   => ['required', 'string', 'max:12'],
        ] + $this->deviceRules());

        $code = preg_replace('/\D/', '', $data['code']);
        $key  = 'activate:' . preg_replace('/\D/', '', $data['mobile']);
        if (RateLimiter::tooManyAttempts($key, 8)) {
            return $this->refused('too_many', __('Too many wrong codes. Ask the office for a new code in an hour.'), 429);
        }

        $person = null;
        foreach ($this->mobileCandidates($data['mobile']) as $mobile) {
            if ($person = $activation->redeem($mobile, $code, ($data['platform'] ?? '') . ' ' . ($data['model'] ?? ''))) {
                break;
            }
        }

        $member  = $person?->company_user_id ? CompanyUser::with(['account', 'company'])->find($person->company_user_id) : null;
        $account = $member?->account;
        if (! $person || ! $account || ! $account->canLogIn() || $member->status !== 'active' || ! $member->company?->isUsable()) {
            RateLimiter::hit($key, 3600);

            return $this->refused('bad_code', __('That code is not right, or it has expired. Ask the office for a new one.'), 422);
        }
        RateLimiter::clear($key);

        $issued = $this->tokens->issue($account, $data, 'crew');
        $account->forceFill(['mobile_verified_at' => $account->mobile_verified_at ?? now()])->saveQuietly();
        $context->runAs($member->company, fn () => $this->audit->record('device.activated', "{$person->name} signed in on " . trim(($data['platform'] ?? '') . ' ' . ($data['model'] ?? '')), 'person', (string) $person->id, $person->name, severity: 'notable'));

        return $this->signedIn($account, $issued, $member->company->uuid);
    }

    /** Step one of the owner app's sign-in: send a code to the mobile number. */
    public function requestOtp(Request $request, OtpService $otp, CodeSender $sender): JsonResponse
    {
        $data    = $request->validate(['mobile' => ['required', 'string', 'max:20'], 'channel' => ['nullable', Rule::in(['sms', 'whatsapp'])]]);
        $account = $this->accountFor($data['mobile']);

        if ($account && $account->canLogIn() && ! LockLadder::isLocked($account) && $account->activeMemberships()->exists()) {
            try {
                $issued = $otp->issue($account, 'mobile_login', $data['channel'] ?? 'sms', $request->ip());
                $sender->send($account, $issued['challenge'], $issued['code']);
            } catch (RuntimeException $e) {
                return $this->refused('too_many', $e->getMessage(), 429);
            }
        }

        // The same answer whether or not the number has a login.
        return response()->json(['ok' => true, 'message' => __('If this number has a login, a code is on its way.'), 'expires_in' => OtpService::TTL_SECONDS]);
    }

    public function verifyOtp(Request $request, OtpService $otp, AuthenticationService $auth): JsonResponse
    {
        $data    = $request->validate(['mobile' => ['required', 'string', 'max:20'], 'code' => ['required', 'string', 'max:10']] + $this->deviceRules());
        $account = $this->accountFor($data['mobile']);

        $challenge = $account ? OtpChallenge::where('subject_type', 'account')->where('subject_id', $account->id)->where('purpose', 'mobile_login')
            ->whereNull('consumed_at')->where('expires_at', '>', now())->latest('id')->first() : null;

        if (! $challenge || ! $otp->verify($challenge, $data['code']) || ! $account->canLogIn() || LockLadder::isLocked($account)) {
            if ($account) {
                $auth->recordAttempt($account, $account->mobile, false, 'bad_code', 'otp', $request->ip(), $request->userAgent());
            }

            return $this->refused('bad_code', __('That code is not right, or it has expired.'), 422);
        }

        $auth->succeed($account, null, 'otp', $request->ip(), $request->userAgent());
        $account->forceFill(['mobile_verified_at' => $account->mobile_verified_at ?? now()])->saveQuietly();

        return $this->signedIn($account, $this->tokens->issue($account, $data, 'owner'));
    }

    /** The companies this login works for (the app's company switcher). */
    public function companies(Request $request): JsonResponse
    {
        return response()->json(['companies' => $this->companyList($request->user())]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $issued = $this->tokens->refresh($request);

        return response()->json(['token' => $issued['token'], 'expires_at' => $issued['expires_at']]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->tokens->revoke($request);

        return response()->json(['ok' => true]);
    }

    /**
     * The PIN that unlocks the app and confirms "I am driving now" on a
     * shared phone. Changing it needs the old one.
     */
    public function setPin(Request $request): JsonResponse
    {
        $account = $request->user();
        $data = $request->validate([
            'pin'         => ['required', 'string', 'regex:/^\d{4,6}$/', 'not_in:0000,1111,1234,123456,000000,111111'],
            'current_pin' => [$account->pin_hash ? 'required' : 'nullable', 'string', 'max:6'],
        ], ['pin.regex' => __('The PIN is 4 to 6 digits.'), 'pin.not_in' => __('That PIN is too easy to guess.')]);

        if ($account->pin_hash && ! Hash::check((string) $data['current_pin'], $account->pin_hash)) {
            return $this->refused('bad_pin', __('The current PIN is not right.'), 422);
        }
        $account->forceFill(['pin_hash' => Hash::make($data['pin'])])->save();

        return response()->json(['ok' => true]);
    }

    /** The phone's push token and app version (sent after sign-in and when they change). */
    public function device(Request $request): JsonResponse
    {
        $data = $request->validate(['push_token' => ['nullable', 'string', 'max:500'], 'app_version' => ['nullable', 'string', 'max:20']]);
        $this->tokens->seen($request, array_filter(['push_token' => $data['push_token'] ?? null, 'app_version' => $data['app_version'] ?? null]));

        return response()->json(['ok' => true]);
    }

    // ---- Helpers ---------------------------------------------------------------

    private function deviceRules(): array
    {
        return [
            'device_id'   => ['required', 'string', 'min:8', 'max:100'],
            'platform'    => ['required', Rule::in(['android', 'ios'])],
            'model'       => ['nullable', 'string', 'max:80'],
            'app_version' => ['nullable', 'string', 'max:20'],
        ];
    }

    /** "+919847011111", or the local number tried with each country we work in. */
    private function mobileCandidates(string $mobile): array
    {
        return collect(array_keys(Identifier::COUNTRY_CODES))->map(fn ($c) => Identifier::mobile($mobile, $c))->filter()->unique()->values()->all();
    }

    private function accountFor(string $mobile): ?Account
    {
        $candidates = $this->mobileCandidates($mobile);

        return $candidates ? Account::whereIn('mobile', $candidates)->first() : null;
    }

    private function signedIn(Account $account, array $issued, ?string $company = null): JsonResponse
    {
        $companies = $this->companyList($account);

        return response()->json([
            'token'      => $issued['token'],
            'expires_at' => $issued['expires_at'],
            'account'    => ['name' => $account->name, 'mobile' => $account->mobile, 'locale' => $account->locale, 'has_pin' => $account->pin_hash !== null],
            'companies'  => $companies,
            'company'    => $company ?? (count($companies) === 1 ? $companies[0]['id'] : null),
        ]);
    }

    private function companyList(Account $account): array
    {
        return CompanyUser::with('company')->where('account_id', $account->id)->where('status', 'active')
            ->whereHas('company', fn ($q) => $q->usable())->get()
            ->map(function (CompanyUser $m) {
                $person = app(CompanyContext::class)->runAs($m->company, fn () => Person::where('company_user_id', $m->id)->first(['id', 'name', 'type']));

                return ['id' => $m->company->uuid, 'name' => $m->company->name, 'country' => $m->company->country, 'crew' => $person !== null, 'person_type' => $person?->type];
            })->values()->all();
    }

    private function refused(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => $code], $status);
    }
}
