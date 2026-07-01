<?php

use App\Models\Permission;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Schema::dropIfExists('mobile_notifications');
    Schema::dropIfExists('permissions');
    Schema::dropIfExists('students');
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
    Schema::create('permissions', function (Blueprint $table) {
        $table->id();
        $table->string('nip_guru');
        $table->foreignId('student_id');
        $table->string('type');
        $table->date('start_date');
        $table->date('end_date');
        $table->text('keterangan')->nullable();
        $table->string('foto_path')->nullable();
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

test('homeroom teacher can submit permission for own class student', function () {
    $wali = User::query()->create(['nip' => 'WALI-IZIN-001', 'name' => 'Wali Izin', 'password' => 'Password123', 'role' => User::ROLE_GURU]);
    User::query()->create(['nip' => 'BK-IZIN-001', 'name' => 'Guru BK', 'password' => 'Password123', 'role' => User::ROLE_GURU_BK]);
    DB::table('school_classes')->insert(['id' => '8I', 'name' => 'Kelas 8I', 'homeroom_teacher_id' => $wali->nip, 'created_at' => now(), 'updated_at' => now()]);
    $student = Student::query()->create(['nisn' => '6000000001', 'nis' => '6001', 'class_id' => '8I', 'name' => 'Siswa Izin', 'gender' => 'P']);
    Sanctum::actingAs($wali);

    $this->postJson('/api/v1/permissions/store', [
        'student_id' => $student->id,
        'type' => 'sakit',
        'start_date' => '2026-06-30',
        'end_date' => '2026-06-30',
        'keterangan' => 'Sakit',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending');

    expect(Permission::query()->where('student_id', $student->id)->where('type', 'sakit')->exists())->toBeTrue();
});

test('homeroom teacher cannot submit permission for another class student', function () {
    $wali = User::query()->create(['nip' => 'WALI-IZIN-002', 'name' => 'Wali Izin Dua', 'password' => 'Password123', 'role' => User::ROLE_GURU]);
    DB::table('school_classes')->insert(['id' => '8J', 'name' => 'Kelas 8J', 'homeroom_teacher_id' => $wali->nip, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('school_classes')->insert(['id' => '8K', 'name' => 'Kelas 8K', 'created_at' => now(), 'updated_at' => now()]);
    $student = Student::query()->create(['nisn' => '6000000002', 'nis' => '6002', 'class_id' => '8K', 'name' => 'Siswa Kelas Lain', 'gender' => 'L']);
    Sanctum::actingAs($wali);

    $this->postJson('/api/v1/permissions/store', [
        'student_id' => $student->id,
        'type' => 'izin',
        'start_date' => '2026-06-30',
        'end_date' => '2026-06-30',
    ])->assertForbidden();
});
