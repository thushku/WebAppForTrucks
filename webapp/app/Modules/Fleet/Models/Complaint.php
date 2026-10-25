<?php

namespace App\Modules\Fleet\Models;

use App\Models\Account;
use App\Modules\Parties\Models\Party;
use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A problem with a truck, raised by the crew (phone) or the office. */
class Complaint extends Model
{
    use Audited, BelongsToCompany;

    public const STATUSES = [
        'open'          => 'Open',
        'in_progress'   => 'In progress',
        'waiting_parts' => 'Waiting for parts',
        'solved'        => 'Solved',
        'closed'        => 'Closed',
    ];

    public const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent — truck cannot run'];

    /** Which status may follow which (a closed complaint can be opened again). */
    public const NEXT = [
        'open'          => ['in_progress', 'waiting_parts', 'solved', 'closed'],
        'in_progress'   => ['waiting_parts', 'solved', 'open'],
        'waiting_parts' => ['in_progress', 'solved'],
        'solved'        => ['closed', 'open'],
        'closed'        => ['open'],
    ];

    protected $guarded = ['id', 'company_id', 'uuid'];

    protected $attributes = ['priority' => 'normal', 'status' => 'open'];

    protected static function booted(): void
    {
        static::creating(function (Complaint $c) {
            $c->uuid ??= (string) Str::uuid7();
        });
    }

    protected function casts(): array
    {
        return ['solved_at' => 'datetime', 'closed_at' => 'datetime', 'odometer_km' => 'integer'];
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'raised_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'assigned_to');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'vendor_party_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ComplaintUpdate::class)->orderBy('created_at')->orderBy('id');
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, ['solved', 'closed'], true);
    }

    public function getLabelAttribute(): string
    {
        return "{$this->number} {$this->title}";
    }
}
