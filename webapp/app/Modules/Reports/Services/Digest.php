<?php

namespace App\Modules\Reports\Services;

use App\Models\CompanyUser;
use App\Modules\Accounts\Models\Voucher;
use App\Modules\Documents\Services\Compliance;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\Receipt;
use App\Modules\Reports\Models\DigestDelivery;
use App\Modules\Road\Models\RoadEvent;
use App\Modules\Road\Services\EventTypes;
use App\Modules\Trips\Models\Trip;
use App\Platform\Authorization\PermissionResolver;
use App\Platform\Notifications\NotificationSettings;
use App\Platform\Notifications\Notifier;
use App\Platform\Tenancy\CompanyContext;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The morning summary (M32): what happened yesterday (or last week) — trips
 * started and ended, money billed, received and paid out, spending on the
 * road — and what waits: entries to approve, trips to settle, LRs to bill,
 * overdue bills by client, papers running out, the month's profit so far.
 *
 * Sent at 07:00 company time by email (and to the inbox and phone, so an
 * owner who signs in by mobile only still gets it) to each person who chose
 * it under My notifications — owners get the daily one unless they switch it
 * off. Only people who see the whole company get it (no branch limit), and
 * each summary goes once (`digest_deliveries`).
 */
class Digest
{
    public const HOUR = 7;

    public function __construct(private CompanyContext $context, private Dashboard $dashboard, private Compliance $compliance) {}

    /**
     * The summary of [$from, $to) in company time.
     *
     * @return array<string, mixed>
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $tz = $this->context->company()->timezone ?: 'UTC';
        [$fromUtc, $toUtc] = [$from->setTimezone('UTC'), $to->setTimezone('UTC')];
        [$firstDay, $lastDay] = [$from->toDateString(), $to->subDay()->toDateString()];

        $ended = Trip::whereBetween('ended_at', [$fromUtc, $toUtc])->get(['id', 'start_odometer', 'end_odometer']);
        $billed = Invoice::whereNotNull('finalized_at')->whereBetween('finalized_at', [$fromUtc, $toUtc])->where('doc_type', '!=', 'credit_note')->where('status', '!=', 'void');
        $received = Receipt::where('status', 'posted')->whereBetween('receipt_date', [$firstDay, $lastDay]);
        $paid = Voucher::where('voucher_type', 'payment')->where('status', '!=', 'cancelled')->whereBetween('voucher_date', [$firstDay, $lastDay]);
        $spent = RoadEvent::whereNotNull('amount')->where('type', '!=', 'money_received')->whereNotIn('status', ['rejected', 'cancelled'])
            ->whereBetween('event_at', [$fromUtc, $toUtc])->get(['type', 'amount']);
        $today = $to->toDateString();
        $overdue = Invoice::with('party:id,name')->whereIn('status', Invoice::OPEN_STATUSES)->where('doc_type', '!=', 'credit_note')->where('balance_due', '>', 0)->where('due_date', '<', $today)
            ->get(['id', 'party_id', 'balance_due', 'due_date'])->groupBy('party_id')
            ->map(fn ($bills) => ['party' => $bills->first()->party?->name, 'amount' => round($bills->sum(fn ($b) => (float) $b->balance_due), 2), 'bills' => $bills->count(),
                'days' => (int) CarbonImmutable::parse($bills->min('due_date'))->diffInDays($to)])
            ->sortByDesc('amount')->values();
        $counts = $this->dashboard->counts();
        $queues = $this->dashboard->queues();
        // Truck EMIs overdue or due in the coming week (M35).
        $emis = app(\App\Modules\Fleet\Services\TruckLoans::class)->due($to, 7);
        $papers = $this->compliance->counts();

        return [
            'from' => $from, 'to' => $to->subDay(), 'days' => (int) $from->diffInDays($to),
            'trips' => [
                'started' => Trip::whereBetween('started_at', [$fromUtc, $toUtc])->count(),
                'ended' => $ended->count(),
                'km' => (int) $ended->sum(fn ($t) => $t->start_odometer && $t->end_odometer > $t->start_odometer ? $t->end_odometer - $t->start_odometer : 0),
                'running' => $counts['trips']['running'], 'planned' => $counts['trips']['planned'],
            ],
            'money' => [
                'billed' => round((float) (clone $billed)->sum('total'), 2), 'bills' => (clone $billed)->count(),
                'received' => round((float) (clone $received)->sum('amount'), 2), 'receipts' => (clone $received)->count(),
                'paid' => round((float) (clone $paid)->sum('total'), 2), 'payments' => (clone $paid)->count(),
                'spent' => round($spent->sum(fn ($e) => (float) $e->amount), 2),
                'spent_by' => $spent->groupBy(fn ($e) => EventTypes::label($e->type))->map(fn ($s) => round($s->sum(fn ($e) => (float) $e->amount), 2))->sortDesc()->take(4)->all(),
            ],
            'waiting' => [
                'approvals' => array_sum($counts['approvals']), 'to_settle' => $counts['trips']['to_settle'],
                'pod_pending' => $queues['pod_pending'], 'ready_to_bill' => $queues['ready_to_bill'],
                'overdue' => $queues['overdue'], 'overdue_amount' => $queues['overdue_amount'],
                'emis' => $emis->count(), 'emis_amount' => round($emis->sum('amount'), 2), 'emis_overdue' => $emis->where('overdue', true)->count(),
            ],
            'overdue_clients' => $overdue->take(5)->all(),
            'papers' => ['expired' => $papers['expired'], 'week' => $papers['d7'], 'missing' => $papers['missing']],
            'profit' => $this->dashboard->profit(),
            'trucks' => $counts['trucks'],
            'tz' => $tz,
        ];
    }

    /** One line for the subject and the inbox: the day's headline numbers. */
    public function headline(array $d, string $period): string
    {
        $when = $period === 'weekly' ? __('Last week') : $d['from']->translatedFormat('l j M');

        return $when . ': ' . collect([
            trans_choice(':count trip ended|:count trips ended', $d['trips']['ended']),
            __(':amount received', ['amount' => Money::company($d['money']['received'])]),
            $d['waiting']['overdue_amount'] > 0 ? __(':amount overdue', ['amount' => Money::company($d['waiting']['overdue_amount'])]) : null,
        ])->filter()->join(' · ');
    }

    /**
     * Send this morning's summaries in the current company: the daily one,
     * and on Mondays the weekly one. Returns how many people got one.
     */
    public function sendDue(CarbonImmutable $now, bool $force = false): int
    {
        $company = $this->context->company();
        $local = $now->setTimezone($company->timezone ?: 'UTC');
        if (! $force && $local->hour !== self::HOUR) {
            return 0;
        }
        $today = $local->startOfDay();
        $periods = ['daily' => [$today->subDay(), $today]];
        if ($local->isMonday()) {
            $periods['weekly'] = [$today->subDays(7), $today];
        }

        $sent = 0;
        $settings = app(NotificationSettings::class);
        $resolver = app(PermissionResolver::class);
        $members = CompanyUser::with('account')->where('company_id', $company->id)->where('status', 'active')->get();
        foreach ($periods as $period => [$from, $to]) {
            $wanted = $members->filter(fn (CompanyUser $m) => $settings->of($m)['digest'] === $period && $resolver->has($m, 'dashboard.view') && $resolver->branchScope($m) === null);
            if ($wanted->isEmpty()) {
                continue;
            }
            // Each reader's own language (M36): the summary is made once per language.
            foreach ($wanted->groupBy(fn (CompanyUser $m) => $m->account?->locale ?: 'en') as $locale => $readers) {
                $sent += \App\Support\Locales::using($locale, function () use ($readers, $period, $from, $to) {
                    $summary = $this->build($from, $to);

                    return $readers->sum(fn (CompanyUser $member) => (int) $this->deliver($member, $period, $from, $summary));
                });
            }
        }

        return $sent;
    }

    /** One person's summary, once: by email when they have one, and to their inbox. */
    public function deliver(CompanyUser $member, string $period, CarbonImmutable $from, array $summary): bool
    {
        $key = ['account_id' => $member->account_id, 'period' => $period, 'period_key' => $from->toDateString()];
        if (DigestDelivery::where($key)->exists()) {
            return false;
        }
        $row = DigestDelivery::create($key + ['status' => 'sent', 'sent_at' => now()]);
        $headline = $this->headline($summary, $period);
        $company = $this->context->company();
        $error = null;
        if ($member->account?->email) {
            try {
                $html = view('emails.digest', ['d' => $summary, 'company' => $company, 'name' => $member->account->name, 'period' => $period, 'headline' => $headline])->render();
                Mail::html($html, fn ($m) => $m->to($member->account->email, $member->account->name)->subject($company->name . ' — ' . $headline));
            } catch (\Throwable $e) {
                $error = mb_substr($e->getMessage(), 0, 500);
                Log::warning('Morning summary email failed', ['company' => $company->id, 'account' => $member->account_id, 'error' => $error]);
            }
        }
        app(Notifier::class)->tell([$member->account_id], 'digest.ready', $headline, $period === 'weekly' ? __('Your summary of last week') : __('Your summary of yesterday'), route('dashboard'),
            collapse: 'digest.' . $period);
        $row->forceFill(['status' => $error ? 'failed' : 'sent', 'error' => $error])->save();

        return $error === null;
    }
}
