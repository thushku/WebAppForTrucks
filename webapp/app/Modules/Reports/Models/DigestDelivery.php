<?php

namespace App\Modules\Reports\Models;

use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** One morning summary sent (or failed) to one person for one day or week (M32). */
class DigestDelivery extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['period_key' => 'date', 'sent_at' => 'datetime'];
    }
}
