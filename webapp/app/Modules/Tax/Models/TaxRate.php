<?php

namespace App\Modules\Tax\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tax code's rate from one date, and how it splits on the invoice:
 *
 *   {"intra": {"CGST": 9, "SGST": 9}, "inter": {"IGST": 18}, "ut": {"CGST": 9, "UTGST": 9}}
 *   {"fixed": {"VAT": 5}}
 *
 * Never edited once an invoice used it: a change is a new row from a date.
 */
class TaxRate extends Model
{
    use Audited, BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['valid_from' => 'date', 'valid_to' => 'date', 'rate' => 'decimal:6', 'parts' => 'array'];
    }

    public function code(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class, 'tax_code_id');
    }

    /**
     * The parts for a supply: within one state, between states, or within a
     * union territory without its own legislature (India); always the same
     * parts elsewhere.
     *
     * @param 'intra'|'inter'|'ut' $where
     * @return array<string, float> code => percent
     */
    public function partsFor(string $where): array
    {
        $parts = $this->parts ?? [];
        $chosen = $parts['fixed'] ?? $parts[$where] ?? ($where === 'ut' ? ($parts['intra'] ?? null) : null) ?? [];

        return array_map('floatval', $chosen) ?: ((float) $this->rate > 0 ? ['TAX' => (float) $this->rate] : []);
    }

    /** "18%" */
    public function percentLabel(): string
    {
        return rtrim(rtrim(number_format((float) $this->rate, 3, '.', ''), '0'), '.') . '%';
    }

    public function getLabelAttribute(): string
    {
        return $this->percentLabel() . ' from ' . $this->valid_from?->toDateString();
    }
}
