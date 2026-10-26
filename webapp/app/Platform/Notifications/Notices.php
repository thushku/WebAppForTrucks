<?php

namespace App\Platform\Notifications;

use App\Modules\Payroll\Models\Payslip;
use App\Modules\Road\Models\RoadEvent;
use App\Modules\Road\Services\Approvals;
use App\Modules\Road\Services\EventTypes;
use App\Modules\Trips\Models\Trip;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * What the business tells whom, in what words (M20). The services call one
 * method at the moment something happens; each picks the people (the trip's
 * crew, whoever recorded an entry, those who approve) and the words, and
 * leaves the inbox and the push to the Notifier. The phone apps open what
 * `data` names: a trip by its uuid, an entry by its uuid, the approvals
 * list, the crew's money page.
 */
class Notices
{
    public function __construct(private Notifier $notifier, private Approvals $approvals) {}

    /** A trip sent to its crew. */
    public function tripSent(Trip $trip): void
    {
        $trip->loadMissing(['crew', 'from', 'to', 'truck']);
        $this->say($this->notifier->accountsOf($trip->crew->pluck('person_id')), fn ($ids) => $this->notifier->tell($ids, 'trip.sent',
            __('New trip :number', ['number' => $trip->trip_number]),
            collect([($trip->from?->name ?? '') . ' → ' . ($trip->to?->name ?? ''), $trip->truck?->reg_no, $trip->trip_date?->format('d M')])->filter()->join(' · '),
            null, ['type' => 'trip', 'trip' => $trip->uuid]));
    }

    /** A trip the crew had is called off. */
    public function tripCancelled(Trip $trip, string $reason): void
    {
        $trip->loadMissing('crew');
        $this->say($this->notifier->accountsOf($trip->crew->pluck('person_id')), fn ($ids) => $this->notifier->tell($ids, 'trip.cancelled',
            __('Trip :number is cancelled', ['number' => $trip->label]), $reason, null, ['type' => 'trip', 'trip' => $trip->uuid]));
    }

    /** The crew's entry was just saved (or changed): the other driver approves it, or the office checks it. */
    public function entryRecorded(RoadEvent $event, bool $new = true): void
    {
        $event->loadMissing(['trip.crew', 'person:id,name']);
        if ($event->status === 'waiting_2nd') {
            $others = $event->trip->crew->filter(fn ($c) => in_array($c->role, ['driver', 'co_driver'], true) && $c->person_id !== $event->person_id)->pluck('person_id');
            $this->say($this->notifier->accountsOf($others), fn ($ids) => $this->notifier->tell($ids, 'entry.second',
                __(':name asks you to approve', ['name' => $event->person?->name]), $this->what($event) . ' · ' . $event->trip->label,
                null, ['type' => 'entry', 'entry' => $event->uuid, 'trip' => $event->trip->uuid]));
        } elseif (in_array($event->status, ['waiting_check', 'disputed'], true)) {
            $this->approvalsWaiting($event);
        }
        if ($new && $event->type === 'incident') {
            $this->say($this->notifier->accountsWith('trip.view'), fn ($ids) => $this->notifier->tell($ids, 'road.incident',
                __('Incident on :trip', ['trip' => $event->trip->label]),
                collect([$event->data['kind'] ?? null, $event->data['description'] ?? null, $event->person?->name])->filter()->join(' — '),
                route('trips.show', $event->trip, false), ['type' => 'trip', 'trip' => $event->trip->uuid]));
        }
    }

    /** The office queried or rejected an entry: its maker hears why. */
    public function entryDecided(RoadEvent $event, string $decision, ?string $note): void
    {
        // The "waiting" notice follows the count quietly (no new push); with nothing left it is done with, for everyone.
        $counts = $this->approvals->counts();
        $waiting = ($counts['waiting_check'] ?? 0) + ($counts['disputed'] ?? 0);
        UserNotification::where('kind', 'approvals.waiting')->whereNull('read_at')
            ->update($waiting === 0 ? ['read_at' => now()] : ['title' => trans_choice(':count entry waits for your approval|:count entries wait for your approval', $waiting)]);
        if (! in_array($decision, ['query', 'reject'], true)) {
            return;
        }
        $event->loadMissing('trip');
        $this->say($this->notifier->accountsOf([$event->person_id]), fn ($ids) => $this->notifier->tell($ids, $decision === 'query' ? 'entry.queried' : 'entry.rejected',
            $decision === 'query' ? __('Question on your :what', ['what' => $this->what($event)]) : __('Your :what was not accepted', ['what' => $this->what($event)]),
            trim((string) $note) . ' · ' . $event->trip->label, null, ['type' => 'entry', 'entry' => $event->uuid, 'trip' => $event->trip->uuid]));
    }

    /**
     * One notice for those who approve, kept up to date: how many entries
     * wait, and the latest. Pushed again only after a quiet half hour.
     */
    public function approvalsWaiting(?RoadEvent $latest = null): void
    {
        $counts = $this->approvals->counts();
        $waiting = ($counts['waiting_check'] ?? 0) + ($counts['disputed'] ?? 0);
        if ($waiting === 0) {
            return;
        }
        $latest?->loadMissing(['trip', 'person:id,name']);
        $this->say($this->notifier->accountsWith('event.verify'), fn ($ids) => $this->notifier->tell($ids, 'approvals.waiting',
            trans_choice(':count entry waits for your approval|:count entries wait for your approval', $waiting),
            $latest ? ($latest->status === 'disputed' ? __('Disputed: ') : '') . ($latest->person?->name ?? '') . ': ' . $this->what($latest) . ' · ' . $latest->trip->label : null,
            route('approvals.index', [], false), ['type' => 'approvals'], 'approvals'));
    }

    /** A payslip paid: the person hears how much, how and the reference. */
    public function payslipPaid(Payslip $slip): void
    {
        $slip->loadMissing(['run', 'person']);
        $this->say($this->notifier->accountsOf([$slip->person_id]), fn ($ids) => $this->notifier->tell($ids, 'pay.paid',
            __('Your pay for :period is paid', ['period' => $slip->run->periodLabel()]),
            collect([Money::company($slip->paid_amount), __(Payslip::PAY_MODES[$slip->paid_mode] ?? $slip->paid_mode), $slip->paid_reference])->filter()->join(' · '),
            null, ['type' => 'money']));
    }

    /**
     * The day's papers that reached a reminder step, for those who look after
     * papers (alongside the email).
     *
     * @param Collection<int, string> $names "KL-07-AB-1234 — Insurance, 7 days"
     */
    public function papersDue(Collection $names): void
    {
        if ($names->isEmpty()) {
            return;
        }
        $this->say($this->notifier->accountsWith('document.manage'), fn ($ids) => $this->notifier->tell($ids, 'papers.due',
            trans_choice(':count paper needs attention|:count papers need attention', $names->count()),
            $names->take(3)->join('; ') . ($names->count() > 3 ? ' …' : ''), route('documents.index', [], false), ['type' => 'papers'], 'papers'));
    }

    /** A client answered a quotation on its link (M24): whoever makes bills hears it. */
    public function quotationAnswered(\App\Modules\Invoicing\Models\Quotation $quotation): void
    {
        $quotation->loadMissing('party:id,name');
        $accepted = $quotation->status === 'accepted';
        $this->say($this->notifier->accountsWith('invoice.create'), fn ($ids) => $this->notifier->tell($ids, $accepted ? 'quotation.accepted' : 'quotation.declined',
            $accepted ? __(':party accepted quotation :number', ['party' => $quotation->party->name, 'number' => $quotation->number])
                : __(':party declined quotation :number', ['party' => $quotation->party->name, 'number' => $quotation->number]),
            collect([$quotation->answered_by_name, $quotation->decision_note])->filter()->join(' — '),
            route('quotations.show', $quotation, false), ['type' => 'quotation', 'quotation' => (string) $quotation->id]));
    }

    /** A crew member asked for leave (M40): those who approve leave hear it. */
    public function leaveAsked(\App\Modules\People\Models\LeaveRequest $leave): void
    {
        $leave->loadMissing('person:id,name');
        $this->say($this->notifier->accountsWith('leave.approve'), fn ($ids) => $this->notifier->tell($ids, 'leave.asked',
            trans_choice(':name asks for :count day of leave|:name asks for :count days of leave', $leave->days, ['name' => $leave->person->name]),
            collect([app(\App\Modules\People\Services\Leave::class)->typeName($leave->leave_type), $this->span($leave), $leave->reason])->filter()->join(' · '),
            route('people.leave', [], false), ['type' => 'leave', 'leave' => $leave->uuid], 'leave.asked'));
    }

    /** Leave approved, turned down or recorded by the office: the person hears it. */
    public function leaveDecided(\App\Modules\People\Models\LeaveRequest $leave): void
    {
        $this->say($this->notifier->accountsOf([$leave->person_id]), fn ($ids) => $this->notifier->tell($ids, 'leave.decided',
            $leave->status === 'approved' ? __('Your leave :span is approved', ['span' => $this->span($leave)]) : __('Your leave :span is not approved', ['span' => $this->span($leave)]),
            collect([app(\App\Modules\People\Services\Leave::class)->typeName($leave->leave_type), $leave->decision_note])->filter()->join(' · '),
            null, ['type' => 'leave', 'leave' => $leave->uuid]));
    }

    /** "28 Oct – 30 Oct", or one day. */
    private function span(\App\Modules\People\Models\LeaveRequest $leave): string
    {
        return $leave->from_on->isSameDay($leave->to_on) ? $leave->from_on->format('d M') : $leave->from_on->format('d M') . ' – ' . $leave->to_on->format('d M');
    }

    /** Tell each reader in their own language (M36): the words are made once per language. */
    private function say(iterable $accountIds, \Closure $tell): void
    {
        $locales = \App\Models\Account::whereIn('id', collect($accountIds)->all())->pluck('locale', 'id');
        foreach ($locales->groupBy(fn ($locale) => $locale ?: 'en', true) as $locale => $ids) {
            \App\Support\Locales::using($locale, fn () => $tell($ids->keys()->all()));
        }
    }

    /** "Diesel ₹5,000" — the entry's kind and amount. */
    private function what(RoadEvent $event): string
    {
        return EventTypes::label($event->type) . ($event->amount !== null && (float) $event->amount ? ' ' . Money::company($event->amount) : '');
    }
}
