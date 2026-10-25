<?php

namespace App\Modules\Road\Services;

use App\Models\Account;
use App\Modules\Road\Models\EventApproval;
use App\Modules\Road\Models\RoadEvent;
use App\Modules\Settings\Services\CompanySettings;
use App\Platform\Audit\AuditRecorder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The office checks the crew's entries (blueprint 03 §6):
 *
 *   waiting for check ──approve──► approved
 *                    ├─query────► query open ──crew edits or answers──► waiting for check
 *                    └─reject───► rejected (not counted; the reason goes to the crew)
 *
 * A disputed payment without receipt (the second driver said no) is decided
 * here too. On a trip with one driver the manager gives the second approval
 * (A4) — and checks it in the same step. With a second driver on the trip,
 * the office waits for them (D6): the trip cannot be settled until they answer.
 *
 * Every decision is kept in event_approvals; rejecting, and approving a
 * payment without receipt, are also in the audit log.
 */
class Approvals
{
    public function __construct(private CompanySettings $settings, private AuditRecorder $audit) {}

    /** @return string[] what the office may do with this entry now: approve, query, reject */
    public function actions(RoadEvent $event): array
    {
        $event->loadMissing('trip');
        if (in_array($event->trip->status, ['settled', 'closed', 'cancelled'], true)) {
            return [];
        }

        return match ($event->status) {
            'waiting_check', 'disputed' => ['approve', 'query', 'reject'],
            'queried'     => ['approve', 'reject'],
            'waiting_2nd' => $this->officeGivesSecond($event) ? ['approve', 'reject'] : [],
            default       => [],
        };
    }

    /** A one-driver trip, and the rule says the manager is the second approver (A4). */
    public function officeGivesSecond(RoadEvent $event): bool
    {
        if ($event->status !== 'waiting_2nd' || $this->settings->get('single_driver_approver') !== 'manager') {
            return false;
        }
        $event->loadMissing('trip.crew');

        return ! $event->trip->crew->contains(fn ($c) => in_array($c->role, ['driver', 'co_driver'], true) && $c->person_id !== $event->person_id);
    }

    public function decide(Account $by, RoadEvent $event, string $decision, ?string $note = null): void
    {
        $note = trim((string) $note) ?: null;
        if (! in_array($decision, $this->actions($event), true)) {
            throw ValidationException::withMessages(['decision' => $event->status === 'waiting_2nd'
                ? __('This entry is waiting for the other driver’s approval.')
                : __('This entry cannot be :what now.', ['what' => __(['approve' => 'approved', 'query' => 'queried', 'reject' => 'rejected'][$decision] ?? $decision)])]);
        }
        if (in_array($decision, ['query', 'reject'], true) && mb_strlen((string) $note) < 3) {
            throw ValidationException::withMessages(['note' => $decision === 'query' ? __('Write the question for the crew.') : __('Write why it is rejected.')]);
        }

        DB::transaction(function () use ($by, $event, $decision, $note) {
            $fields = ['decision_note' => $note ? mb_substr($note, 0, 255) : null];
            if ($event->status === 'waiting_2nd') {
                $fields += ['second_by_account' => $by->id, 'second_at' => now()];
            }
            $fields += match ($decision) {
                'approve' => ['status' => 'approved', 'verified_by' => $by->id, 'verified_at' => now()],
                'reject'  => ['status' => 'rejected', 'verified_by' => $by->id, 'verified_at' => now()],
                'query'   => ['status' => 'queried'],
            };
            $event->forceFill($fields)->save();

            EventApproval::create(['road_event_id' => $event->id, 'step' => 'manager', 'decision' => $decision, 'account_id' => $by->id, 'note' => $note]);
        });

        if ($decision === 'reject' || $event->type === 'no_receipt') {
            $what = EventTypes::label($event->type) . ($event->amount !== null ? ' ' . $event->amount : '');
            $this->audit->record("road.{$decision}", ucfirst($decision === 'approve' ? 'approved' : ($decision === 'reject' ? 'rejected' : 'queried')) . " {$what} on {$event->trip->label}",
                'trip', (string) $event->trip_id, $event->trip->label, reason: $note, severity: $decision === 'query' ? null : 'notable');
        }
    }

    /**
     * Approve many at once — only entries waiting for check with no flags:
     * a flagged entry is looked at one by one.
     *
     * @param Collection<int, RoadEvent> $events
     * @return int how many were approved
     */
    public function approveMany(Account $by, Collection $events): int
    {
        $done = 0;
        foreach ($events as $event) {
            if ($event->status === 'waiting_check' && empty($event->flags) && in_array('approve', $this->actions($event), true)) {
                $this->decide($by, $event, 'approve');
                $done++;
            }
        }

        return $done;
    }

    /** Entries waiting for the office, newest trips' first, for the inbox and the owner app. */
    public function inbox(?string $status = null)
    {
        return RoadEvent::with(['trip:id,uuid,trip_number,status', 'trip.crew:id,trip_id,person_id,role', 'person:id,name', 'photos:id,road_event_id,slot,duplicate_of'])
            ->whereIn('status', $status ? [$status] : RoadEvent::OPEN)
            ->whereHas('trip', fn ($q) => $q->whereNotIn('status', ['settled', 'closed', 'cancelled']))
            ->orderBy('trusted_at');
    }

    /** How many entries wait, per status (the inbox tabs and the menu badge). */
    public function counts(): array
    {
        return RoadEvent::whereIn('status', RoadEvent::OPEN)
            ->whereHas('trip', fn ($q) => $q->whereNotIn('status', ['settled', 'closed', 'cancelled']))
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n)->all();
    }
}
