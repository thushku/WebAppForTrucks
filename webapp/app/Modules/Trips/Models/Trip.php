<?php

namespace App\Modules\Trips\Models;

use App\Modules\Fleet\Models\Truck;
use App\Modules\Parties\Models\Party;
use App\Modules\Parties\Models\SupplierVehicle;
use App\Modules\Settings\Models\Branch;
use App\Modules\Settings\Models\Place;
use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One journey of one truck (blueprint 03 §1): its crew, stops, consignments,
 * and later its road events, settlement and profit.
 */
class Trip extends Model
{
    use Audited, BelongsToCompany;

    public const STATUSES = [
        'draft'     => 'Draft',
        'assigned'  => 'Sent to crew',
        'accepted'  => 'Accepted',
        'running'   => 'Running',
        'ended'     => 'Ended',
        'settled'   => 'Settled',
        'closed'    => 'Closed',
        'cancelled' => 'Cancelled',
    ];

    public const LOAD_TYPES = ['ftl' => 'Full load (FTL)', 'ptl' => 'Part load (PTL)', 'mixed' => 'Mixed'];

    public const KINDS = ['loaded' => 'Loaded', 'empty' => 'Empty run', 'round' => 'Round trip'];

    /** A truck or person on a trip in these states is busy. */
    public const OPEN_STATUSES = ['assigned', 'accepted', 'running'];

    protected $guarded = ['id', 'company_id', 'uuid', 'trip_number'];

    protected $attributes = ['load_type' => 'ftl', 'trip_kind' => 'loaded', 'truck_source' => 'own', 'status' => 'draft', 'other_charges' => 0, 'ended_early' => false];

    protected static function booted(): void
    {
        static::creating(function (Trip $trip) {
            $trip->uuid ??= (string) Str::uuid7();
        });
    }

    protected function casts(): array
    {
        return [
            'trip_date'            => 'date',
            'planned_start_at'     => 'datetime',
            'expected_delivery_at' => 'datetime',
            'assigned_at'          => 'datetime',
            'started_at'           => 'datetime',
            'ended_at'             => 'datetime',
            'cancelled_at'         => 'datetime',
            'settled_at'           => 'datetime',
            'closed_at'            => 'datetime',
            'reopen_count'         => 'integer',
            'budget_fuel'          => 'decimal:2',
            'budget_toll'          => 'decimal:2',
            'budget_bata'          => 'decimal:2',
            'other_charges'        => 'decimal:2',
            'ended_early'          => 'boolean',
            'planned_km'           => 'integer',
            'start_odometer'       => 'integer',
            'end_odometer'         => 'integer',
        ];
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'supplier_party_id');
    }

    public function supplierVehicle(): BelongsTo
    {
        return $this->belongsTo(SupplierVehicle::class);
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'from_place_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'to_place_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function returnOf(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'return_of_trip_id');
    }

    public function crew(): HasMany
    {
        return $this->hasMany(TripCrew::class)->orderByRaw("field(role, 'driver', 'co_driver', 'helper')")->orderBy('id');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(TripStop::class)->orderBy('seq');
    }

    public function consignments(): HasMany
    {
        return $this->hasMany(Consignment::class)->orderBy('id');
    }

    public function advances(): HasMany
    {
        return $this->hasMany(TripAdvance::class)->orderBy('given_on')->orderBy('id');
    }

    public function hireChallans(): HasMany
    {
        return $this->hasMany(HireChallan::class)->orderBy('id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(BrokerCommission::class)->orderBy('id');
    }

    public function officeEntries(): HasMany
    {
        return $this->hasMany(TripOfficeEntry::class)->orderBy('entry_date')->orderBy('id');
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(TripSettlement::class)->orderBy('id');
    }

    public function roadEvents(): HasMany
    {
        return $this->hasMany(\App\Modules\Road\Models\RoadEvent::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /** Route, crew and consignments may change until the trip starts running. */
    public function isPlanning(): bool
    {
        return in_array($this->status, ['draft', 'assigned', 'accepted'], true);
    }

    public function isCancellable(): bool
    {
        return $this->isPlanning();
    }

    /** "KL-07-AB-1234" or "MH-04-KL-7788 (hired)" */
    public function vehicleLabel(): string
    {
        return $this->truck_source === 'hired'
            ? ($this->supplierVehicle?->reg_no ?? '—') . ' (' . __('hired') . ')'
            : ($this->truck?->reg_no ?? '—');
    }

    public function getLabelAttribute(): string
    {
        return $this->trip_number ?? ('Draft #' . $this->id);
    }
}
