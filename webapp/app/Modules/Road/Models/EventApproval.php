<?php

namespace App\Modules\Road\Models;

use App\Models\Account;
use App\Modules\People\Models\Person;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One decision on a road event, by the second driver, the office or the crew (answering a query). Never changed. */
class EventApproval extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    public const DECISIONS = ['approve' => 'Approved', 'reject' => 'Rejected', 'query' => 'Asked', 'answer' => 'Answered'];

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** "Vikram Rao" or "Suresh Nair (2nd driver)". */
    public function who(): string
    {
        return match ($this->step) {
            'second_driver' => ($this->person?->name ?? '?') . ' (' . __('2nd driver') . ')',
            'crew'          => $this->person?->name ?? '?',
            default         => $this->account?->name ?? __('Office'),
        };
    }
}
