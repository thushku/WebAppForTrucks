<?php

namespace App\Platform\Tenancy;

use Illuminate\Database\Eloquent\Builder;

/**
 * For records that belong to a branch (M22): `inBranch()` narrows a list to
 * the branch filter, and a branch-limited person cannot open another
 * branch's record by its URL (route binding answers 404).
 */
trait HasBranch
{
    /** Trucks and people with no branch are shared by all branches; trips and bills are not. */
    public static function branchSharedWhenUnassigned(): bool
    {
        return false;
    }

    public function scopeInBranch(Builder $query): Builder
    {
        return app(BranchContext::class)->apply($query, $this->qualifyColumn('branch_id'), static::branchSharedWhenUnassigned());
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $model = parent::resolveRouteBinding($value, $field);

        return $model && app(BranchContext::class)->canSee($model->branch_id !== null ? (int) $model->branch_id : null, static::branchSharedWhenUnassigned()) ? $model : null;
    }
}
