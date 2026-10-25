<?php

namespace App\Modules\Trips\Models;

use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** An e-way bill on an LR (India). An LR may carry several. */
class ConsignmentEwaybill extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['valid_until' => 'datetime', 'value' => 'decimal:2'];
    }
}
