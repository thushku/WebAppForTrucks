<?php

namespace App\Platform\Notifications\Push;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Firebase Cloud Messaging, HTTP v1 (decision D18). Signs in with the
 * Firebase project's service-account key (a JSON file kept outside the
 * repository, FIREBASE_CREDENTIALS) — a short JWT signed with PHP's own
 * OpenSSL is exchanged for an hour's access token, kept in the cache — and
 * sends one message per phone token. No SDK or package is needed.
 */
class FcmGateway implements PushGateway
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /** @param array{project_id: string, client_email: string, private_key: string, token_uri?: string} $key */
    public function __construct(private array $key) {}

    /** From config('services.fcm'); null when the key file is missing or not a service-account key. */
    public static function fromConfig(): ?self
    {
        $path = (string) config('services.fcm.credentials');
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            return null;
        }
        $key = json_decode((string) file_get_contents($path), true);
        if (! is_array($key) || empty($key['client_email']) || empty($key['private_key']) || empty($key['project_id'] ?? config('services.fcm.project_id'))) {
            return null;
        }
        $key['project_id'] = config('services.fcm.project_id') ?: $key['project_id'];

        return new self($key);
    }

    public function send(string $token, array $message): string
    {
        $response = $this->post($token, $message);
        if ($response->status() === 401) {
            // The access token ran out early: once more with a new one.
            Cache::forget($this->cacheKey());
            $response = $this->post($token, $message);
        }
        if ($response->successful()) {
            return self::SENT;
        }

        $code = collect($response->json('error.details', []))->pluck('errorCode')->filter()->first();
        $status = (string) $response->json('error.status', '');
        if ($code === 'UNREGISTERED' || $status === 'NOT_FOUND'
            || ($status === 'INVALID_ARGUMENT' && str_contains(mb_strtolower((string) $response->json('error.message', '')), 'registration token'))) {
            return self::INVALID_TOKEN;
        }
        if ($response->status() === 429 || $response->serverError()) {
            throw new PushUnavailable("FCM answered {$response->status()} {$status}");
        }

        return self::FAILED;
    }

    private function post(string $token, array $message): Response
    {
        $data = array_map('strval', $message['data']);
        $body = ['message' => array_filter([
            'token' => $token,
            'notification' => array_filter(['title' => $message['title'], 'body' => $message['body']]),
            'data' => $data ?: null,
            // Low notices wait for the phone's next wake-up; medium and high wake it (M26).
            'android' => array_filter(['priority' => ($message['priority'] ?? 'medium') === 'low' ? 'normal' : 'high', 'collapse_key' => $message['collapse'],
                'notification' => array_filter(['channel_id' => config('services.fcm.android_channel', 'fleet'), 'tag' => $message['collapse'], 'sound' => 'default'])]),
            'apns' => ['headers' => array_filter(['apns-collapse-id' => $message['collapse'] ? mb_substr($message['collapse'], 0, 64) : null]),
                'payload' => ['aps' => ['sound' => 'default', 'badge' => $message['badge']]]],
        ])];

        try {
            return Http::withToken($this->accessToken())->acceptJson()->timeout(10)
                ->post('https://fcm.googleapis.com/v1/projects/' . rawurlencode($this->key['project_id']) . '/messages:send', $body);
        } catch (ConnectionException $e) {
            throw new PushUnavailable($e->getMessage(), 0, $e);
        }
    }

    private function accessToken(): string
    {
        $cached = Cache::get($this->cacheKey());
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }
        $uri = $this->key['token_uri'] ?? 'https://oauth2.googleapis.com/token';
        try {
            $response = Http::asForm()->timeout(10)->post($uri, ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $this->jwt($uri)]);
        } catch (ConnectionException $e) {
            throw new PushUnavailable($e->getMessage(), 0, $e);
        }
        if (! $response->successful() || ! is_string($response->json('access_token'))) {
            if ($response->serverError()) {
                throw new PushUnavailable("Google sign-in answered {$response->status()}");
            }
            throw new RuntimeException('Firebase refused the service-account key: ' . $response->json('error_description', $response->json('error', (string) $response->status())));
        }
        Cache::put($this->cacheKey(), $response->json('access_token'), max(60, (int) $response->json('expires_in', 3600) - 300));

        return $response->json('access_token');
    }

    /** The signed request for an access token (RS256), valid for an hour. */
    private function jwt(string $audience): string
    {
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $now = time();
        $input = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.'
            . $b64(json_encode(['iss' => $this->key['client_email'], 'scope' => self::SCOPE, 'aud' => $audience, 'iat' => $now, 'exp' => $now + 3600]));
        $privateKey = openssl_pkey_get_private($this->key['private_key']);
        if ($privateKey === false || ! openssl_sign($input, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('The Firebase service-account key could not be read.');
        }

        return $input . '.' . $b64($signature);
    }

    private function cacheKey(): string
    {
        return 'fcm:access:' . sha1($this->key['client_email']);
    }
}
