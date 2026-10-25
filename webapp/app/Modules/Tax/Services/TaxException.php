<?php

namespace App\Modules\Tax\Services;

use RuntimeException;

/**
 * The engine cannot decide the tax, and says why in words for the person
 * issuing the invoice ("Set the transport type on the load"). It never falls
 * back to 0% or to a guess (blueprint 11 §3 step 5).
 */
class TaxException extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'tax')
    {
        parent::__construct($message);
    }
}
