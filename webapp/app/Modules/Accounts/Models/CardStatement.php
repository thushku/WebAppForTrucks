<?php

namespace App\Modules\Accounts\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A FASTag or fuel-card provider's statement (M44), or a pump's credit statement (M46), brought in. */
class CardStatement extends Model
{
    use Audited, BelongsToCompany;

    public const KINDS = ['fastag' => 'FASTag', 'fuel_card' => 'Fuel card', 'pump' => 'Pump credit'];

    /** The crew's payment mode each kind of statement answers for. */
    public const MODES = ['fastag' => 'fastag', 'fuel_card' => 'fuel_card', 'pump' => 'credit'];

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['period_from' => 'date', 'period_to' => 'date', 'lines' => 'integer', 'skipped' => 'integer'];
    }

    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Modules\Parties\Models\Party::class, 'vendor_party_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(CardStatementLine::class)->orderBy('txn_at')->orderBy('id');
    }

    public function getLabelAttribute(): string
    {
        return (self::KINDS[$this->kind] ?? $this->kind) . ' ' . $this->file_name;
    }
}
