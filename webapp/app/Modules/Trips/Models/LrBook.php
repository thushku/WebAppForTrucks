<?php

namespace App\Modules\Trips\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A pre-printed LR book (M21, blueprint 10 §3): leaves prefix + first_no …
 * last_no, handed to a branch, agent or driver on a date. Its used leaves are
 * the consignments that carry its id; cancelled and lost leaves are kept as
 * LrBookLeaf rows with the reason.
 */
class LrBook extends Model
{
    use Audited, BelongsToCompany;

    public const STATUSES = ['active' => 'In use', 'closed' => 'Closed'];

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['issued_on' => 'date', 'first_no' => 'integer', 'last_no' => 'integer'];
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(LrBookLeaf::class)->orderBy('leaf_no');
    }

    public function consignments(): HasMany
    {
        return $this->hasMany(Consignment::class);
    }

    public function size(): int
    {
        return $this->last_no - $this->first_no + 1;
    }

    /** "A-1001 – A-1050" */
    public function rangeLabel(): string
    {
        return $this->prefix . $this->first_no . ' – ' . $this->prefix . $this->last_no;
    }
}
