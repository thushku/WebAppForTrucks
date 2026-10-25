<?php

namespace App\Platform\Notifications\Push;

use Illuminate\Support\Facades\Log;

/** Without a Firebase project: each push is written to the log, so it can be seen while building. */
class LogGateway implements PushGateway
{
    public function send(string $token, array $message): string
    {
        Log::info('Push (log driver)', ['token' => '…' . substr($token, -8), 'title' => $message['title'], 'body' => $message['body'], 'data' => $message['data']]);

        return self::SENT;
    }
}
