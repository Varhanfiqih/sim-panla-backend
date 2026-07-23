<?php

namespace App\Services;

use App\Models\MobileDeviceToken;
use App\Models\MobileNotification;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FirebaseCloudMessagingService
{
    public function sendToUser(User $user, MobileNotification $notification): void
    {
        if (! $this->isConfigured()) {
            Log::warning('FCM push skipped: firebase service is not configured', [
                'user_id' => $user->id,
                'notification_id' => $notification->id,
                'project_id' => config('services.firebase.project_id'),
                'service_account_path' => config('services.firebase.service_account_path'),
            ]);

            return;
        }

        $tokens = MobileDeviceToken::query()
            ->where('user_id', $user->id)
            ->pluck('token');

        if ($tokens->isEmpty()) {
            Log::info('FCM push skipped: user has no registered mobile device token', [
                'user_id' => $user->id,
                'notification_id' => $notification->id,
                'type' => $notification->type,
            ]);

            return;
        }

        foreach ($tokens as $token) {
            $this->sendToToken($token, $notification);
        }
    }

    private function sendToToken(string $token, MobileNotification $notification): void
    {
        $projectId = config('services.firebase.project_id');
        $accessToken = $this->accessToken();

        if (! $projectId || ! $accessToken) {
            return;
        }

        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $notification->title,
                    'body' => $notification->body,
                ],
                'data' => $this->dataPayload($notification),
                'android' => [
                    'priority' => 'HIGH',
                    'notification' => [
                        'channel_id' => 'sim_panla_notifications',
                        'sound' => 'default',
                    ],
                ],
            ],
        ];

        try {
            $response = Http::timeout(20)
                ->withToken($accessToken)
                ->acceptJson()
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);
        } catch (\Throwable $error) {
            Log::warning('FCM push request failed before response', [
                'notification_id' => $notification->id,
                'type' => $notification->type,
                'error' => $error->getMessage(),
            ]);

            return;
        }

        if ($response->successful()) {
            Log::info('FCM push sent', [
                'notification_id' => $notification->id,
                'type' => $notification->type,
            ]);

            return;
        }

        Log::warning('FCM push failed', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if (in_array($response->status(), [400, 404], true)) {
            MobileDeviceToken::query()->where('token', $token)->delete();
        }
    }

    private function dataPayload(MobileNotification $notification): array
    {
        return collect($notification->data ?? [])
            ->mapWithKeys(fn ($value, $key) => [(string) $key => $this->stringifyDataValue($value)])
            ->merge([
                'notification_id' => (string) $notification->id,
                'user_id' => (string) $notification->user_id,
                'type' => $notification->type,
            ])
            ->all();
    }

    private function accessToken(): ?string
    {
        return Cache::remember('firebase_fcm_access_token', 3300, function () {
            $serviceAccount = $this->serviceAccount();
            if (! $serviceAccount) {
                return null;
            }

            $now = time();
            $header = $this->base64UrlEncode(json_encode([
                'alg' => 'RS256',
                'typ' => 'JWT',
            ]));
            $claim = $this->base64UrlEncode(json_encode([
                'iss' => $serviceAccount['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            $unsigned = "{$header}.{$claim}";
            $signature = '';
            openssl_sign($unsigned, $signature, $serviceAccount['private_key'], OPENSSL_ALGO_SHA256);
            $jwt = "{$unsigned}.{$this->base64UrlEncode($signature)}";

            try {
                $response = Http::timeout(20)->asForm()->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);
            } catch (\Throwable $error) {
                Log::warning('FCM token request failed before response', [
                    'error' => $error->getMessage(),
                ]);

                return null;
            }

            if (! $response->successful()) {
                Log::warning('FCM token request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json('access_token');
        });
    }

    private function serviceAccount(): ?array
    {
        $path = config('services.firebase.service_account_path');
        if (! $path || ! file_exists($path)) {
            Log::warning('FCM service account file not found', [
                'path' => $path,
            ]);

            return null;
        }

        $json = json_decode(file_get_contents($path), true);
        if (! is_array($json)) {
            Log::warning('FCM service account JSON is invalid', [
                'path' => $path,
            ]);

            return null;
        }

        return $json;
    }

    private function isConfigured(): bool
    {
        return filled(config('services.firebase.project_id'))
            && filled(config('services.firebase.service_account_path'));
    }

    private function stringifyDataValue(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
