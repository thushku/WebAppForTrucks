<?php

namespace App\Modules\Accounts\Models;

use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * The books locked up to a date (blueprint 07 §1: financial years and
 * locks). Rows are only added: the newest says where the lock stands, the
 * rest is the history of who locked and unlocked, and why.
 */
class BookLock extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['locked_until' => 'date'];
    }

    public function author(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Account::class, 'created_by');
    }
}
