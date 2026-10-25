<?php

namespace App\Modules\Parties\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A firm or person the company deals with: one record, many roles
 * (blueprint 09). ACC Ltd can be the client who pays, the consignee at its
 * depot and, now and then, a vendor — and it is still one party, so its
 * balances and documents are in one place.
 *
 * Words on screen: a Supplier supplies a truck (we owe lorry hire); a Vendor
 * sells goods or services (fuel, repairs, tyres…) — decision P4.
 */
class Party extends Model
{
    use Audited, BelongsToCompany;

    /** role => [label, one-line hint] */
    public const ROLES = [
        'client'    => ['Client', 'Pays for transport; carries a balance'],
        'consignor' => ['Consignor', 'Sends the goods (address book)'],
        'consignee' => ['Consignee', 'Receives the goods (address book)'],
        'supplier'  => ['Supplier', 'Owner or broker of a hired truck — we owe lorry hire'],
        'vendor'    => ['Vendor', 'Sells us goods or services: fuel, repairs, tyres…'],
        'broker'    => ['Broker', 'Arranges loads or trucks for a commission'],
    ];

    public const SUPPLIER_TYPES = [
        'owner_operator' => 'Owner-operator',
        'fleet_operator' => 'Fleet operator',
        'broker'         => 'Vehicle broker',
    ];

    public const VENDOR_CATEGORIES = [
        'fuel_pump'   => 'Fuel pump',
        'workshop'    => 'Workshop / mechanic',
        'tyres'       => 'Tyre dealer',
        'spares'      => 'Spare parts',
        'lubricants'  => 'Batteries and lubricants',
        'insurance'   => 'Insurance company / agent',
        'rto_agent'   => 'RTO agent',
        'gps_fastag'  => 'GPS / FASTag provider',
        'labour'      => 'Loading labour',
        'weighbridge' => 'Weighbridge',
        'towing'      => 'Towing',
        'rent'        => 'Rent',
        'professional' => 'CA / legal',
        'other'       => 'Other',
    ];

    protected $guarded = ['id', 'company_id', 'uuid'];

    protected $attributes = [
        'country' => 'IN', 'due_basis' => 'invoice', 'credit_action' => 'warn', 'billing_cycle' => 'per_lr',
        'pod_required' => 'none', 'tds_deducted' => false, 'blacklisted' => false, 'opening_balance' => 0,
        'opening_side' => 'receivable', 'active' => true,
    ];

    protected static function booted(): void
    {
        static::creating(function (Party $party) {
            $party->uuid ??= (string) Str::uuid7();
        });
    }

    protected function casts(): array
    {
        return [
            'required_refs'    => 'array',
            'tds_deducted'     => 'boolean',
            'blacklisted'      => 'boolean',
            'active'           => 'boolean',
            'credit_limit'     => 'decimal:2',
            'opening_balance'  => 'decimal:2',
            'opening_as_of'    => 'date',
            'credit_days'      => 'integer',
            'payment_days'     => 'integer',
            'rating'           => 'integer',
            'lat'              => 'float',
            'lng'              => 'float',
        ];
    }

    public function roles(): HasMany
    {
        return $this->hasMany(PartyRole::class);
    }

    public function taxIds(): HasMany
    {
        return $this->hasMany(PartyTaxId::class)->orderByDesc('is_default')->orderBy('state_code');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(PartyContact::class)->orderByDesc('is_primary')->orderBy('name');
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(PartyBankAccount::class)->orderByDesc('is_default');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(SupplierVehicle::class)->orderByDesc('active')->orderBy('reg_no');
    }

    public function rateContracts(): HasMany
    {
        return $this->hasMany(RateContract::class)->orderByDesc('valid_from')->orderByDesc('version');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'group_party_id');
    }

    public function scopeWithRole(Builder $query, string $role): Builder
    {
        return $query->whereHas('roles', fn ($q) => $q->where('role', $role));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /** @return string[] */
    public function roleCodes(): array
    {
        return $this->relationLoaded('roles') ? $this->roles->pluck('role')->all() : $this->roles()->pluck('role')->all();
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roleCodes(), true);
    }

    /** The default GSTIN / TRN, if any. */
    public function primaryTaxId(): ?PartyTaxId
    {
        return $this->taxIds->firstWhere('is_default', true) ?? $this->taxIds->first();
    }

    /** "ACC" from "ACC Limited", "SHA" from "Sharma Traders": the person can change it. */
    public static function suggestCode(string $name): string
    {
        $words = preg_split('/\s+/', trim(preg_replace('/[^A-Za-z0-9\s]/', ' ', $name))) ?: [];
        $first = $words[0] ?? '';
        // An acronym written in capitals ("ACC", "TVS") is the code itself.
        if (preg_match('/^[A-Z]{2,6}$/', $first)) {
            return $first;
        }

        return strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $first) ?: 'PTY', 0, 3));
    }

    /** "Ravi will give you ₹18,750": receivable when positive. */
    public function openingSigned(): float
    {
        return (float) $this->opening_balance * ($this->opening_side === 'payable' ? -1 : 1);
    }
}
