<?php

namespace App\Modules\Settings\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A place trips start, stop or end at: a city, a customer's site, a pump, a
 * toll plaza. Its short code goes into trip numbers (decision P1): a trip
 * from Kochi (COK) to Mumbai (BOM) is 261013-ACC-COK-BOM-01.
 */
class Place extends Model
{
    use Audited, BelongsToCompany;

    public const TYPES = [
        'city'          => 'City / town',
        'customer_site' => 'Customer site',
        'pump'          => 'Fuel pump',
        'toll'          => 'Toll plaza',
        'weighbridge'   => 'Weighbridge',
        'godown'        => 'Godown / yard',
        'border'        => 'Border / checkpost',
        'other'         => 'Other',
    ];

    protected $guarded = ['id', 'company_id'];

    protected $attributes = ['type' => 'city', 'country' => 'IN', 'active' => true];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'lat' => 'float', 'lng' => 'float'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /** "Kochi (COK)" */
    public function getLabelAttribute(): string
    {
        return "{$this->name} ({$this->short_code})";
    }

    /**
     * A suggested short code from a name: the first three letters of the
     * first word, upper case ("Bhiwandi" → "BHI"). The person can change it.
     */
    public static function suggestCode(string $name): string
    {
        $letters = preg_replace('/[^A-Za-z]/', '', (string) (preg_split('/\s+/', trim($name))[0] ?? ''));

        return strtoupper(substr($letters ?: 'PLC', 0, 3));
    }
}
