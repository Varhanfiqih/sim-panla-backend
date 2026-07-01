<?php

use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('schedules');
    Schema::dropIfExists('time_slots');
    Schema::dropIfExists('subjects');
    Schema::dropIfExists('school_classes');
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
        $table->string('type')->nullable();
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

test('admin can create teaching schedule with teacher class subject and time slot', function () {
    $teacher = User::query()->create([
        'nip' => 'GURU-301',
        'name' => 'Guru Jadwal',
        'password' => 'Password123',
        'role' => User::ROLE_GURU,
    ]);
    SchoolClass::query()->create(['id' => '9A', 'name' => 'Kelas 9A']);
    $subject = Subject::query()->create(['name' => 'Bahasa Indonesia']);
    $slot = TimeSlot::query()->create([
        'start_time' => '09:00:00',
        'end_time' => '09:45:00',
        'type' => 'KBM',
    ]);

    $schedule = Schedule::query()->create([
        'day_of_week' => 'SELASA',
        'class_id' => '9A',
        'time_slot_id' => $slot->id,
        'subject_id' => $subject->id,
        'teacher_id' => $teacher->nip,
    ]);

    expect($schedule->teacher?->name)->toBe('Guru Jadwal')
        ->and($schedule->schoolClass?->name)->toBe('Kelas 9A')
        ->and($schedule->subject?->name)->toBe('Bahasa Indonesia')
        ->and($schedule->timeSlot?->start_time)->toBe('09:00:00');
});

test('admin can update teaching schedule time slot', function () {
    $teacher = User::query()->create([
        'nip' => 'GURU-302',
        'name' => 'Guru Jadwal Dua',
        'password' => 'Password123',
        'role' => User::ROLE_GURU,
    ]);
    SchoolClass::query()->create(['id' => '9B', 'name' => 'Kelas 9B']);
    $subject = Subject::query()->create(['name' => 'IPA']);
    $slotOne = TimeSlot::query()->create(['start_time' => '07:00:00', 'end_time' => '07:45:00', 'type' => 'KBM']);
    $slotTwo = TimeSlot::query()->create(['start_time' => '08:00:00', 'end_time' => '08:45:00', 'type' => 'KBM']);

    $schedule = Schedule::query()->create([
        'day_of_week' => 'RABU',
        'class_id' => '9B',
        'time_slot_id' => $slotOne->id,
        'subject_id' => $subject->id,
        'teacher_id' => $teacher->nip,
    ]);

    $schedule->update(['time_slot_id' => $slotTwo->id]);

    expect($schedule->fresh()->timeSlot?->start_time)->toBe('08:00:00');
});
