<?php

namespace App\Platform\Auth\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A computer someone trusted on the code screen ("Trust this computer for
 * 30 days"). The browser keeps a random token in a cookie; this row keeps
 * only its hash.
 */
class TrustedBrowser extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'last_used_at' => 'datetime'];
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }
}
