<?php

namespace App\Modules\Accounts\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A FASTag or fuel-card provider's statement brought in (M44). */
class CardStatement extends Model
{
    use Audited, BelongsToCompany;

    public const KINDS = ['fastag' => 'FASTag', 'fuel_card' => 'Fuel card'];

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['period_from' => 'date', 'period_to' => 'date', 'lines' => 'integer', 'skipped' => 'integer'];
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
