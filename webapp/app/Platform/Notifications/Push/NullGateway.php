<?php

namespace App\Platform\Notifications\Push;

/** Pushes switched off (PUSH_DRIVER=none): the inbox still fills. */
class NullGateway implements PushGateway
{
    public function send(string $token, array $message): string
    {
        return self::FAILED;
    }
}
