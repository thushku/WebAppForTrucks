<?php

namespace App\Modules\Invoicing\Models;

use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** A client's outstanding, overdue and advance, kept in step by BalanceService (fast lists, no summing on every page). */
class PartyBalance extends Model
{
    use BelongsToCompany;

    public const CREATED_AT = null;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['outstanding' => 'decimal:3', 'overdue' => 'decimal:3', 'advance' => 'decimal:3', 'oldest_due' => 'date', 'open_bills' => 'integer'];
    }
}
