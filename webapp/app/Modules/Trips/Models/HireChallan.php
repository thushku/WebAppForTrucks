<?php

namespace App\Modules\Trips\Models;

use App\Modules\Parties\Models\Party;
use App\Modules\Parties\Models\SupplierVehicle;
use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lorry hire challan: what we owe the supplier of a hired truck (blueprint
 * 10 §6.1, 09 §4). Draft until issued (numbered); after that the agreed hire
 * is fixed and money moves by lines (advance, fuel, deductions, payments).
 */
class HireChallan extends Model
{
    use Audited, BelongsToCompany;

    public const STATUSES = ['draft' => 'Draft', 'issued' => 'Issued', 'settled' => 'Settled', 'cancelled' => 'Cancelled'];

    /** line kind => [label, +1 adds to what we owe / −1 reduces it] */
    public const LINE_KINDS = [
        'detention' => ['Detention', 1],
        'extra'     => ['Extra charge', 1],
        'advance'   => ['Advance paid', -1],
        'fuel'      => ['Fuel given', -1],
        'deduction' => ['Deduction (shortage, damage, late)', -1],
        'payment'   => ['Balance paid', -1],
    ];

    protected $guarded = ['id', 'company_id', 'number'];

    protected $attributes = ['hire_basis' => 'fixed', 'commission_amount' => 0, 'commission_mode' => 'deducted', 'office_charge' => 0,
        'tds_rate' => 0, 'tds_amount' => 0, 'status' => 'draft'];

    protected function casts(): array
    {
        return [
            'challan_date' => 'date', 'issued_at' => 'datetime', 'settled_at' => 'datetime',
            'hire_rate' => 'decimal:2', 'hire_weight_mt' => 'decimal:3', 'hire_amount' => 'decimal:2', 'commission_amount' => 'decimal:2',
            'office_charge' => 'decimal:2', 'tds_rate' => 'decimal:2', 'tds_amount' => 'decimal:2',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'supplier_party_id');
    }

    public function broker(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'broker_party_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(SupplierVehicle::class, 'supplier_vehicle_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(HireChallanLine::class)->orderBy('line_date')->orderBy('id');
    }

    /**
     * What is still owed: hire + detention + extras − advances − fuel −
     * deductions − commission (when deducted from the supplier) − office
     * charge − TDS − payments.
     */
    public function balance(): float
    {
        $lines = $this->relationLoaded('lines') ? $this->lines : $this->lines()->get();
        $total = (float) $this->hire_amount
            - (float) $this->office_charge
            - (float) $this->tds_amount
            - ($this->commission_mode === 'deducted' ? (float) $this->commission_amount : 0);

        foreach ($lines as $line) {
            $total += (self::LINE_KINDS[$line->kind][1] ?? 0) * (float) $line->amount;
        }

        return round($total, 2);
    }

    public function getLabelAttribute(): string
    {
        return $this->number ?? ('Hire challan draft #' . $this->id);
    }
}
