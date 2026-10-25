<?php

namespace App\Modules\Tax\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a line is taxed as. The category follows the e-invoice standards
 * (UN/CEFACT 5305, used by Peppol PINT-AE and EN 16931): S standard, Z zero,
 * E exempt, AE reverse charge, O outside the scope of tax.
 */
class TaxCode extends Model
{
    use Audited, BelongsToCompany;

    public const CATEGORIES = [
        'S'  => 'Standard',
        'Z'  => 'Zero-rated',
        'E'  => 'Exempt',
        'AE' => 'Reverse charge',
        'O'  => 'Outside the scope of tax',
    ];

    /** Papers a zero rate can ask for. */
    public const EVIDENCE = [
        'transport_document' => 'Consignment note, CMR or bill of lading',
        'exit_or_customs'    => 'Exit or customs document',
        'lut'                => 'Letter of undertaking (export without tax)',
        'sez_authorisation'  => 'SEZ authorisation',
    ];

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['reverse_charge' => 'boolean', 'evidence' => 'array', 'is_system' => 'boolean', 'active' => 'boolean'];
    }

    public function rates(): HasMany
    {
        return $this->hasMany(TaxRate::class)->orderByDesc('valid_from');
    }

    /** The rate valid on a date (a credit note passes the original invoice's date). */
    public function rateOn(CarbonInterface $on): ?TaxRate
    {
        $day = $on->toDateString();
        $rates = $this->relationLoaded('rates') ? $this->rates : $this->rates()->get();

        return $rates->first(fn (TaxRate $r) => $r->valid_from->toDateString() <= $day && ($r->valid_to === null || $r->valid_to->toDateString() >= $day));
    }

    /** No tax is charged on this line (zero, exempt, outside scope); reverse charge keeps a notional amount. */
    public function chargesNothing(): bool
    {
        return in_array($this->category, ['Z', 'E', 'O'], true);
    }

    public function getLabelAttribute(): string
    {
        return $this->code;
    }
}
