<?php

namespace App\Modules\Settings\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One entry of a small company-editable list. The lists and what extra
 * fields each one keeps in `meta` are described in LISTS; the starting
 * entries per country are in CountryDefaults.
 */
class MasterItem extends Model
{
    use Audited, BelongsToCompany;

    /** list => [title, meta fields => label] */
    public const LISTS = [
        'material'         => ['Materials (goods carried)', []],
        'expense_category' => ['Expense categories', ['group' => 'Group (running / maintenance / office)', 'needs_receipt' => 'Receipt photo needed']],
        'truck_type'       => ['Truck types', ['wheels' => 'Wheels', 'payload_mt' => 'Typical payload (MT)']],
        'body_type'        => ['Body types', []],
        'document_type'    => ['Document types', ['owner' => 'For (truck / person / company / party)', 'remind_days' => 'Remind days before expiry']],
        'leave_type'       => ['Leave types', ['paid' => 'Paid', 'days_per_year' => 'Days per year']],
        'complaint_category' => ['Complaint categories', []],
    ];

    protected $guarded = ['id', 'company_id'];

    protected $attributes = ['sort' => 0, 'is_system' => false, 'active' => true];

    protected function casts(): array
    {
        return ['meta' => 'array', 'is_system' => 'boolean', 'active' => 'boolean', 'sort' => 'integer'];
    }

    public function scopeOfList(Builder $query, string $list): Builder
    {
        return $query->where('list', $list)->orderBy('sort')->orderBy('name');
    }
}
