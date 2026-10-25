<?php

namespace App\Modules\Settings\Models;

use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** The next number of one sequence in one period ("2026-27", "2026-10-25", "all"). */
class DocumentCounter extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['next_number' => 'integer', 'last_assigned_at' => 'datetime'];
    }
}
