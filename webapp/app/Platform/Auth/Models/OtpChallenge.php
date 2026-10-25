<?php

namespace App\Platform\Auth\Models;

use App\Models\Account;
use App\Platform\Console\Admin;
use Illuminate\Database\Eloquent\Model;

/**
 * One one-time code that was sent: for a person's account or a console admin.
 * Only the code's hash is kept; the plain code never touches the database.
 */
class OtpChallenge extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expires_at'   => 'datetime',
            'consumed_at'  => 'datetime',
            'attempts'     => 'integer',
            'max_attempts' => 'integer',
        ];
    }

    /** The account or admin the code was sent to. */
    public function subject(): Account|Admin|null
    {
        return match ($this->subject_type) {
            'account' => Account::find($this->subject_id),
            'admin'   => Admin::find($this->subject_id),
            default   => null,
        };
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null
            && $this->expires_at->isFuture()
            && $this->attempts < $this->max_attempts;
    }
}
