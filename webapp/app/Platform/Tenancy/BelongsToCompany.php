<?php

namespace App\Platform\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Scope;

/**
 * Put this on every company-owned model.
 *
 * Two jobs:
 *   1. A global scope, so no query can forget `where company_id = ?`.
 *   2. Stamping company_id on create, so nothing is written unscoped.
 *
 * Isolation under one shared database is an application property, so it
 * has to be structural rather than remembered. The other layers are the
 * company-first indexes and the isolation tests.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::creating(function (Model $model) {
            if (empty($model->company_id)) {
                // Throws when there is no company — deliberately. A row
                // written without one would be invisible to every later query.
                $model->company_id = app(CompanyContext::class)->id();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

/**
 * The global scope itself: a separate class, so it can be named in
 * withoutGlobalScope() on the rare, explicit occasions that is right.
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(CompanyContext::class);

        if (! $context->has()) {
            // Fail loudly rather than quietly returning every company's rows.
            throw new \RuntimeException(sprintf(
                'Query on %s without a company in context. This would have read across companies.',
                $model::class
            ));
        }

        $builder->where($model->qualifyColumn('company_id'), $context->id());
    }
}
