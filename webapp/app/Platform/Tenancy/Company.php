<?php

namespace App\Platform\Tenancy;

use App\Models\Account;
use App\Models\CompanyUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A customer of the platform: one transport business ("tenant").
 *
 * Not itself company-scoped (it IS the scope). Screens reach it through
 * CompanyContext; the console reaches every company directly.
 */
class Company extends Model
{
    protected $guarded = ['id', 'uuid'];

    /** The database defaults, so a freshly created company reads the same as a loaded one. */
    protected $attributes = [
        'country'        => 'IN',
        'currency'       => 'INR',
        'timezone'       => 'Asia/Kolkata',
        'fy_start_month' => 4,
        'status'         => 'trial',
        'mfa_policy'     => 'optional',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at'         => 'datetime',
            'deletion_requested_at' => 'datetime',
            'read_only_since'       => 'datetime',
            'fy_start_month'        => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Company $company) {
            $company->uuid ??= (string) Str::uuid7();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'owner_account_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(CompanyUser::class);
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereIn('status', ['trial', 'active', 'lapsed']);
    }

    public function isUsable(): bool
    {
        return in_array($this->status, ['trial', 'active', 'lapsed'], true);
    }

    public function onTrial(): bool
    {
        return $this->status === 'trial' && $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function trialDaysLeft(): int
    {
        if (! $this->trial_ends_at) {
            return 0;
        }

        // Day maths on dates in the company's own time zone, not a fraction
        // of a UTC day (a Clinic Ledger lesson: 6.77 days was truncated to 6).
        $today = now($this->timezone)->startOfDay();
        $end   = $this->trial_ends_at->copy()->setTimezone($this->timezone)->startOfDay();

        return max(0, (int) $today->diffInDays($end, false));
    }

    /** A lapsed trial or subscription reads everything and writes nothing. */
    public function isReadOnly(): bool
    {
        return $this->status === 'lapsed' || $this->read_only_since !== null;
    }
}
