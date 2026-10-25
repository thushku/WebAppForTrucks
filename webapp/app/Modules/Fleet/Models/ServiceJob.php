<?php

namespace App\Modules\Fleet\Models;

use App\Modules\Parties\Models\Party;
use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Work done on a truck: parts and labour, by a workshop vendor. The bill is
 * posted to the books (vendor ledger, truck ledger) when accounting arrives
 * (M11).
 */
class ServiceJob extends Model
{
    use Audited, BelongsToCompany;

    public const KINDS = ['scheduled' => 'Scheduled service', 'breakdown' => 'Breakdown', 'accident' => 'Accident repair', 'other' => 'Other'];

    public const STATUSES = ['open' => 'Open', 'done' => 'Done', 'cancelled' => 'Cancelled'];

    protected $guarded = ['id', 'company_id'];

    protected $attributes = ['kind' => 'scheduled', 'status' => 'open', 'parts_total' => 0, 'labour_total' => 0, 'total' => 0];

    protected function casts(): array
    {
        return [
            'job_date' => 'date', 'completed_at' => 'datetime', 'odometer_km' => 'integer',
            'parts_total' => 'decimal:2', 'labour_total' => 'decimal:2', 'total' => 'decimal:2',
        ];
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'vendor_party_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ServiceSchedule::class, 'service_schedule_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ServiceJobItem::class);
    }
}
