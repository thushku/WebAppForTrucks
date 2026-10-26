<?php

namespace App\Modules\Fleet\Models;

use App\Modules\Parties\Models\Party;
use App\Modules\Settings\Models\Branch;
use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use App\Platform\Tenancy\HasBranch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One of the company's trucks (own, attached or leased). Hired market trucks
 * are not here — they belong to a supplier (SupplierVehicle).
 */
class Truck extends Model
{
    use Audited, BelongsToCompany, HasBranch;

    /** A truck or person with no branch works for every branch (M22). */
    public static function branchSharedWhenUnassigned(): bool
    {
        return true;
    }

    public const STATUSES = [
        'idle'           => 'Available',
        'on_trip'        => 'On trip',
        'workshop'       => 'In workshop',
        'breakdown'      => 'Breakdown',
        'out_of_service' => 'Out of service',
        'sold'           => 'Sold',
    ];

    public const OWNERSHIP = ['own' => 'Own', 'attached' => 'Attached (owner’s truck, our trips)', 'leased' => 'Leased'];

    public const FUELS = ['diesel' => 'Diesel', 'cng' => 'CNG', 'lng' => 'LNG', 'electric' => 'Electric', 'petrol' => 'Petrol'];

    public const NORMS = ['BS3', 'BS4', 'BS6', 'Euro 4', 'Euro 5', 'Euro 6'];

    protected $guarded = ['id', 'company_id', 'uuid', 'reg_key'];

    protected $attributes = ['fuel_type' => 'diesel', 'ownership' => 'own', 'status' => 'idle', 'lift_axle' => false];

    protected static function booted(): void
    {
        static::creating(function (Truck $truck) {
            $truck->uuid ??= (string) Str::uuid7();
        });
        static::saving(function (Truck $truck) {
            $truck->reg_key = self::key((string) $truck->reg_no);
        });
    }

    protected function casts(): array
    {
        return [
            'registration_date' => 'date',
            'loan_ends_on'      => 'date',
            'loan_starts_on'    => 'date',
            'loan_rate'         => 'decimal:2',
            'purchase_date'     => 'date',
            'sold_on'           => 'date',
            'odometer_at'       => 'datetime',
            'lift_axle'         => 'boolean',
            'payload_mt'        => 'decimal:3',
            'deck_length_ft'    => 'decimal:1',
            'mileage_loaded_kmpl' => 'decimal:2',
            'mileage_empty_kmpl'  => 'decimal:2',
            'emi_amount'        => 'decimal:2',
            'purchase_price'    => 'decimal:2',
            'odometer_km'       => 'integer',
            'wheels'            => 'integer',
        ];
    }

    /** "kl 07 ab-1234" → "KL07AB1234" */
    public static function key(string $regNo): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $regNo));
    }

    public function scopeInService(Builder $query): Builder
    {
        return $query->where('status', '!=', 'sold');
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function serviceJobs(): HasMany
    {
        return $this->hasMany(ServiceJob::class)->orderByDesc('job_date')->orderByDesc('id');
    }

    public function serviceSchedules(): HasMany
    {
        return $this->hasMany(ServiceSchedule::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'owner_party_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function getLabelAttribute(): string
    {
        return $this->reg_no;
    }

    /** "6 y 3 m" from the registration date (Trucksarthi parity #12). */
    public function age(): ?string
    {
        if (! $this->registration_date) {
            return null;
        }
        $diff = CarbonImmutable::instance($this->registration_date)->diff(now());

        return __(':y y :m m', ['y' => $diff->y, 'm' => $diff->m]);
    }

    /** The year, from the registration date when the year of manufacture is not given. */
    public function year(): ?int
    {
        return $this->manufacture_year ?: $this->registration_date?->year;
    }

    /** Last 5 characters only: what a shared message shows of chassis and engine numbers. */
    public static function tail(?string $value): ?string
    {
        return $value ? '…' . substr($value, -5) : null;
    }
}
