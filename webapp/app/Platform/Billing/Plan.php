<?php

namespace App\Platform\Billing;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * What a company can buy (decision D20: set by the super-admin, nothing in
 * code): priced per truck, flat, or once for life; monthly, annual or
 * lifetime; with limits (trucks, users, storage) and the modules included.
 * Not company-owned: plans belong to the platform.
 */
class Plan extends Model
{
    public const PRICING = ['per_truck' => 'Per truck', 'flat' => 'Flat price', 'lifetime' => 'Lifetime (one payment)'];

    /** Modules a plan can include or leave out (empty = all). */
    public const MODULES = [
        'trips' => 'Trips and the crew app', 'invoicing' => 'Invoices and LR', 'ledgers' => 'Ledgers', 'accounts' => 'Accounts and tax',
        'documents' => 'Document Manager', 'fleet' => 'Trucks, service and complaints', 'map' => 'Live map', 'reports' => 'Reports',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['modules' => 'array', 'is_student' => 'boolean', 'is_public' => 'boolean', 'active' => 'boolean',
            'price_monthly' => 'decimal:2', 'price_annual' => 'decimal:2', 'price_lifetime' => 'decimal:2'];
    }

    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopeOffered(Builder $query, ?string $country = null): Builder
    {
        return $query->where('active', true)->when($country, fn ($q) => $q->where(fn ($q) => $q->whereNull('country')->orWhere('country', $country)))->orderBy('sort_order');
    }

    /** The price of a period for this many trucks, before any coupon. */
    public function price(string $period, int $trucks = 1): float
    {
        $base = (float) match ($period) {
            'annual' => $this->price_annual,
            'lifetime' => $this->price_lifetime,
            default => $this->price_monthly,
        };

        return round($this->pricing === 'per_truck' ? $base * max(1, $trucks) : $base, 2);
    }

    public function includes(string $module): bool
    {
        return empty($this->modules) || in_array($module, $this->modules, true);
    }
}
