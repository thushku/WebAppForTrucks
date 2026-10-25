<?php

namespace App\Platform\Notifications;

use App\Models\Account;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Something one person was told, in one company (M20): the bell's and the phone's inbox, and the push sent. */
class UserNotification extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['data' => 'array', 'read_at' => 'datetime', 'pushed_at' => 'datetime', 'times' => 'integer'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** The inbox of one person in the company in context. */
    public function scopeFor($query, Account|int $account)
    {
        return $query->where('account_id', $account instanceof Account ? $account->id : $account);
    }

    /** What the phone apps get: the words, what to open, and whether it was read. */
    public function toApi(): array
    {
        return [
            'id' => $this->id, 'kind' => $this->kind, 'title' => $this->title, 'body' => $this->body, 'data' => $this->data ?? (object) [],
            'times' => $this->times, 'read' => $this->read_at !== null, 'at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
