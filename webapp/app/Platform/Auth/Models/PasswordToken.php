<?php

namespace App\Platform\Auth\Models;

use Illuminate\Database\Eloquent\Model;

/** A single-use link (verify email, reset password, invitation), stored as a hash. */
class PasswordToken extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
