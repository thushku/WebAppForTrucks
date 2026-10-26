<?php

namespace App\Modules\Invoicing\Models;

use App\Modules\Parties\Models\Party;
use App\Modules\Parties\Models\RateContract;
use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use App\Platform\Tenancy\HasBranch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A quotation / rate confirmation to a client (M21, blueprint 10 §6.3). A
 * draft is edited freely; issuing gives it its number and fixes it; the
 * client accepts or declines it. Not a tax document and never posted: the
 * bill that follows works its tax out with the engine. "Expired" is read
 * from the date, never stored.
 */
class Quotation extends Model
{
    use Audited, BelongsToCompany, HasBranch;

    public const STATUSES = ['draft' => 'Draft', 'issued' => 'Sent', 'accepted' => 'Accepted', 'declined' => 'Declined', 'cancelled' => 'Cancelled'];

    protected $guarded = ['id', 'company_id', 'number', 'public_token'];

    protected function casts(): array
    {
        return ['quote_date' => 'date', 'valid_until' => 'date', 'issued_at' => 'datetime', 'decided_at' => 'datetime', 'viewed_at' => 'datetime'];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function rateContract(): BelongsTo
    {
        return $this->belongsTo(RateContract::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /** Sent, not answered, and past its date (on the company's calendar). */
    public function isExpired(?CarbonImmutable $today = null): bool
    {
        return $this->status === 'issued' && $this->valid_until !== null
            && $this->valid_until->toDateString() < ($today ?? CarbonImmutable::today())->toDateString();
    }

    /** The status as shown: "Expired" when a sent quotation's date has passed. */
    public function statusLabel(?CarbonImmutable $today = null): string
    {
        return $this->isExpired($today) ? __('Expired') : __(self::STATUSES[$this->status] ?? $this->status);
    }

    public function getLabelAttribute(): string
    {
        return $this->number ?? __('Draft quotation #:id', ['id' => $this->id]);
    }
}
