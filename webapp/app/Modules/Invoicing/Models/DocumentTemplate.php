<?php

namespace App\Modules\Invoicing\Models;

use App\Modules\Invoicing\Design\DesignSchema;
use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named design for one document family — bills and notes, the LR, the
 * money receipt (blueprint 10 §9). One template per family is the company's
 * default; a template can also be the default for chosen clients or
 * branches. Every save is a new version (document_template_versions), so a
 * change rolls back, and an issued bill keeps the design it was printed with.
 */
class DocumentTemplate extends Model
{
    use Audited, BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['design' => 'array', 'is_default' => 'boolean', 'party_ids' => 'array', 'branch_ids' => 'array', 'version' => 'integer'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentTemplateVersion::class)->orderByDesc('version');
    }

    public function scopeFamily(Builder $query, string $family): Builder
    {
        return $query->where('family', $family);
    }

    /** The stored design, checked again (a design saved by an older version still prints). */
    public function designNormalized(): array
    {
        return DesignSchema::normalize($this->family, (array) $this->design);
    }

    public function themeName(): string
    {
        $themes = DesignSchema::THEMES[$this->family] ?? DesignSchema::THEMES['invoice'];

        return __(($themes[$this->design['theme'] ?? ''] ?? reset($themes))[0]);
    }
}
