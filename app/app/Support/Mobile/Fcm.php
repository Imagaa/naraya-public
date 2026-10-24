<?php

namespace App\Support\Mobile;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Notification channel for the Naraya Sales Android app: FCM HTTP v1, signed with the Firebase service account
 * (config services.fcm.credentials). Web Push can't reach the app's
 * WebView, so the app registers an FCM token (device_tokens) and a notice's toFcm() goes to every one of them.
 * Sent synchronously like the rest (no queue worker); a failure is logged and never breaks the action that notified.
 */
class Fcm
{
    public function send(object $notifiable, Notification $notification): void
    {
        $credentials = self::credentials();
        if (! $credentials) {
            return;
        }

        $message = $notification->toFcm($notifiable);
        foreach ($notifiable->deviceTokens as $device) {
            try {
                $response = Http::withToken(self::accessToken($credentials))->timeout(5)
                    ->post("https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send", [
                        'message' => ['token' => $device->token] + $message,
                    ]);
                if ($response->status() === 404) {
                    $device->delete(); // UNREGISTERED: the app was uninstalled or its token replaced
                } elseif ($response->failed()) {
                    Log::warning('FCM send failed', ['status' => $response->status(), 'body' => $response->body()]);
                }
            } catch (\Throwable $e) {
                Log::warning('FCM send failed', ['error' => $e->getMessage()]);
            }
        }
    }

    /** @return array{project_id: string, client_email: string, private_key: string, token_uri: string}|null */
    private static function credentials(): ?array
    {
        $path = config('services.fcm.credentials');

        return $path && is_file($path) ? json_decode(file_get_contents($path), true) : null;
    }

    /** OAuth2 token from a self-signed service-account JWT (RS256), cached until shortly before it expires. */
    private static function accessToken(array $credentials): string
    {
        return Cache::remember('fcm-access-token', 3000, function () use ($credentials) {
            $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
            $now = time();
            $unsigned = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$b64(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $credentials['token_uri'],
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);

            return Http::asForm()->timeout(5)->post($credentials['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsigned.'.'.$b64($signature),
            ])->throw()->json('access_token');
        });
    }
}
