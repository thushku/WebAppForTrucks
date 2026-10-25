<?php

namespace App\Platform\Devices;

use Illuminate\Database\Eloquent\Model;

/**
 * The answer to a phone command, kept under the UUID the phone made for it.
 * A command sent again (the answer was lost on a bad network) gets the same
 * answer back and does not run twice.
 */
class ApiCommand extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['result' => 'array', 'http_status' => 'integer', 'created_at' => 'datetime'];
    }
}
