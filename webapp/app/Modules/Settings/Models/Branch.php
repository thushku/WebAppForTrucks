<?php

namespace App\Modules\Settings\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** A branch or office of the company (Kochi, Mumbai…). Its code can appear in document numbers. */
class Branch extends Model
{
    use Audited, BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected $attributes = ['is_head_office' => false, 'active' => true];

    protected function casts(): array
    {
        return ['is_head_office' => 'boolean', 'active' => 'boolean'];
    }
}
