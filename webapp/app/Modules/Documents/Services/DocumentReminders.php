<?php

namespace App\Modules\Documents\Services;

use App\Models\CompanyUser;
use App\Modules\Documents\Models\Document;
use App\Platform\Authorization\PermissionResolver;
use App\Platform\Tenancy\Company;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Facades\Mail;

/**
 * The daily expiry reminders (blueprint 09 §6.2): a reminder at each step
 * before expiry (the type's reminder days, then 15, 7, 1 and the day itself)
 * and every week after it has expired, until it is renewed or snoozed.
 *
 * One email a day per company, to everyone who may manage documents, lists
 * what reached a new step. Push and WhatsApp join when those channels exist.
 */
class DocumentReminders
{
    public function __construct(private CompanyContext $context, private Compliance $compliance, private PermissionResolver $permissions) {}

    /**
     * The step a document is at today, or null when it needs no reminder
     * (no expiry, the type has reminders off, or it is still far away).
     */
    public static function step(?int $daysLeft, int $remindDays): ?int
    {
        if ($daysLeft === null || $remindDays <= 0) {
            return null;
        }
        if ($daysLeft < 0) {
            // 0 on the day and the first week after, then −7, −14…
            return -7 * intdiv(-$daysLeft, 7);
        }

        $steps = array_values(array_filter([$remindDays, 15, 7, 1, 0], fn ($s) => $s <= $remindDays));
        $step  = null;
        foreach ($steps as $s) {
            if ($daysLeft <= $s) {
                $step = $s;
            }
        }

        return $step;
    }

    /** @return int documents reminded */
    public function runFor(Company $company): int
    {
        return $this->context->runAs($company, function () use ($company) {
            $today = now($company->timezone)->startOfDay();
            $types = $this->compliance->types();
            $due   = [];

            $docs = Document::whereNotNull('expires_on')
                ->where('expires_on', '<=', $today->copy()->addDays(120)->toDateString())
                ->where(fn ($q) => $q->whereNull('snoozed_until')->orWhere('snoozed_until', '<', $today->toDateString()))
                ->get();

            foreach ($docs as $doc) {
                $days = (int) $today->diffInDays($doc->expires_on, false);
                $step = self::step($days, (int) ($types[$doc->document_type]['remind_days'] ?? 30));
                if ($step !== null && ($doc->last_reminder_days === null || $step < $doc->last_reminder_days)) {
                    $due[] = [$doc, $step, $days];
                }
            }

            if ($due === []) {
                return 0;
            }

            $labels = $this->compliance->ownerLabels(array_column($due, 0));
            $lines  = array_map(fn ($row) => sprintf('• %s — %s %s: %s',
                $labels[$row[0]->owner_type . ':' . $row[0]->owner_id] ?? '?',
                $this->compliance->typeName($row[0]->document_type),
                $row[0]->number ?? '',
                $row[2] < 0 ? __('expired :n days ago', ['n' => -$row[2]]) : ($row[2] === 0 ? __('expires today') : __('expires in :n days (:date)', ['n' => $row[2], 'date' => $row[0]->expires_on->format('d M Y')])),
            ), $due);

            $recipients = CompanyUser::with('account')->where('company_id', $company->id)->where('status', 'active')->get()
                ->filter(fn (CompanyUser $m) => $m->account?->email && $this->permissions->has($m, 'document.manage'))
                ->map(fn ($m) => $m->account->email)->unique()->values();

            if ($recipients->isNotEmpty()) {
                Mail::raw(
                    __("Documents that need attention at :company:\n\n:lines\n\nRenew them in Documents, or snooze a reminder with a reason.", ['company' => $company->name, 'lines' => implode("\n", $lines)]),
                    fn ($m) => $m->to($recipients->all())->subject(__(':n documents need attention — :company', ['n' => count($due), 'company' => $company->name])),
                );
            }

            foreach ($due as [$doc, $step]) {
                $doc->forceFill(['last_reminder_days' => $step, 'reminded_at' => now()])->saveQuietly();
            }

            return count($due);
        });
    }
}
