<?php

namespace App\Modules\Documents\Models;

use App\Models\Account;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A replaced version of a document: read-only history (the renewal is audited on the document). */
class DocumentVersion extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['issued_on' => 'date', 'valid_from' => 'date', 'expires_on' => 'date', 'archived_at' => 'datetime', 'amount' => 'decimal:2'];
    }

    public function renewer(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'renewed_by');
    }
}
