<?php

namespace App\Modules\Trips\Models;

use App\Modules\Parties\Models\Party;
use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One booking = one LR (blueprint 10 §4, 12 §3). On a part-load trip each
 * consignment picks up and drops at its own stops. The billing party carries
 * the balance; consignor and consignee are address-book roles.
 */
class Consignment extends Model
{
    use Audited, BelongsToCompany;

    public const STATUSES = [
        'booked'       => 'Booked',
        'picked_up'    => 'Picked up',
        'in_transit'   => 'In transit',
        'delivered'    => 'Delivered',
        'pod_received' => 'POD received',
        'billed'       => 'Billed',
        'cancelled'    => 'Cancelled',
    ];

    public const PAYMENT_TYPES = ['to_be_billed' => 'To be billed', 'paid' => 'Paid (at booking)', 'to_pay' => 'To pay (at delivery)', 'foc' => 'Free of charge'];

    public const GST_PAYERS = ['consignor' => 'Consignor', 'consignee' => 'Consignee', 'transporter' => 'Transporter'];

    public const BASES = ['trip' => 'per trip', 'tonne' => 'per tonne', 'kg' => 'per kg', 'km' => 'per km', 'package' => 'per package'];

    public const PACKAGE_TYPES = ['bags', 'boxes', 'drums', 'pallets', 'bundles', 'rolls', 'pieces', 'loose', 'container'];

    protected $guarded = ['id', 'company_id', 'uuid', 'lr_number'];

    protected $attributes = ['status' => 'booked', 'freight_basis' => 'trip', 'freight' => 0, 'loading_charge' => 0, 'unloading_charge' => 0,
        'other_charges' => 0, 'advance_from_party' => 0, 'payment_type' => 'to_be_billed', 'risk' => 'owner', 'hazardous' => false,
        'over_dimension' => false, 'pod_status' => 'none'];

    protected static function booted(): void
    {
        static::creating(function (Consignment $c) {
            $c->uuid ??= (string) Str::uuid7();
        });
    }

    protected function casts(): array
    {
        return [
            'lr_date' => 'date', 'consignor_invoice_date' => 'date', 'delivered_at' => 'datetime', 'pod_received_at' => 'datetime',
            'actual_weight_mt' => 'decimal:3', 'charged_weight_mt' => 'decimal:3', 'rate' => 'decimal:2', 'freight' => 'decimal:2',
            'loading_charge' => 'decimal:2', 'unloading_charge' => 'decimal:2', 'other_charges' => 'decimal:2', 'advance_from_party' => 'decimal:2',
            'consignor_invoice_value' => 'decimal:2', 'declared_value' => 'decimal:2', 'detention_rate_per_day' => 'decimal:2',
            'hazardous' => 'boolean', 'over_dimension' => 'boolean', 'packages' => 'integer', 'detention_free_hours' => 'integer',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function billingParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'billing_party_id');
    }

    public function consignor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'consignor_party_id');
    }

    public function consignee(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'consignee_party_id');
    }

    /** The printed LR book its typed number is from (M21); null for the automatic series. */
    public function lrBook(): BelongsTo
    {
        return $this->belongsTo(LrBook::class);
    }

    /** The quotation its rate came from (M21). */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Invoicing\Models\Quotation::class);
    }

    public function pickupStop(): BelongsTo
    {
        return $this->belongsTo(TripStop::class, 'pickup_stop_id');
    }

    public function dropStop(): BelongsTo
    {
        return $this->belongsTo(TripStop::class, 'drop_stop_id');
    }

    public function ewaybills(): HasMany
    {
        return $this->hasMany(ConsignmentEwaybill::class);
    }

    /** Freight plus the charges billed with it. */
    public function totalCharges(): float
    {
        return round((float) $this->freight + (float) $this->loading_charge + (float) $this->unloading_charge + (float) $this->other_charges, 2);
    }

    /** The weight the load counts for (charged, else actual). */
    public function weight(): float
    {
        return (float) ($this->charged_weight_mt ?? $this->actual_weight_mt ?? 0);
    }

    public function getLabelAttribute(): string
    {
        return $this->lr_number ?? ('LR draft #' . $this->id);
    }
}
