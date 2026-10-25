<?php

namespace App\Modules\Fleet\Models;

use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** A part or a labour line on a service job. Saved with its job, audited there. */
class ServiceJobItem extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3', 'rate' => 'decimal:2', 'amount' => 'decimal:2'];
    }
}
