<?php

namespace App\Modules\People\Models;

use App\Models\CompanyUser;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Settings\Models\Branch;
use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use App\Platform\Tenancy\HasBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A driver, helper or member of staff (blueprint 06 §6).
 *
 * The person is the company's record (licence, pay, bank, emergency
 * contact). Their phone-app login is separate: an Account (one per real
 * person, shared across companies — P7) joined to this company through
 * `company_user_id`, made when the office issues an activation code.
 *
 * ID numbers (Aadhaar, Emirates ID) and bank details are encrypted at rest;
 * screens show the last four characters, and only `people.id_documents`
 * sees more.
 */
class Person extends Model
{
    use Audited, BelongsToCompany, HasBranch;

    /** A truck or person with no branch works for every branch (M22). */
    public static function branchSharedWhenUnassigned(): bool
    {
        return true;
    }

    protected $table = 'people';

    public const TYPES = ['driver' => 'Driver', 'helper' => 'Helper / cleaner', 'office' => 'Office staff', 'mechanic' => 'Mechanic', 'other' => 'Other'];

    public const STATUSES = ['active' => 'Working', 'on_leave' => 'On leave', 'left' => 'Left'];

    public const ID_TYPES = ['aadhaar' => 'Aadhaar', 'emirates_id' => 'Emirates ID', 'passport' => 'Passport', 'voter_id' => 'Voter ID', 'other' => 'Other'];

    public const PAY_BASES = ['monthly' => 'Monthly salary', 'per_trip' => 'Per trip', 'per_km' => 'Per km'];

    /** The app role a crew type gets when their phone is activated. */
    public const APP_ROLE = ['driver' => 'driver', 'helper' => 'helper', 'mechanic' => 'workshop', 'office' => 'viewer', 'other' => 'helper'];

    protected $guarded = ['id', 'company_id', 'uuid'];

    protected $hidden = ['id_number', 'bank_account_number', 'bank_iban', 'upi_id'];

    protected $attributes = ['type' => 'driver', 'status' => 'active', 'pay_basis' => 'monthly', 'hazardous_endorsement' => false];

    protected static function booted(): void
    {
        static::creating(function (Person $p) {
            $p->uuid ??= (string) Str::uuid7();
        });
    }

    protected function casts(): array
    {
        return [
            'date_of_birth'  => 'date',
            'joining_date'   => 'date',
            'leaving_date'   => 'date',
            'licence_expiry' => 'date',
            'hazardous_endorsement' => 'boolean',
            'id_number'      => 'encrypted',
            'bank_account_number' => 'encrypted',
            'bank_iban'      => 'encrypted',
            'upi_id'         => 'encrypted',
            'monthly_salary' => 'decimal:2',
            'per_trip_rate'  => 'decimal:2',
            'per_km_rate'    => 'decimal:2',
        ];
    }

    public function scopeCrew(Builder $query): Builder
    {
        return $query->whereIn('type', ['driver', 'helper']);
    }

    public function scopeWorking(Builder $query): Builder
    {
        return $query->where('status', '!=', 'left');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(CompanyUser::class, 'company_user_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function defaultTruck(): BelongsTo
    {
        return $this->belongsTo(Truck::class, 'default_truck_id');
    }

    public function activationCodes(): HasMany
    {
        return $this->hasMany(ActivationCode::class);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];

        return mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
    }

    /**
     * valid | expiring (within 30 days) | expired | missing — for the
     * licence chip on the people list (Trucksarthi parity #19).
     */
    public function licenceState(): string
    {
        if (! in_array($this->type, ['driver'], true) && ! $this->licence_no) {
            return 'na';
        }
        if (! $this->licence_no || ! $this->licence_expiry) {
            return 'missing';
        }
        $days = now()->startOfDay()->diffInDays($this->licence_expiry, false);

        return $days < 0 ? 'expired' : ($days <= 30 ? 'expiring' : 'valid');
    }

    /** "XXXX XXXX 1234" for an Aadhaar (UIDAI: show at most the last 4 digits). */
    public function maskedId(): ?string
    {
        if (! $this->id_last4) {
            return null;
        }

        return $this->id_type === 'aadhaar' ? 'XXXX XXXX ' . $this->id_last4 : '••••' . $this->id_last4;
    }
}
