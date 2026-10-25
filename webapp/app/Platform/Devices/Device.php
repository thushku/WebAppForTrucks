<?php

namespace App\Platform\Devices;

use App\Models\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A signed-in phone (crew app or owner app). One per account and device id;
 * a person who drives for two companies has one device row and picks the
 * company per request (X-Company). Not company-scoped for that reason.
 */
class Device extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['push_token'];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime', 'last_seen_at' => 'datetime', 'last_sync_at' => 'datetime',
            'last_gps_at' => 'datetime', 'revoked_at' => 'datetime', 'push_token' => 'encrypted',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
