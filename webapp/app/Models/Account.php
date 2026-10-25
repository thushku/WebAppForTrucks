<?php

namespace App\Models;

use App\Platform\Authorization\PermissionResolver;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * One real person's login identity.
 *
 * Office users sign in on the web; crew sign in on the phone. The same
 * account may work for several companies (decision P7); what it may do is
 * decided per company, through its membership (CompanyUser) and roles.
 */
class Account extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = ['name', 'email', 'mobile', 'locale', 'date_of_birth', 'photo_path'];

    protected $hidden = ['password_hash', 'pin_hash', 'remember_token'];

    /** The database defaults, so a new, unsaved-then-saved model reads the same as a loaded one. */
    protected $attributes = [
        'locale'          => 'en',
        'status'          => 'active',
        'mfa_enabled'     => false,
        'failed_attempts' => 0,
        'lock_level'      => 0,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'mobile_verified_at' => 'datetime',
            'locked_until'       => 'datetime',
            'locked_hard_at'     => 'datetime',
            'last_login_at'      => 'datetime',
            'date_of_birth'      => 'date',
            'mfa_enabled'        => 'boolean',
        ];
    }

    // ---- Authentication ----------------------------------------------------

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getAuthPassword(): string
    {
        return (string) $this->password_hash;
    }

    public function isLocked(): bool
    {
        return $this->locked_hard_at !== null
            || ($this->locked_until !== null && $this->locked_until->isFuture());
    }

    public function canLogIn(): bool
    {
        return $this->status === 'active';
    }

    public function isVerified(): bool
    {
        return $this->email_verified_at !== null || $this->mobile_verified_at !== null;
    }

    public function markLoggedIn(?string $ip): void
    {
        $this->forceFill([
            'failed_attempts' => 0,
            'last_login_at'   => now(),
            'last_login_ip'   => $ip,
        ])->saveQuietly();
    }

    public function unlock(): void
    {
        $this->forceFill([
            'failed_attempts' => 0,
            'lock_level'      => 0,
            'locked_until'    => null,
            'locked_hard_at'  => null,
        ])->saveQuietly();
    }

    // ---- Companies -----------------------------------------------------------

    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyUser::class);
    }

    /** Memberships the person can actually open (active member of a usable company). */
    public function activeMemberships()
    {
        return $this->memberships()
            ->where('status', 'active')
            ->whereHas('company', fn ($q) => $q->usable())
            ->with('company');
    }

    /** The membership in the company of this request. */
    public function currentMembership(): ?CompanyUser
    {
        $member = app(CompanyContext::class)->member();

        return $member && $member->account_id === $this->id ? $member : null;
    }

    /** Does this person hold the permission in the current company? Deny by default. */
    public function hasPermission(string $code): bool
    {
        $member = $this->currentMembership();

        return $member !== null && app(PermissionResolver::class)->has($member, $code);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';

        return mb_strtoupper($first . $last) ?: '?';
    }
}
