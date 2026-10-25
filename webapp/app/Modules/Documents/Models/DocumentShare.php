<?php

namespace App\Modules\Documents\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** A secure, expiring link to a pack of documents ("all papers of KL-07-AB-1234"). */
class DocumentShare extends Model
{
    use Audited, BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['document_ids' => 'array', 'expires_at' => 'datetime', 'last_opened_at' => 'datetime', 'revoked_at' => 'datetime', 'opened_count' => 'integer'];
    }

    public function isLive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    public function getLabelAttribute(): string
    {
        return $this->purpose;
    }
}
