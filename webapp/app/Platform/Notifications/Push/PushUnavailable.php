<?php

namespace App\Platform\Notifications\Push;

use RuntimeException;

/** The push service is busy or down for now: the push is tried again later. */
class PushUnavailable extends RuntimeException {}
