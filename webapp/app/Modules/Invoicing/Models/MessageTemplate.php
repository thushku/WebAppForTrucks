<?php

namespace App\Modules\Invoicing\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** The company's own wording for one purpose and channel (Settings → Messages). */
class MessageTemplate extends Model
{
    use Audited, BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    public function getLabelAttribute(): string
    {
        return "{$this->purpose} / {$this->channel}";
    }
}
