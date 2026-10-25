<?php

namespace App\Modules\Parties\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A hired / market truck belonging to a supplier (blueprint 09 §4.1). */
class SupplierVehicle extends Model
{
    use Audited, BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'capacity_mt' => 'decimal:3'];
    }

    protected static function booted(): void
    {
        static::saving(function (SupplierVehicle $v) {
            $v->reg_key = self::key((string) $v->reg_no);
        });
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** "kl 07 ab-1234" → "KL07AB1234": the same truck however it is typed. */
    public static function key(string $regNo): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $regNo));
    }

    public function getLabelAttribute(): string
    {
        return $this->reg_no;
    }
}
