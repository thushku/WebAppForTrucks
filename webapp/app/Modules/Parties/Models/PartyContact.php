<?php

namespace App\Modules\Parties\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** A person at a party: dispatch, accounts, bill submission… */
class PartyContact extends Model
{
    use Audited, BelongsToCompany;

    public const ROLES = [
        'dispatch'        => 'Dispatch',
        'accounts'        => 'Accounts',
        'bill_submission' => 'Bill submission',
        'owner'           => 'Owner',
        'driver'          => 'Driver',
        'other'           => 'Other',
    ];

    protected $guarded = ['id', 'company_id'];

    protected $attributes = ['is_primary' => false];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }
}
