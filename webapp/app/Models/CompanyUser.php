<?php

namespace App\Models;

use App\Platform\Audit\Audited;
use App\Platform\Authorization\Models\RoleAssignment;
use App\Platform\Tenancy\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person's membership of one company: "Ravi, at Sharma Roadlines".
 *
 * Roles and permissions hang off this row, never off the account, because
 * the same person may be an owner in one company and a driver in another.
 */
class CompanyUser extends Model
{
    use Audited;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'joined_at'    => 'datetime',
            'last_seen_at' => 'datetime',
            'notification_settings' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** The name the audit log and screens use for this row. */
    public function getLabelAttribute(): string
    {
        return (string) ($this->account?->name ?? 'Member #' . $this->id);
    }
}
