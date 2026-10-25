<?php

namespace App\Modules\Invoicing\Models;

use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** One send, open, print or download of a document (the delivery trail). */
class DocumentDelivery extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'created_at' => 'datetime'];
    }
}
