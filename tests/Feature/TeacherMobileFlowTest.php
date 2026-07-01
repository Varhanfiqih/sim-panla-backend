<?php

use App\Models\Attendance;
use App\Models\Student;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Event::fake();

    Schema::dropIfExists('attendances');
    Schema::dropIfExists('teacher_attendances');
    Schema::dropIfExists('mobile_notifications');
    Schema::dropIfExists('students');
    Schema::dropIfExists('school_classes');
    Schema::dropIfExists('personal_access_tokens');
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

    Schema::create('personal_access_tokens', function (Blueprint $table) {
        $table->id();
        $table->morphs('tokenable');
        $table->string('name');
        $table->string('token', 64)->unique();
        $table->text('abilities')->nullable();
        $table->timestamp('last_used_at')->nullable();
        $table->timestamp('expires_at')->nullable();
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

    Schema::create('attendances', function (Blueprint $table) {
        $table->id();
        $table->string('nip_guru');
        $table->string('nisn_student');
        $table->string('kelas');
        $table->string('presensi');
        $table->string('ekstra')->nullable();
        $table->string('kegiatan')->nullable();
        $table->string('keterangan')->nullable();
        $table->timestamps();
    });
});

test('teacher can login to mobile api using nip and password', function () {
    User::query()->create([
        'nip' => 'GURU-010',
        'name' => 'Guru Mobile',
        'password' => 'Password123',
        'role' => User::ROLE_GURU,
    ]);

    $this->postJson('/api/v1/login', [
        'nip' => 'GURU-010',
        'password' => 'Password123',
    ])
        ->assertOk()
        ->assertJsonPath('data.user.role', User::ROLE_GURU)
        ->assertJsonStructure(['data' => ['token']]);
});

test('teacher check in stores daily attendance and notification', function () {
    $teacher = User::query()->create([
        'nip' => 'GURU-011',
        'name' => 'Guru Hadir',
        'password' => 'Password123',
        'role' => User::ROLE_GURU,
    ]);
    Sanctum::actingAs($teacher);

    $this->postJson('/api/v1/teacher/check-in', [
        'status' => 'hadir',
    ])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect(TeacherAttendance::query()->where('user_id', $teacher->id)->where('status', 'hadir')->exists())->toBeTrue()
        ->and(DB::table('mobile_notifications')->where('user_id', $teacher->id)->where('type', 'teacher_checkin')->exists())->toBeTrue();
});

test('qr attendance scan records student attendance and rejects duplicate scan', function () {
    $teacher = User::query()->create([
        'nip' => 'GURU-012',
        'name' => 'Guru Scanner',
        'password' => 'Password123',
        'role' => User::ROLE_GURU,
    ]);
    Student::query()->create([
        'nisn' => '0099887766',
        'nis' => '9988',
        'class_id' => '8A',
        'name' => 'Siswa QR',
        'gender' => 'P',
        'qr_code' => 'QR-0099887766',
    ]);
    Sanctum::actingAs($teacher);

    $payload = [
        'qr_code' => 'QR-0099887766',
        'type' => 'Masuk',
        'kegiatan' => 'Gerbang',
    ];

    $this->postJson('/api/v1/attendance/scan', $payload)
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect(Attendance::query()->where('nisn_student', '0099887766')->where('keterangan', 'Masuk')->exists())->toBeTrue();

    $this->postJson('/api/v1/attendance/scan', $payload)
        ->assertStatus(400)
        ->assertJsonPath('status', 'error');
});
