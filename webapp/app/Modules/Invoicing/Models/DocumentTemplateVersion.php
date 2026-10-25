<?php

namespace App\Modules\Invoicing\Models;

use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One saved state of a template. Rows are only ever added: a rollback copies
 * an old design forward as a new version.
 */
class DocumentTemplateVersion extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['design' => 'array', 'version' => 'integer'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Account::class, 'created_by');
    }
}
