<?php

use App\Events\MobileNotificationPushed;
use App\Models\MobileNotification;
use Carbon\Carbon;

test('mobile notification event broadcasts on the user notification channel', function () {
    $notification = new MobileNotification(['user_id' => 15]);

    $channels = (new MobileNotificationPushed($notification))->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0]->name)->toBe('notifications.15');
});

test('mobile notification event exposes the expected broadcast payload', function () {
    $notification = new MobileNotification();
    $notification->setRawAttributes([
        'id' => 21,
        'user_id' => 15,
        'type' => 'teacher_checkin',
        'title' => 'Check-in Berhasil',
        'body' => 'Kehadiran Anda hari ini berhasil dikonfirmasi.',
        'data' => json_encode(['attendance_id' => 9]),
        'created_at' => Carbon::parse('2026-07-12 08:15:00', 'Asia/Jakarta'),
    ], true);

    $payload = (new MobileNotificationPushed($notification))->broadcastWith();

    expect($payload)->toMatchArray([
        'id' => 21,
        'user_id' => 15,
        'type' => 'teacher_checkin',
        'title' => 'Check-in Berhasil',
        'body' => 'Kehadiran Anda hari ini berhasil dikonfirmasi.',
        'data' => ['attendance_id' => 9],
        'is_read' => false,
    ])
        ->and($payload['created_at'])->toBe('2026-07-12T08:15:00+07:00');
});
