<?php

namespace App\Modules\Parties\Models;

use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One role a party plays (client, supplier…). Changes are audited on the party. */
class PartyRole extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
