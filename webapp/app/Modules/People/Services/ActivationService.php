<?php

namespace App\Modules\People\Services;

use App\Models\Account;
use App\Models\CompanyUser;
use App\Modules\People\Models\ActivationCode;
use App\Modules\People\Models\Person;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Authorization\RoleProvisioner;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The crew app's first sign-in (blueprint 02: "mobile number + one-time
 * activation code shown to the office in the web app — no SMS cost").
 *
 * Issuing a code makes sure the person has a login: the Account for their
 * mobile number (made now, or the one they already have because they also
 * drive for another company — P7), joined to this company with the app role
 * for their type (driver, helper…). The code is 8 digits, lives 72 hours and
 * replaces any earlier code. The phone exchanges it for a device token in M6.
 */
class ActivationService
{
    public const LIFETIME_HOURS = 72;

    public function __construct(private CompanyContext $context, private RoleProvisioner $roles, private AuditRecorder $audit) {}

    /** @return string the plain code, shown once */
    public function issue(Person $person): string
    {
        if (! $person->mobile) {
            throw ValidationException::withMessages(['mobile' => __('Add the person’s mobile number first: the app signs in with it.')]);
        }
        if ($person->status === 'left') {
            throw ValidationException::withMessages(['status' => __('This person has left. Mark them as working again first.')]);
        }

        $code = (string) random_int(10_000_000, 99_999_999);

        DB::transaction(function () use ($person, $code) {
            $member = $this->ensureLogin($person);

            ActivationCode::where('person_id', $person->id)->whereNull('used_at')->update(['used_at' => now(), 'used_device' => 'replaced']);
            ActivationCode::create([
                'person_id'  => $person->id,
                'code_hash'  => hash('sha256', $person->mobile . '|' . $code),
                'expires_at' => now()->addHours(self::LIFETIME_HOURS),
                'created_by' => auth()->id(),
            ]);

            $person->forceFill(['company_user_id' => $member->id])->save();
        });

        $this->audit->record('person.activation_issued', "Activation code made for {$person->name}", 'person', (string) $person->id, $person->name, severity: 'notable');

        return $code;
    }

    /** The phone's side (used by the API in M6): the person whose code this is, once. */
    public function redeem(string $mobile, string $code, ?string $device = null): ?Person
    {
        return DB::transaction(function () use ($mobile, $code, $device) {
            $row = ActivationCode::withoutGlobalScopes()
                ->where('code_hash', hash('sha256', $mobile . '|' . $code))
                ->lockForUpdate()->first();

            if (! $row || ! $row->isUsable()) {
                return null;
            }
            $row->forceFill(['used_at' => now(), 'used_device' => mb_substr((string) $device, 0, 120)])->save();

            return Person::withoutGlobalScopes()->find($row->person_id);
        });
    }

    /** The Account for the mobile number and an active membership with the app role. */
    private function ensureLogin(Person $person): CompanyUser
    {
        $account = Account::where('mobile', $person->mobile)->lockForUpdate()->first();
        if (! $account) {
            $account = new Account(['name' => $person->name, 'mobile' => $person->mobile]);
            $account->save();
        }
        if (! $account->canLogIn()) {
            throw ValidationException::withMessages(['mobile' => __('The login for this mobile number is suspended.')]);
        }

        $member = CompanyUser::where('company_id', $this->context->id())->where('account_id', $account->id)->lockForUpdate()->first();
        $linked = Person::where('company_user_id', $member?->id ?? 0)->where('id', '!=', $person->id)->first();
        if ($linked) {
            throw ValidationException::withMessages(['mobile' => __('This mobile number already signs in as :name.', ['name' => $linked->name])]);
        }

        if ($member?->status === 'suspended') {
            throw ValidationException::withMessages(['mobile' => __('This person is suspended in Settings → Team. Let them back in there first.')]);
        }

        $member ??= new CompanyUser(['company_id' => $this->context->id(), 'account_id' => $account->id, 'title' => __(Person::TYPES[$person->type] ?? 'Crew')]);
        if ($member->status !== 'active') {
            $member->forceFill(['status' => 'active', 'joined_at' => $member->joined_at ?? now()])->save();
        }

        $this->roles->grant($member, Person::APP_ROLE[$person->type] ?? 'helper');

        return $member;
    }
}
