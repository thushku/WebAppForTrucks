<?php

namespace App\Modules\Trips\Models;

use App\Modules\People\Models\Person;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A person on a trip with their role on it (driver, co-driver, helper). Changes are audited on the trip. */
class TripCrew extends Model
{
    use BelongsToCompany;

    protected $table = 'trip_crew';

    public const ROLES = ['driver' => 'Driver', 'co_driver' => 'Co-driver', 'helper' => 'Helper'];

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime', 'rejected_at' => 'datetime'];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
