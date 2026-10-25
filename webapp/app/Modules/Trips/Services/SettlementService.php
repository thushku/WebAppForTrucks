<?php

namespace App\Modules\Trips\Services;

use App\Modules\People\Models\Person;
use App\Modules\Road\Models\RoadEvent;
use App\Modules\Settings\Services\CompanySettings;
use App\Modules\Trips\Models\Trip;
use App\Modules\Trips\Models\TripAdvance;
use App\Modules\Trips\Models\TripCrew;
use App\Modules\Trips\Models\TripSettlement;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Settling the crew's trip cash, then closing the trip (blueprint 03 §3, 8).
 *
 * Each crew member has their own small cash account for the trip:
 *
 *   received = advances from the office + money collected on the road
 *              (from the party or anyone else; money from the office is the
 *              advances the office records)
 *   spent    = their APPROVED entries paid from their own pocket (cash, UPI)
 *   balance  = received − spent   (> 0: they return it, < 0: we pay them)
 *
 * Fuel-card, FASTag and credit spending is the company's money and is not in
 * it. A rejected entry is not counted. Bata is worked out here (the company
 * rule, editable) but goes to the personal ledger, not into this cash.
 *
 * Settling waits until every entry is decided: nothing waiting for the second
 * driver or the office, no open query, no dispute (D6). When every crew
 * member is settled the trip is "settled"; closing it locks the crew's
 * entries for good, while the office can still add its own (D8). Only the
 * owner reopens a closed trip, with a reason; its settlements are voided
 * (kept) and done again.
 */
class SettlementService
{
    public function __construct(
        private CompanySettings $settings,
        private CompanyContext $context,
        private AuditRecorder $audit,
    ) {}

    /** @return array<string, int> open entries by status, e.g. ['waiting_2nd' => 1] (empty: nothing in the way) */
    public function blockers(Trip $trip): array
    {
        return RoadEvent::where('trip_id', $trip->id)->whereIn('status', RoadEvent::OPEN)
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n)->all();
    }

    /**
     * Each crew member's account: what they received and spent, the balance,
     * the suggested bata and their settlement (when done).
     *
     * @return Collection<int, array<string, mixed>> by person id
     */
    public function accounts(Trip $trip): Collection
    {
        $trip->loadMissing('crew.person');
        $advances = TripAdvance::where('trip_id', $trip->id)->selectRaw('person_id, sum(amount) as total')->groupBy('person_id')->pluck('total', 'person_id');
        $events   = RoadEvent::where('trip_id', $trip->id)->where('status', 'approved')->get(['person_id', 'type', 'amount', 'from_own_pocket', 'data']);
        $settled  = TripSettlement::where('trip_id', $trip->id)->current()->get()->keyBy('person_id');
        $open     = RoadEvent::where('trip_id', $trip->id)->whereIn('status', RoadEvent::OPEN)->whereNotNull('amount')
            ->selectRaw('person_id, sum(amount) as total')->groupBy('person_id')->pluck('total', 'person_id');

        return $trip->crew->mapWithKeys(function (TripCrew $member) use ($trip, $advances, $events, $settled, $open) {
            $mine      = $events->where('person_id', $member->person_id);
            $collected = $mine->where('type', 'money_received')->filter(fn ($e) => in_array($e->data['from'] ?? 'other', ['party', 'other'], true))->sum(fn ($e) => (float) $e->amount);
            $spent     = $mine->where('type', '!=', 'money_received')->where('from_own_pocket', true)->sum(fn ($e) => (float) $e->amount);
            $given     = (float) ($advances[$member->person_id] ?? 0);
            [$days, $bata] = $this->bata($trip, $member);

            return [$member->person_id => [
                'person'     => $member->person,
                'role'       => $member->role,
                'advances'   => round($given, 2),
                'collected'  => round($collected, 2),
                'received'   => round($given + $collected, 2),
                'spent'      => round($spent, 2),
                'balance'    => round($given + $collected - $spent, 2),
                'waiting'    => round((float) ($open[$member->person_id] ?? 0), 2),
                'bata_days'  => $days,
                'bata'       => $bata,
                'settlement' => $settled[$member->person_id] ?? null,
            ]];
        });
    }

    /**
     * The company's bata rule for this crew member: per day on the trip
     * (calendar days in the company's time zone, start and end day included)
     * or once per trip. Per km and route tables are typed by the manager.
     *
     * @return array{0: ?int, 1: float} [days, amount]
     */
    public function bata(Trip $trip, TripCrew $member): array
    {
        $rate = (float) $this->settings->get($member->role === 'helper' ? 'bata_helper_amount' : 'bata_driver_amount');
        if (! $trip->started_at || $rate <= 0) {
            return [null, 0.0];
        }

        $tz   = $this->context->company()->timezone ?: 'UTC';
        $from = CarbonImmutable::instance($trip->started_at)->setTimezone($tz)->startOfDay();
        $to   = CarbonImmutable::instance($trip->ended_at ?? now())->setTimezone($tz)->startOfDay();
        $days = (int) $from->diffInDays($to) + 1;

        return match ($this->settings->get('bata_rule')) {
            'per_day'  => [$days, round($days * $rate, 2)],
            'per_trip' => [$days, round($rate, 2)],
            default    => [$days, 0.0],
        };
    }

    /** @param array{mode: string, reference?: ?string, note?: ?string, bata_amount?: mixed} $data */
    public function settle(Trip $trip, Person $person, array $data): TripSettlement
    {
        if ($trip->status !== 'ended') {
            throw ValidationException::withMessages(['trip' => $trip->status === 'running' ? __('End the trip first.') : __('This trip is not waiting for settlement.')]);
        }
        if ($blockers = $this->blockers($trip)) {
            throw ValidationException::withMessages(['trip' => $this->blockerText($blockers)]);
        }

        return DB::transaction(function () use ($trip, $person, $data) {
            Trip::whereKey($trip->id)->lockForUpdate()->first();
            $account = $this->accounts($trip)[$person->id] ?? throw ValidationException::withMessages(['person' => __('This person is not on the trip’s crew.')]);
            if ($account['settlement']) {
                throw ValidationException::withMessages(['person' => __(':name is already settled.', ['name' => $person->name])]);
            }

            $mode = $data['mode'];
            if ($account['balance'] == 0.0) {
                $mode = 'nil';
            } elseif ($mode === 'nil') {
                throw ValidationException::withMessages(['mode' => __('Say how the balance of :amount is settled.', ['amount' => number_format(abs($account['balance']), 2)])]);
            } elseif ($mode === 'cash_returned' && $account['balance'] < 0) {
                throw ValidationException::withMessages(['mode' => __('The company owes :name; nothing is returned.', ['name' => $person->name])]);
            } elseif ($mode === 'paid_to_crew' && $account['balance'] > 0) {
                throw ValidationException::withMessages(['mode' => __(':name holds the company’s money; it is returned, not paid.', ['name' => $person->name])]);
            }

            $settlement = TripSettlement::create([
                'trip_id' => $trip->id, 'person_id' => $person->id,
                'advances' => $account['advances'], 'collected' => $account['collected'], 'spent' => $account['spent'], 'balance' => $account['balance'],
                'mode' => $mode, 'reference' => $data['reference'] ?? null, 'note' => $data['note'] ?? null,
                'bata_days' => $account['bata_days'],
                'bata_amount' => isset($data['bata_amount']) && $data['bata_amount'] !== '' ? round((float) $data['bata_amount'], 2) : $account['bata'],
                'settled_by' => auth()->id(), 'settled_at' => now(),
            ]);

            $this->audit->record('trip.crew_settled', "Settled {$person->name} on {$trip->label}: balance {$account['balance']} (" . TripSettlement::MODES[$mode] . ')',
                'trip', (string) $trip->id, $trip->label, severity: 'notable');

            // Everybody settled: the trip is.
            $crew = $trip->crew->pluck('person_id');
            if (TripSettlement::where('trip_id', $trip->id)->current()->whereIn('person_id', $crew)->count() === $crew->count()) {
                $this->markSettled($trip);
            }

            return $settlement;
        });
    }

    /** A trip without crew of ours (a hired truck): nothing to settle in cash. */
    public function settleWithoutCrew(Trip $trip): void
    {
        if ($trip->status !== 'ended') {
            throw ValidationException::withMessages(['trip' => __('This trip is not waiting for settlement.')]);
        }
        if ($trip->crew()->exists()) {
            throw ValidationException::withMessages(['trip' => __('Settle each crew member.')]);
        }
        if ($blockers = $this->blockers($trip)) {
            throw ValidationException::withMessages(['trip' => $this->blockerText($blockers)]);
        }
        DB::transaction(fn () => $this->markSettled($trip));
    }

    public function close(Trip $trip): void
    {
        if ($trip->status !== 'settled') {
            throw ValidationException::withMessages(['trip' => __('Settle the trip first.')]);
        }
        $trip->forceFill(['status' => 'closed', 'closed_at' => now(), 'closed_by' => auth()->id()])->save();
        $this->audit->record('trip.closed', "Closed {$trip->label}", 'trip', (string) $trip->id, $trip->label);
    }

    /** Owner only (trip.reopen): back to "ended", settlements voided and kept. */
    public function reopen(Trip $trip, string $reason): void
    {
        if (! in_array($trip->status, ['settled', 'closed'], true)) {
            throw ValidationException::withMessages(['trip' => __('Only a settled or closed trip can be reopened.')]);
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['reason' => __('Write why the trip is reopened (at least 10 letters).')]);
        }

        DB::transaction(function () use ($trip, $reason) {
            TripSettlement::where('trip_id', $trip->id)->current()->update(['voided_at' => now(), 'void_reason' => mb_substr($reason, 0, 255), 'updated_at' => now()]);
            $trip->forceFill(['status' => 'ended', 'settled_at' => null, 'settled_by' => null, 'closed_at' => null, 'closed_by' => null, 'reopen_count' => $trip->reopen_count + 1])->save();
        });
        $this->audit->record('trip.reopened', "Reopened {$trip->label}", 'trip', (string) $trip->id, $trip->label, reason: $reason, severity: 'sensitive');
    }

    /** "1 entry is waiting for the 2nd driver · 2 are waiting for your check" */
    public function blockerText(array $blockers): string
    {
        $parts = [];
        foreach (['waiting_2nd' => ':count entry waits for the second approval|:count entries wait for the second approval',
            'waiting_check' => ':count entry waits for the office’s check|:count entries wait for the office’s check',
            'queried' => ':count query is still open|:count queries are still open',
            'disputed' => ':count disputed entry needs a decision|:count disputed entries need a decision'] as $status => $text) {
            if (! empty($blockers[$status])) {
                $parts[] = trans_choice($text, $blockers[$status]);
            }
        }

        return __('Not yet: :what.', ['what' => implode(' · ', $parts)]);
    }

    private function markSettled(Trip $trip): void
    {
        $trip->forceFill(['status' => 'settled', 'settled_at' => now(), 'settled_by' => auth()->id()])->save();
        $this->audit->record('trip.settled', "Settled {$trip->label}", 'trip', (string) $trip->id, $trip->label);
    }
}
