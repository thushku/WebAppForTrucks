<?php

namespace App\Modules\People\Models;

use App\Models\Account;
use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Days off asked for by a crew member or recorded by the office (M40): waiting, approved, turned down or cancelled. */
class LeaveRequest extends Model
{
    use Audited, BelongsToCompany;

    public const STATUSES = ['waiting' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Turned down', 'cancelled' => 'Cancelled'];

    /** Waiting and approved requests hold their days. */
    public const HOLDING = ['waiting', 'approved'];

    protected $guarded = ['id', 'company_id'];

    protected $attributes = ['status' => 'waiting', 'asked_via' => 'office'];

    protected function casts(): array
    {
        return ['from_on' => 'date', 'to_on' => 'date', 'days' => 'integer', 'decided_at' => 'datetime'];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'decided_by');
    }

    public function getLabelAttribute(): string
    {
        return $this->leave_type . ' ' . $this->from_on?->format('d M') . '–' . $this->to_on?->format('d M Y');
    }
}
