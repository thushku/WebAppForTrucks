<?php

namespace App\Platform\Notifications\Push;

/**
 * Where a push goes (M20): Firebase Cloud Messaging for Android and iPhone
 * (decision D18), the log while no Firebase project is set, or nowhere.
 */
interface PushGateway
{
    public const SENT = 'sent';

    /** The phone's token is no longer valid (app removed or signed out): forget it. */
    public const INVALID_TOKEN = 'invalid_token';

    public const FAILED = 'failed';

    /**
     * @param array{title: string, body: ?string, data: array<string, string>, collapse: ?string, badge: int} $message
     * @return string one of the constants
     *
     * @throws PushUnavailable when the service is down for now (try again later)
     */
    public function send(string $token, array $message): string;
}
