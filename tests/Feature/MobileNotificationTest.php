<?php

use App\Models\MobileNotification;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Schema::dropIfExists('mobile_notifications');
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('nip')->unique();
        $table->string('name');
        $table->string('password');
        $table->timestamp('password_changed_at')->nullable();
        $table->string('role');
        $table->boolean('is_inval_piket')->default(false);
        $table->rememberToken();
        $table->timestamps();
    });

    Schema::create('mobile_notifications', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('type');
        $table->string('title');
        $table->text('body');
        $table->json('data')->nullable();
        $table->timestamp('read_at')->nullable();
        $table->timestamps();
    });

    Schema::create('teacher_attendances', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id');
        $table->date('date');
        $table->string('status');
        $table->string('reason')->nullable();
        $table->text('description')->nullable();
        $table->string('attachment')->nullable();
        $table->timestamps();
    });

    Schema::create('school_classes', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('name');
        $table->string('homeroom_teacher_id')->nullable();
        $table->timestamps();
    });

    Schema::create('subjects', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('time_slots', function (Blueprint $table) {
        $table->id();
        $table->time('start_time');
        $table->time('end_time');
        $table->string('type');
        $table->timestamps();
    });

    Schema::create('schedules', function (Blueprint $table) {
        $table->id();
        $table->string('day_of_week');
        $table->string('class_id');
        $table->foreignId('time_slot_id');
        $table->foreignId('subject_id');
        $table->string('teacher_id');
        $table->string('keterangan')->nullable();
        $table->timestamps();
    });
});

test('user can list and mark their notification as read', function () {
    $user = User::query()->create([
        'nip' => 'guru-notifikasi',
        'name' => 'Guru Notifikasi',
        'password' => 'Password123',
        'role' => User::ROLE_GURU,
    ]);
    $notification = MobileNotification::query()->create([
        'user_id' => $user->id,
        'type' => 'journal_submitted',
        'title' => 'Jurnal Berhasil Disimpan',
        'body' => 'Jurnal kelas berhasil dikirim.',
    ]);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.id', $notification->id)
        ->assertJsonPath('data.0.is_read', false);

    $this->postJson("/api/v1/notifications/{$notification->id}/read")
        ->assertOk();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('user cannot modify another users notification', function () {
    $owner = User::query()->create([
        'nip' => 'guru-owner',
        'name' => 'Guru Owner',
        'password' => 'Password123',
        'role' => User::ROLE_GURU,
    ]);
    $other = User::query()->create([
        'nip' => 'guru-other',
        'name' => 'Guru Other',
        'password' => 'Password123',
        'role' => User::ROLE_GURU,
    ]);
    $notification = MobileNotification::query()->create([
        'user_id' => $owner->id,
        'type' => 'inval_claim',
        'title' => 'Inval',
        'body' => 'Jadwal inval diambil.',
    ]);
    Sanctum::actingAs($other);

    $this->postJson("/api/v1/notifications/{$notification->id}/read")
        ->assertNotFound();
    $this->deleteJson("/api/v1/notifications/{$notification->id}")
        ->assertNotFound();
});

test('user can mark and delete notification through post action endpoint', function () {
    $user = User::query()->create([
        'nip' => 'guru-action',
        'name' => 'Guru Action',
        'password' => 'Password123',
        'role' => User::ROLE_GURU,
    ]);
    Sanctum::actingAs($user);

    $readNotification = MobileNotification::query()->create([
        'user_id' => $user->id,
        'type' => 'teacher_checkin',
        'title' => 'Check-in Berhasil',
        'body' => 'Kehadiran berhasil dikonfirmasi.',
    ]);

    $this->postJson("/api/v1/notifications/{$readNotification->id}/action", [
        'action' => 'read',
    ])->assertOk();

    expect($readNotification->fresh()->read_at)->not->toBeNull();

    $deleteNotification = MobileNotification::query()->create([
        'user_id' => $user->id,
        'type' => 'journal_submitted',
        'title' => 'Jurnal Berhasil',
        'body' => 'Jurnal berhasil dikirim.',
    ]);

    $this->postJson("/api/v1/notifications/{$deleteNotification->id}/action", [
        'action' => 'delete',
    ])->assertOk();

    expect($deleteNotification->fresh())->toBeNull();
});

test('teacher absence notifies only teachers with an available inval slot', function () {
    $absentTeacher = User::query()->create([
        'nip' => 'guru-absen',
        'name' => 'Guru Tidak Hadir',
        'password' => 'Password123',
        'role' => User::ROLE_GURU,
    ]);
    $availableTeacher = User::query()->create([
        'nip' => 'guru-tersedia',
        'name' => 'Guru Tersedia',
        'password' => 'Password123',
        'role' => User::ROLE_GURU,
    ]);
    $busyTeacher = User::query()->create([
        'nip' => 'guru-bentrok',
        'name' => 'Guru Bentrok',
        'password' => 'Password123',
        'role' => User::ROLE_GURU,
    ]);

    $day = strtoupper(now()->locale('id')->isoFormat('dddd'));
    DB::table('school_classes')->insert([
        'id' => '8A',
        'name' => '8A',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('subjects')->insert([
        'id' => 1,
        'name' => 'Matematika',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('time_slots')->insert([
        'id' => 1,
        'start_time' => '08:00:00',
        'end_time' => '08:45:00',
        'type' => 'KBM',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('schedules')->insert([
        [
            'day_of_week' => $day,
            'class_id' => '8A',
            'time_slot_id' => 1,
            'subject_id' => 1,
            'teacher_id' => $absentTeacher->nip,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'day_of_week' => $day,
            'class_id' => '8A',
            'time_slot_id' => 1,
            'subject_id' => 1,
            'teacher_id' => $busyTeacher->nip,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    Sanctum::actingAs($absentTeacher);

    $this->postJson('/api/v1/teacher/check-in', [
        'status' => 'tidak_hadir',
        'reason' => 'Sakit',
    ])->assertOk();

    expect(
        MobileNotification::query()
            ->where('user_id', $availableTeacher->id)
            ->where('type', 'inval_available')
            ->exists(),
    )->toBeTrue()
        ->and(
            MobileNotification::query()
                ->where('user_id', $busyTeacher->id)
                ->where('type', 'inval_available')
                ->exists(),
        )->toBeFalse();
});
