<?php

namespace App\Modules\Trips\Models;

use App\Modules\People\Models\Person;
use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cash (or UPI / bank) given to a crew member for the trip. Settled against their entries (M7). */
class TripAdvance extends Model
{
    use Audited, BelongsToCompany;

    public const MODES = ['cash' => 'Cash', 'upi' => 'UPI', 'bank' => 'Bank transfer'];

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['given_on' => 'date', 'amount' => 'decimal:2'];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function getLabelAttribute(): string
    {
        return 'Advance ' . $this->amount;
    }
}
