<?php

namespace App\Modules\Documents\Models;

use App\Modules\Parties\Models\Party;
use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * The current paper of one owner (blueprint 09 §6). Earlier versions are in
 * DocumentVersion; files of every version in DocumentFile.
 */
class Document extends Model
{
    use Audited, BelongsToCompany;

    public const OWNER_TYPES = ['truck' => 'Truck', 'person' => 'Person', 'company' => 'Company', 'party' => 'Party'];

    /** The reminder steps, in days before expiry (0 = the day, negative = after). */
    public const REMINDER_STEPS = [30, 15, 7, 1, 0];

    protected $guarded = ['id', 'company_id', 'uuid'];

    protected $attributes = ['version' => 1, 'owner_id' => 0];

    protected static function booted(): void
    {
        static::creating(function (Document $d) {
            $d->uuid ??= (string) Str::uuid7();
        });
    }

    protected function casts(): array
    {
        return [
            'issued_on'     => 'date',
            'valid_from'    => 'date',
            'expires_on'    => 'date',
            'snoozed_until' => 'date',
            'reminded_at'   => 'datetime',
            'amount'        => 'decimal:2',
            'version'       => 'integer',
            'owner_id'      => 'integer',
            'last_reminder_days' => 'integer',
        ];
    }

    public function files(): HasMany
    {
        return $this->hasMany(DocumentFile::class)->orderBy('id');
    }

    /** Files of the current version (the relation holds every version's files). */
    public function currentFiles()
    {
        return $this->files->where('version', $this->version)->values();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'vendor_party_id');
    }

    /** Days left until expiry (negative once expired); null when it does not expire. */
    public function daysLeft(): ?int
    {
        return $this->expires_on ? (int) CarbonImmutable::today()->diffInDays($this->expires_on, false) : null;
    }

    /**
     * expired | due (within the type's reminder window, at least 30 days when
     * none is set) | valid | lifelong.
     */
    public function state(?int $remindDays = null): string
    {
        $days = $this->daysLeft();
        if ($days === null) {
            return 'lifelong';
        }
        if ($days < 0) {
            return 'expired';
        }

        return $days <= max(7, $remindDays ?: 30) ? 'due' : 'valid';
    }

    /** "Insurance of KL-07-AB-1234" is built by the screens; here just the type and number. */
    public function getLabelAttribute(): string
    {
        return trim($this->document_type . ' ' . ($this->number ?? ''));
    }
}
