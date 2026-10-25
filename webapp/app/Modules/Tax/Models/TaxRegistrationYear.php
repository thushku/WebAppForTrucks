<?php

namespace App\Modules\Tax\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** India: the GTA option a GSTIN chose for one financial year (locked for that year). */
class TaxRegistrationYear extends Model
{
    use Audited, BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['declared_on' => 'date'];
    }

    public function getLabelAttribute(): string
    {
        return $this->fy_key . ' ' . $this->gta_option;
    }
}
