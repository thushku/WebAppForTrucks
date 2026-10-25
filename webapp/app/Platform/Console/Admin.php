<?php

namespace App\Platform\Console;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A super-admin of the platform (the SaaS provider's own staff).
 *
 * Separate table, separate guard ('platform'), separate secret URL, and a
 * one-time email code on EVERY sign-in (website guideline §4: "Admin
 * two-factor auth").
 */
class Admin extends Authenticatable
{
    protected $fillable = ['name', 'email'];

    protected $hidden = ['password_hash', 'remember_token'];

    protected $attributes = ['status' => 'active', 'failed_attempts' => 0, 'lock_level' => 0];

    protected function casts(): array
    {
        return [
            'locked_until'   => 'datetime',
            'locked_hard_at' => 'datetime',
            'last_login_at'  => 'datetime',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getAuthPassword(): string
    {
        return (string) $this->password_hash;
    }

    public function canLogIn(): bool
    {
        return $this->status === 'active';
    }
}
