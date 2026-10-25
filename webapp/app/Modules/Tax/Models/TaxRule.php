<?php

namespace App\Modules\Tax\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Freight facts → a tax code. Every condition left empty means "any"; the
 * rule with the most conditions that match wins, then the higher priority.
 */
class TaxRule extends Model
{
    use Audited, BelongsToCompany;

    /** The conditions, in the order they are shown. */
    public const CONDITIONS = ['service_class', 'gta_option', 'transport_type', 'carrier_role', 'buyer_registered', 'buyer_category'];

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['buyer_registered' => 'boolean', 'valid_from' => 'date', 'valid_to' => 'date', 'is_system' => 'boolean', 'active' => 'boolean', 'priority' => 'integer'];
    }

    /** How many conditions this rule sets (more = more specific). */
    public function specificity(): int
    {
        return collect(self::CONDITIONS)->filter(fn ($c) => $this->{$c} !== null)->count();
    }

    public function getLabelAttribute(): string
    {
        return ($this->service_class ?? 'any') . ' → ' . $this->tax_code;
    }
}
