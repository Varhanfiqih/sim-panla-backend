<?php

use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Schema::dropIfExists('teacher_attendances');
    Schema::dropIfExists('mobile_notifications');
    Schema::dropIfExists('schedules');
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('nip')->unique()->nullable();
        $table->string('name');
        $table->string('password');
        $table->timestamp('password_changed_at')->nullable();
        $table->string('role')->default(User::ROLE_GURU);
        $table->boolean('is_inval_piket')->default(false);
        $table->rememberToken();
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

    Schema::create('mobile_notifications', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id');
        $table->string('type');
        $table->string('title');
        $table->text('body');
        $table->json('data')->nullable();
        $table->timestamp('read_at')->nullable();
        $table->timestamps();
    });

    Schema::create('schedules', function (Blueprint $table) {
        $table->id();
        $table->string('day_of_week')->nullable();
        $table->string('class_id')->nullable();
        $table->foreignId('subject_id')->nullable();
        $table->foreignId('time_slot_id')->nullable();
        $table->string('teacher_id')->nullable();
        $table->timestamps();
    });
});

test('teacher can check in as present once per day', function () {
    $teacher = User::query()->create([
        'nip' => 'GURU-CHECKIN-001',
        'name' => 'Guru Hadir',
        'password' => 'Password123',
        'role' => User::ROLE_GURU,
    ]);
    Sanctum::actingAs($teacher);

    $this->postJson('/api/v1/teacher/check-in', ['status' => 'hadir'])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect(TeacherAttendance::query()->where('user_id', $teacher->id)->where('status', 'hadir')->count())->toBe(1)
        ->and(DB::table('mobile_notifications')->where('user_id', $teacher->id)->where('type', 'teacher_checkin')->exists())->toBeTrue();
});

test('teacher absence requires reason and stores absence reason', function () {
    $teacher = User::query()->create([
        'nip' => 'GURU-CHECKIN-002',
        'name' => 'Guru Izin',
        'password' => 'Password123',
        'role' => User::ROLE_GURU,
    ]);
    Sanctum::actingAs($teacher);

    $this->postJson('/api/v1/teacher/check-in', ['status' => 'tidak_hadir'])
        ->assertUnprocessable();

    $this->postJson('/api/v1/teacher/check-in', [
        'status' => 'tidak_hadir',
        'reason' => 'Sakit',
    ])->assertOk();

    expect(TeacherAttendance::query()->where('user_id', $teacher->id)->value('reason'))->toBe('Sakit');
});
