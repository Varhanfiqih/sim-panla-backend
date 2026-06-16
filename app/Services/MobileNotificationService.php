<?php

namespace App\Services;

use App\Events\MobileNotificationPushed;
use App\Models\MobileNotification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MobileNotificationService
{
    public function send(
        User $user,
        string $type,
        string $title,
        string $body,
        array $data = [],
    ): MobileNotification {
        $notification = MobileNotification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data ?: null,
        ]);

        try {
            event(new MobileNotificationPushed($notification));
        } catch (\Throwable $error) {
            Log::warning('Realtime mobile notification failed: '.$error->getMessage());
        }

        try {
            app(FirebaseCloudMessagingService::class)->sendToUser($user, $notification);
        } catch (\Throwable $error) {
            Log::warning('FCM mobile notification failed: '.$error->getMessage());
        }

        return $notification;
    }

    public function sendToUsers(
        Collection $users,
        string $type,
        string $title,
        string $body,
        array $data = [],
    ): void {
        $users->each(fn (User $user) => $this->send($user, $type, $title, $body, $data));
    }
}
