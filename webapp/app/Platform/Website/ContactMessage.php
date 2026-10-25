<?php

namespace App\Platform\Website;

use Illuminate\Database\Eloquent\Model;

/** A message sent from the website's Contact page. Platform-level: read in the console. */
class ContactMessage extends Model
{
    public const TOPICS = ['sales' => 'A demo or pricing', 'support' => 'Help with my account', 'partner' => 'Partnership', 'other' => 'Something else'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }
}
