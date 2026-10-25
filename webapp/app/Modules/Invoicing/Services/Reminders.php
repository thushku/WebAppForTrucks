<?php

namespace App\Modules\Invoicing\Services;

use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Settings\Services\CompanySettings;
use App\Platform\Tenancy\Company;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Overdue reminders by email (blueprint 10): every day, for each bill past
 * its due date by one of the company's reminder days (3, 15, 30 by default),
 * one email to the client with the reminder wording. A step is sent once; a
 * bill found late (reminders just switched on) gets one reminder, not a pile.
 * Clients marked "no reminders", read-only companies and credit notes are
 * left out.
 */
class Reminders
{
    public function __construct(private CompanyContext $context, private Messages $messages, private DocumentMailer $mailer) {}

    /** @return array{sent: int, failed: int} */
    public function run(Company $company): array
    {
        return $this->context->runAs($company, function () use ($company) {
            $settings = app(CompanySettings::class);
            if ($company->isReadOnly() || ! $settings->get('reminders_enabled')) {
                return ['sent' => 0, 'failed' => 0];
            }
            $steps = $this->steps((string) $settings->get('reminder_days'));
            if ($steps === []) {
                return ['sent' => 0, 'failed' => 0];
            }
            // Calendar days on the company's clock: a due date has no time of day.
            $today = CarbonImmutable::parse(CarbonImmutable::now($company->timezone ?: 'UTC')->toDateString());
            $done = ['sent' => 0, 'failed' => 0];

            $bills = Invoice::with(['party.contacts'])->whereIn('status', Invoice::OPEN_STATUSES)->where('doc_type', '!=', 'credit_note')
                ->where('balance_due', '>', 0)->where('due_date', '<', $today->toDateString())->whereNotNull('public_token')
                ->whereHas('party', fn ($q) => $q->where('send_reminders', true))->get();
            $logged = DB::table('reminder_log')->whereIn('invoice_id', $bills->pluck('id'))->get(['invoice_id', 'step_days'])->groupBy('invoice_id')->map(fn ($r) => $r->pluck('step_days')->all());

            foreach ($bills as $bill) {
                $days = (int) CarbonImmutable::parse($bill->due_date->toDateString())->diffInDays($today);
                $reached = array_values(array_filter($steps, fn ($s) => $s <= $days));
                $already = $logged[$bill->id] ?? [];
                $open = array_values(array_diff($reached, $already));
                // Only the latest step reached, and only if no later step was sent before.
                if ($open === [] || max($open) < max($already ?: [0])) {
                    continue;
                }
                $step = max($open);
                $email = $bill->party->email ?: $bill->party->contacts->firstWhere('is_primary', true)?->email ?: $bill->party->contacts->whereNotNull('email')->first()?->email;
                $status = 'failed';
                $error = null;
                if (! $email) {
                    $error = 'No email for the client';
                } else {
                    $message = $this->messages->render('reminder', 'email', $bill);
                    $status = $this->mailer->send($bill, [$email], [], (string) $message['subject'], $message['body'], null) ? 'sent' : 'failed';
                    $error = $status === 'sent' ? null : 'The mail server refused it';
                }
                DB::table('reminder_log')->insert(array_map(fn ($s) => ['company_id' => $company->id, 'invoice_id' => $bill->id, 'step_days' => $s, 'recipient' => $email,
                    'status' => $s === $step ? $status : 'skipped', 'error' => $s === $step ? $error : null, 'sent_at' => now()], $open));
                $done[$status === 'sent' ? 'sent' : 'failed']++;
            }

            return $done;
        });
    }

    /** "3, 15,30" → [3, 15, 30]; nonsense left out. */
    public function steps(string $days): array
    {
        $steps = array_values(array_unique(array_filter(array_map('intval', preg_split('/[\s,;]+/', $days) ?: []), fn ($d) => $d >= 1 && $d <= 365)));
        sort($steps);

        return $steps;
    }
}
