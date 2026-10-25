<?php

namespace App\Modules\Fleet\Services;

use App\Modules\Fleet\Models\Complaint;
use App\Modules\Fleet\Models\ComplaintUpdate;
use App\Modules\Fleet\Models\ServiceJob;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Settings\Services\DocumentNumbering;
use App\Platform\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Complaints and service jobs, each change in one transaction:
 *
 *  · a complaint gets its number when raised and keeps a trail of every note
 *    and status change (who, when);
 *  · a service job's totals are always the sum of its lines; marking it done
 *    moves the truck's odometer forward and resets the schedule it served.
 */
class FleetService
{
    public function __construct(private DocumentNumbering $numbering, private AuditRecorder $audit) {}

    public function raiseComplaint(Truck $truck, array $data): Complaint
    {
        return DB::transaction(function () use ($truck, $data) {
            $complaint = Complaint::create($data + [
                'truck_id'  => $truck->id,
                'number'    => $this->numbering->assign('complaint'),
                'raised_by' => auth()->id(),
                'status'    => 'open',
            ]);

            ComplaintUpdate::create(['complaint_id' => $complaint->id, 'account_id' => auth()->id(), 'note' => $data['description'] ?? null, 'status_to' => 'open']);

            if (! empty($data['odometer_km'])) {
                $this->moveOdometer($truck, (int) $data['odometer_km']);
            }

            return $complaint;
        });
    }

    /** A note, a new status, or both. */
    public function updateComplaint(Complaint $complaint, ?string $status, ?string $note, ?int $assignedTo = null, ?int $vendorId = null): void
    {
        if ($status !== null && $status !== $complaint->status && ! in_array($status, Complaint::NEXT[$complaint->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => __('A complaint that is :from cannot become :to.', [
                'from' => __(Complaint::STATUSES[$complaint->status]), 'to' => __(Complaint::STATUSES[$status] ?? $status),
            ])]);
        }
        if (($status === null || $status === $complaint->status) && blank($note) && $assignedTo === $complaint->assigned_to && $vendorId === $complaint->vendor_party_id) {
            throw ValidationException::withMessages(['note' => __('Write a note or choose a new status.')]);
        }

        DB::transaction(function () use ($complaint, $status, $note, $assignedTo, $vendorId) {
            $from    = $complaint->status;
            $changes = ['assigned_to' => $assignedTo, 'vendor_party_id' => $vendorId];

            if ($status !== null && $status !== $from) {
                $changes['status']    = $status;
                $changes['solved_at'] = in_array($status, ['solved', 'closed'], true) ? ($complaint->solved_at ?? now()) : null;
                $changes['closed_at'] = $status === 'closed' ? now() : null;
            }
            $complaint->forceFill($changes)->save();

            ComplaintUpdate::create([
                'complaint_id' => $complaint->id,
                'account_id'   => auth()->id(),
                'note'         => filled($note) ? $note : null,
                'status_from'  => isset($changes['status']) ? $from : null,
                'status_to'    => $changes['status'] ?? null,
            ]);
        });
    }

    /**
     * Save a job and its lines; totals are worked out here, never trusted
     * from the form.
     *
     * @param array<int, array{kind: string, description: string, qty: float|string, rate: float|string}> $items
     */
    public function saveJob(?ServiceJob $job, Truck $truck, array $fields, array $items): ServiceJob
    {
        if ($job && $job->status !== 'open') {
            throw ValidationException::withMessages(['job' => __('A finished or cancelled job cannot be changed.')]);
        }

        return DB::transaction(function () use ($job, $truck, $fields, $items) {
            $parts = $labour = 0.0;
            $rows  = [];
            foreach ($items as $item) {
                $amount = round((float) $item['qty'] * (float) $item['rate'], 2);
                $item['kind'] === 'labour' ? $labour += $amount : $parts += $amount;
                $rows[] = ['kind' => $item['kind'], 'description' => $item['description'], 'qty' => $item['qty'], 'rate' => $item['rate'], 'amount' => $amount];
            }

            $job ??= new ServiceJob(['truck_id' => $truck->id, 'number' => $this->numbering->assign('service_job', isset($fields['job_date']) ? \Carbon\CarbonImmutable::parse($fields['job_date'] . ' 12:00') : null), 'created_by' => auth()->id()]);
            $job->fill($fields)->forceFill([
                'parts_total'  => round($parts, 2),
                'labour_total' => round($labour, 2),
                'total'        => round($parts + $labour, 2),
            ])->save();

            $job->items()->delete();
            foreach ($rows as $row) {
                $job->items()->create($row);
            }

            return $job;
        });
    }

    public function completeJob(ServiceJob $job): void
    {
        if ($job->status !== 'open') {
            throw ValidationException::withMessages(['job' => __('This job is already :status.', ['status' => __(ServiceJob::STATUSES[$job->status])])]);
        }

        $job->loadMissing(['truck', 'schedule']);

        DB::transaction(function () use ($job) {
            $job->forceFill(['status' => 'done', 'completed_at' => now()])->save();

            if ($job->odometer_km) {
                $this->moveOdometer($job->truck, $job->odometer_km);
            }
            if ($job->schedule) {
                $job->schedule->forceFill(['last_done_km' => $job->odometer_km ?? $job->schedule->last_done_km, 'last_done_on' => $job->job_date])->save();
            }
            if ($job->truck->status === 'workshop') {
                $job->truck->forceFill(['status' => 'idle'])->save();
            }
        });

        $this->audit->record('service_job.completed', "Service job {$job->number} done on {$job->truck->reg_no}", 'service_job', (string) $job->id, $job->number);
    }

    /** The odometer only moves forward; a lower reading is kept on the record that brought it, not on the truck. */
    public function moveOdometer(Truck $truck, int $km): void
    {
        if ($truck->odometer_km === null || $km > $truck->odometer_km) {
            $truck->forceFill(['odometer_km' => $km, 'odometer_at' => now()])->save();
        }
    }
}
