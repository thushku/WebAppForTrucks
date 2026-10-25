<?php

namespace App\Platform\Data;

use App\Models\Account;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Tenancy\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Deleting a company (blueprint 09 §8): the owner asks, the company keeps
 * working for 72 hours while anyone with the right can cancel, then every
 * row and file of the company is removed — except one encrypted copy of
 * everything, kept for the years tax and company law require, then removed
 * too. The company's row stays (status "deleted") so the console can see it.
 */
class CompanyDeletion
{
    public const WAIT_HOURS = 72;

    public function __construct(private AuditRecorder $audit, private CompanyExport $export) {}

    public function request(Company $company, Account $by, string $reason): void
    {
        if ($company->deletion_requested_at) {
            return;
        }
        DB::transaction(function () use ($company, $by, $reason) {
            $company->forceFill(['deletion_requested_at' => now(), 'deletion_requested_by' => $by->id, 'deletion_reason' => mb_substr($reason, 0, 255)])->save();
            $this->audit->record('company.deletion_requested', "{$by->name} asked to delete {$company->name}; it goes on {$this->dueAt($company)->toDayDateTimeString()} UTC", 'company', (string) $company->id, $company->name,
                reason: $reason, severity: 'sensitive', companyId: $company->id);
        });

        // Everyone who can stop it hears about it.
        foreach ($this->owners($company) as $email) {
            try {
                Mail::raw(__(":name asked to delete :company and all its data. It happens on :when (UTC). To stop it, sign in and open Settings → Your data → Cancel the deletion. Download your data before then if you want a copy.",
                    ['name' => $by->name, 'company' => $company->name, 'when' => $this->dueAt($company)->toDayDateTimeString()]), fn ($m) => $m->to($email)->subject(__(':company will be deleted in 72 hours', ['company' => $company->name])));
            } catch (\Throwable $e) {
                Log::warning('Deletion notice mail failed', ['company' => $company->id, 'error' => $e->getMessage()]);
            }
        }
    }

    public function cancel(Company $company, Account $by): void
    {
        if (! $company->deletion_requested_at || $company->deleted_at) {
            return;
        }
        $company->forceFill(['deletion_requested_at' => null, 'deletion_requested_by' => null, 'deletion_reason' => null])->save();
        $this->audit->record('company.deletion_cancelled', "{$by->name} cancelled the deletion of {$company->name}", 'company', (string) $company->id, $company->name, severity: 'sensitive', companyId: $company->id);
    }

    public function dueAt(Company $company): \Illuminate\Support\Carbon
    {
        return $company->deletion_requested_at->copy()->addHours(self::WAIT_HOURS);
    }

    /**
     * The hourly sweep: companies whose 72 hours have passed are removed;
     * copies kept for the law past their date are removed too.
     *
     * @return array{deleted: int, expired: int}
     */
    public function sweep(): array
    {
        $deleted = 0;
        foreach (Company::whereNotNull('deletion_requested_at')->whereNull('deleted_at')->where('deletion_requested_at', '<=', now()->subHours(self::WAIT_HOURS))->get() as $company) {
            $this->purge($company);
            $deleted++;
        }

        $expired = 0;
        foreach (Company::whereNotNull('retention_path')->where('retention_until', '<', now()->toDateString())->get() as $company) {
            Storage::disk('local')->delete($company->retention_path);
            $company->forceFill(['retention_path' => null])->save();
            $this->audit->record('company.retention_removed', "The law copy of {$company->name} was removed (kept until {$company->retention_until->toDateString()})", 'company', (string) $company->id, $company->name, severity: 'notable');
            $expired++;
        }

        return ['deleted' => $deleted, 'expired' => $expired];
    }

    /** The copy for the law first; only then the data. */
    public function purge(Company $company): void
    {
        $years = (int) config('platform.retention_years', 8);
        $path = "retention/{$company->uuid}.zip";
        $this->export->build($company, $path, $this->password($company));

        $tables = $this->export->tables();
        $tables[] = 'activation_codes';
        $tables[] = 'api_commands';
        $tables[] = 'document_links';
        $tables[] = 'document_counters';
        $tables[] = 'document_sequences';
        $tables[] = 'company_settings';

        DB::transaction(function () use ($company, $tables, $path, $years) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            try {
                DB::table('role_permissions')->whereIn('role_id', DB::table('roles')->where('company_id', $company->id)->pluck('id'))->delete();
                foreach (array_unique($tables) as $table) {
                    if ($table !== 'audit_log' && $table !== 'subscriptions') {
                        DB::table($table)->where('company_id', $company->id)->delete();
                    }
                }
                // The company's history goes with it (it is in the law copy); the console keeps one line.
                DB::table('audit_log')->where('company_id', $company->id)->delete();
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
            $company->forceFill(['status' => 'deleted', 'deleted_at' => now(), 'retention_path' => $path, 'retention_until' => now()->addYears($years)->toDateString(),
                'plan_id' => null, 'logo_path' => null])->save();
        });
        Storage::disk('local')->deleteDirectory("companies/{$company->uuid}");
        $this->audit->record('company.deleted', "{$company->name} and all its data were deleted; an encrypted copy is kept until {$company->retention_until->toDateString()} for the law", 'company', (string) $company->id, $company->name, severity: 'sensitive');
    }

    /** The law copy's password: from the app key and the company, never stored. The console can rebuild it. */
    public function password(Company $company): string
    {
        return hash_hmac('sha256', 'retention:' . $company->uuid, (string) config('app.key'));
    }

    /** @return string[] */
    private function owners(Company $company): array
    {
        return DB::table('company_users as m')->join('accounts as a', 'a.id', '=', 'm.account_id')->where('m.company_id', $company->id)->where('m.status', 'active')
            ->whereNotNull('a.email')->whereIn('m.id', DB::table('role_assignments as ra')->join('roles as r', 'r.id', '=', 'ra.role_id')->where('r.code', 'owner')->where('ra.company_id', $company->id)->pluck('ra.company_user_id'))
            ->pluck('a.email')->unique()->values()->all();
    }
}
