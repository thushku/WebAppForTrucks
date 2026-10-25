<?php

namespace App\Modules\Documents\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One image or PDF of a document version, stored privately. */
class DocumentFile extends Model
{
    use Audited, BelongsToCompany;

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['bytes' => 'integer', 'version' => 'integer', 'created_at' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    /** The stored label ("Page 2"), else the uploaded file's name. */
    public function getLabelAttribute(?string $value): string
    {
        return (string) ($value ?: ($this->attributes['original_name'] ?? ''));
    }
}
