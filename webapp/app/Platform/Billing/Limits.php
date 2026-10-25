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
        $bytes = $this->bytes($company);

        return [
            'trucks' => DB::table('trucks')->where('company_id', $company->id)->where('status', '!=', 'sold')->count(),
            'users' => DB::table('company_users')->where('company_id', $company->id)->whereIn('status', ['active', 'invited'])->count(),
            'storage_mb' => round($bytes / 1048576, 1),
        ];
    }

    /**
     * Refuses an office upload that would go past the plan's storage. The
     * crew's photos from the road are never refused (they are the proof);
     * the dashboard warns the owner instead (storageAlert).
     */
    public function assertStorage(Company $company, int $bytes): void
    {
        $max = $this->storageMb($company);
        if ($max !== null && ($this->bytes($company) + $bytes) / 1048576 > $max) {
            throw ValidationException::withMessages(['files' => __('The plan’s storage (:mb MB) is full. Remove old files or move to a bigger plan to add more.', ['mb' => number_format($max)])]);
        }
    }

    /** The share of the plan's storage used, when it is 90 % or more; null otherwise or without a limit. */
    public function storageAlert(Company $company): ?int
    {
        $max = $this->storageMb($company);
        if (! $max) {
            return null;
        }
        $share = (int) floor($this->bytes($company) / 1048576 / $max * 100);

        return $share >= 90 ? $share : null;
    }

    /** Bytes kept: documents' files and the crew's photos. */
    private function bytes(Company $company): int
    {
        return (int) DB::table('document_files')->where('company_id', $company->id)->sum('bytes') + (int) DB::table('event_photos')->where('company_id', $company->id)->sum('bytes');
    }

    private function storageMb(Company $company): ?int
    {
        return $company->plan_id ? Plan::whereKey($company->plan_id)->value('storage_mb') : null;
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
