<?php

namespace App\Platform\Authorization\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A company's role ("Manager", "Driver"…): a named bundle of permissions. */
class Role extends Model
{
    use Audited, BelongsToCompany;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'level' => 'integer'];
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }
}
