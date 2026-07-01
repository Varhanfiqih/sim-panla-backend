<?php

use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('schedules');
    Schema::dropIfExists('time_slots');
    Schema::dropIfExists('subjects');
    Schema::dropIfExists('students');
    Schema::dropIfExists('school_classes');
    Schema::dropIfExists('users');
    Schema::dropIfExists('app_settings');

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

    Schema::create('students', function (Blueprint $table) {
        $table->id();
        $table->string('nisn')->unique();
        $table->string('nis')->nullable();
        $table->string('class_id');
        $table->string('name');
        $table->string('gender');
        $table->string('qr_code')->nullable();
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

    Schema::create('app_settings', function (Blueprint $table) {
        $table->id();
        $table->string('key')->unique();
        $table->text('value')->nullable();
        $table->timestamps();
    });
});

test('admin operational data links teacher student class and schedule', function () {
    $teacher = User::query()->create([
        'nip' => 'GURU-001',
        'name' => 'Guru Matematika',
        'password' => 'Password123',
        'role' => User::ROLE_GURU,
    ]);

    SchoolClass::query()->create([
        'id' => '7A',
        'name' => 'Kelas 7A',
        'homeroom_teacher_id' => $teacher->nip,
    ]);

    Student::query()->create([
        'nisn' => '0012345678',
        'nis' => '1234',
        'class_id' => '7A',
        'name' => 'Adam Rizky',
        'gender' => 'L',
    ]);

    $subject = Subject::query()->create(['name' => 'Matematika']);
    $slot = TimeSlot::query()->create([
        'start_time' => '08:00:00',
        'end_time' => '08:45:00',
        'type' => 'KBM',
    ]);

    $schedule = Schedule::query()->create([
        'day_of_week' => 'SENIN',
        'class_id' => '7A',
        'time_slot_id' => $slot->id,
        'subject_id' => $subject->id,
        'teacher_id' => $teacher->nip,
    ]);

    expect($teacher->homeroomClass?->id)->toBe('7A')
        ->and(Student::query()->where('class_id', '7A')->count())->toBe(1)
        ->and($schedule->teacher?->nip)->toBe($teacher->nip)
        ->and($schedule->schoolClass?->name)->toBe('Kelas 7A')
        ->and($schedule->subject?->name)->toBe('Matematika');
});

test('attendance configuration can be stored as app setting', function () {
    DB::table('app_settings')->insert([
        'key' => 'jam_masuk',
        'value' => '07:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('app_settings')->where('key', 'jam_masuk')->value('value'))
        ->toBe('07:00:00');
});
