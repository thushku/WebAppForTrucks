<?php

namespace App\Modules\Road\Models;

use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** An e-POD: what was handed over, to whom, when, short or damaged. A load may be delivered in parts. */
class ConsignmentDelivery extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['delivered_at' => 'datetime', 'unloading_started_at' => 'datetime', 'unloading_ended_at' => 'datetime', 'otp_verified' => 'boolean', 'weight_mt' => 'decimal:3'];
    }
}
