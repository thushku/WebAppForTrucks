<?php

namespace App\Modules\Road\Models;

use App\Modules\People\Models\Person;
use App\Modules\Trips\Models\Trip;
use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One entry the crew made on the road (blueprint 03 §5): what happened,
 * when (three clocks), where, by whom, and where it stands in the approval
 * flow. Never deleted — cancelled with a reason.
 */
class RoadEvent extends Model
{
    use Audited, BelongsToCompany;

    public const STATUSES = [
        'info'          => 'Recorded',
        'waiting_2nd'   => 'Waiting for 2nd approval',
        'waiting_check' => 'Waiting for check',
        'approved'      => 'Approved',
        'queried'       => 'Query open',
        'rejected'      => 'Rejected',
        'disputed'      => 'Disputed',
        'cancelled'     => 'Cancelled',
    ];

    /** Statuses that still count as money on the trip (settlement waits for the first three). */
    public const OPEN = ['waiting_2nd', 'waiting_check', 'queried', 'disputed'];

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return [
            'data' => 'array', 'flags' => 'array', 'amount' => 'decimal:2', 'from_own_pocket' => 'boolean', 'mock_location' => 'boolean',
            'event_at' => 'datetime', 'recorded_at' => 'datetime', 'trusted_at' => 'datetime', 'gps_time' => 'datetime',
            'second_at' => 'datetime', 'verified_at' => 'datetime', 'cancelled_at' => 'datetime',
            'lat' => 'float', 'lng' => 'float', 'odometer' => 'integer', 'uptime_ms' => 'integer',
            'photos_expected' => 'integer', 'photos_received' => 'integer',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function secondApprover(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'second_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(EventPhoto::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(EventApproval::class)->orderBy('id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Account::class, 'verified_by');
    }

    public function edits(): HasMany
    {
        return $this->hasMany(RoadEventEdit::class)->orderBy('id');
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', '!=', 'cancelled');
    }

    public function isMoney(): bool
    {
        return $this->amount !== null && (float) $this->amount > 0;
    }

    /** Still editable by the person who made it: not yet checked, not cancelled. */
    public function isEditableBy(int $personId): bool
    {
        return $this->person_id === $personId && in_array($this->status, ['info', 'waiting_2nd', 'waiting_check', 'queried', 'disputed'], true);
    }

    public function getLabelAttribute(): string
    {
        return $this->type . ($this->amount ? ' ' . $this->amount : '');
    }
}
