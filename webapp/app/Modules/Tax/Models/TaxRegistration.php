<?php

namespace App\Modules\Tax\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use App\Support\FiscalYear;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One of our tax registrations: a GSTIN (one per state we work from) or the
 * UAE TRN. Invoices are issued under one of them; India also keeps, per
 * financial year, the GTA option chosen through Annexure V.
 */
class TaxRegistration extends Model
{
    use Audited, BelongsToCompany;

    public const REGIMES = ['in_gst' => 'India GST', 'ae_vat' => 'UAE VAT'];

    /** India GTA options from 22 Sep 2025 (GST 2.0). */
    public const GTA_OPTIONS = [
        'rcm_5'   => '5% under reverse charge (the client pays) — the default',
        'fcm_5'   => '5% forward charge, no input tax credit',
        'fcm_18'  => '18% forward charge, with input tax credit',
        'non_gta' => 'Not a GTA: no consignment note issued (exempt road transport)',
    ];

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'active' => 'boolean', 'registered_on' => 'date', 'cancelled_on' => 'date'];
    }

    public function years(): HasMany
    {
        return $this->hasMany(TaxRegistrationYear::class)->orderByDesc('fy_key');
    }

    public function scopeUsable(Builder $query, ?CarbonInterface $on = null): Builder
    {
        $day = ($on ?? now())->toDateString();

        return $query->where('active', true)
            ->where(fn ($q) => $q->whereNull('registered_on')->orWhere('registered_on', '<=', $day))
            ->where(fn ($q) => $q->whereNull('cancelled_on')->orWhere('cancelled_on', '>=', $day));
    }

    /** The GTA option in force on a date (the default, 5% reverse charge, when none was recorded). */
    public function gtaOption(CarbonInterface $on, int $fyStartMonth = 4): string
    {
        $key = FiscalYear::containing($on, $fyStartMonth)->key;
        $year = $this->relationLoaded('years') ? $this->years->firstWhere('fy_key', $key) : $this->years()->where('fy_key', $key)->first();

        return $year?->gta_option ?? 'rcm_5';
    }

    /** The PAN inside a GSTIN (characters 3–12). */
    public function pan(): ?string
    {
        return $this->regime === 'in_gst' && strlen((string) $this->tax_id) === 15 ? substr($this->tax_id, 2, 10) : null;
    }

    public function getLabelAttribute(): string
    {
        return $this->tax_id;
    }
}
