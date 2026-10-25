<?php

namespace App\Platform\Billing;

use App\Platform\Tenancy\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The plan's limits, checked where things are added (a truck, a team
 * member) and shown on the billing page. A company without a plan (on its
 * trial) has no limits; nothing already there is ever taken away — the
 * limit only stops adding more.
 */
class Limits
{
    /** @return array{trucks: int, users: int, storage_mb: float} */
    public function usage(Company $company): array
    {
        $bytes = (int) DB::table('document_files')->where('company_id', $company->id)->sum('bytes') + (int) DB::table('event_photos')->where('company_id', $company->id)->sum('bytes');

        return [
            'trucks' => DB::table('trucks')->where('company_id', $company->id)->where('status', '!=', 'sold')->count(),
            'users' => DB::table('company_users')->where('company_id', $company->id)->whereIn('status', ['active', 'invited'])->count(),
            'storage_mb' => round($bytes / 1048576, 1),
        ];
    }

    /** Refuses adding one more truck or user beyond the plan, in words the owner can act on. */
    public function assertRoom(Company $company, string $what): void
    {
        $plan = $company->plan_id ? Plan::find($company->plan_id) : null;
        $max = $plan?->{$what === 'trucks' ? 'max_trucks' : 'max_users'};
        if ($max === null) {
            return;
        }
        if ($this->usage($company)[$what] >= $max) {
            throw ValidationException::withMessages([$what === 'trucks' ? 'reg_no' : 'email' => $what === 'trucks'
                ? __('The :plan plan has room for :n trucks. Move to a bigger plan to add more.', ['plan' => $plan->name, 'n' => $max])
                : __('The :plan plan has room for :n users. Move to a bigger plan to invite more.', ['plan' => $plan->name, 'n' => $max])]);
        }
    }
}
