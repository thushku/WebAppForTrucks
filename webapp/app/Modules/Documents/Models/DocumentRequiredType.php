<?php

namespace App\Modules\Documents\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** A document every truck (of a type) or every person (of a type) must have. */
class DocumentRequiredType extends Model
{
    use Audited, BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    public function getLabelAttribute(): string
    {
        return "{$this->applies_to}" . ($this->subtype ? ":{$this->subtype}" : '') . " needs {$this->document_type}";
    }
}
