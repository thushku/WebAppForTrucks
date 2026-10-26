<?php

namespace App\Modules\Trips\Models;

use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A leaf of an LR book that will never carry a load: cancelled (spoilt) or lost, with why. */
class LrBookLeaf extends Model
{
    use BelongsToCompany;

    public const STATUSES = ['cancelled' => 'Cancelled', 'lost' => 'Lost'];

    protected $guarded = ['id', 'company_id'];

    public function book(): BelongsTo
    {
        return $this->belongsTo(LrBook::class, 'lr_book_id');
    }
}
