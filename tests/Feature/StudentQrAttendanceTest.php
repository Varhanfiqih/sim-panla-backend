<?php

use App\Models\Attendance;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Event::fake();

    Schema::dropIfExists('attendances');
    Schema::dropIfExists('students');
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

test('teacher can record student attendance using qr code', function () {
    $teacher = User::query()->create([
        'nip' => 'GURU-QR-001',
        'name' => 'Guru QR',
        'password' => 'Password123',
        'role' => User::ROLE_GURU,
    ]);
    Student::query()->create([
        'nisn' => '3000000001',
        'nis' => '3001',
        'class_id' => '7C',
        'name' => 'Siswa QR',
        'gender' => 'L',
        'qr_code' => 'QR-3000000001',
    ]);
    Sanctum::actingAs($teacher);

    $this->postJson('/api/v1/attendance/scan', [
        'qr_code' => 'QR-3000000001',
        'type' => 'Masuk',
        'kegiatan' => 'Gerbang',
    ])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect(Attendance::query()->where('nisn_student', '3000000001')->where('keterangan', 'Masuk')->exists())->toBeTrue();
});

test('duplicate qr attendance scan is rejected', function () {
    $teacher = User::query()->create([
        'nip' => 'GURU-QR-002',
        'name' => 'Guru QR Dua',
        'password' => 'Password123',
        'role' => User::ROLE_GURU,
    ]);
    Student::query()->create([
        'nisn' => '3000000002',
        'nis' => '3002',
        'class_id' => '7C',
        'name' => 'Siswa QR Dua',
        'gender' => 'P',
        'qr_code' => 'QR-3000000002',
    ]);
    Sanctum::actingAs($teacher);

    $payload = ['qr_code' => 'QR-3000000002', 'type' => 'Masuk', 'kegiatan' => 'Gerbang'];

    $this->postJson('/api/v1/attendance/scan', $payload)->assertOk();
    $this->postJson('/api/v1/attendance/scan', $payload)
        ->assertStatus(400)
        ->assertJsonPath('status', 'error');
});
