<?php

use App\Models\InvalAssignment;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

uses()->group('bk-inval');

beforeEach(function () {
    Schema::dropIfExists('inval_assignments');
    Schema::dropIfExists('mobile_notifications');
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
        $table->timestamps();
    });
    Schema::create('schedules', function (Blueprint $table) {
        $table->id();
        $table->string('day_of_week')->nullable();
        $table->string('class_id');
        $table->foreignId('subject_id');
        $table->foreignId('time_slot_id');
        $table->string('teacher_id')->nullable();
        $table->timestamps();
    });
    Schema::create('inval_assignments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('schedule_id');
        $table->string('replacement_teacher_id');
        $table->date('date');
        $table->string('status');
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
});

function seedBkInvalData(): User
{
    $absentTeacher = User::query()->create(['nip' => 'GURU-ABSEN-BK-001', 'name' => 'Guru Absen BK', 'password' => 'Password123', 'role' => User::ROLE_GURU]);
    $bk = User::query()->create(['nip' => 'BK-INVAL-001', 'name' => 'Guru BK Inval', 'password' => 'Password123', 'role' => User::ROLE_GURU_BK]);
    DB::table('school_classes')->insert(['id' => '8K', 'name' => 'Kelas 8K', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('subjects')->insert(['id' => 1, 'name' => 'Matematika', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('time_slots')->insert(['id' => 1, 'start_time' => '11:00:00', 'end_time' => '11:45:00', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('schedules')->insert(['id' => 1, 'class_id' => '8K', 'subject_id' => 1, 'time_slot_id' => 1, 'teacher_id' => $absentTeacher->nip, 'created_at' => now(), 'updated_at' => now()]);

    return $bk;
}

test('guru bk can claim available inval class using same inval flow', function () {
    $bk = seedBkInvalData();
    Sanctum::actingAs($bk);

    $this->postJson('/api/v1/journal/inval-claim', ['schedule_ids' => [1]])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect(InvalAssignment::query()->where('schedule_id', 1)->where('replacement_teacher_id', $bk->nip)->exists())->toBeTrue();
});
