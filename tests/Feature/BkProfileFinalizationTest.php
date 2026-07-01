<?php

use App\Models\BkAction;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Schema::dropIfExists('bk_actions');
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

    Schema::create('bk_actions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('student_id');
        $table->string('handled_by_user_id');
        $table->string('action_type');
        $table->text('notes')->nullable();
        $table->string('status_sebelum')->nullable();
        $table->string('status_sesudah')->nullable();
        $table->date('tanggal_kejadian')->nullable();
        $table->timestamps();
    });
});

test('guru bk can store and view student follow up action history', function () {
    $bk = User::query()->create([
        'nip' => 'BK-010',
        'name' => 'Guru BK',
        'password' => 'Password123',
        'role' => User::ROLE_GURU_BK,
    ]);
    DB::table('school_classes')->insert([
        'id' => '8C',
        'name' => 'Kelas 8C',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $student = Student::query()->create([
        'nisn' => '0077007700',
        'nis' => '7700',
        'class_id' => '8C',
        'name' => 'Siswa Bimbingan',
        'gender' => 'P',
    ]);
    Sanctum::actingAs($bk);

    $this->postJson('/api/v1/bk/action', [
        'student_id' => $student->id,
        'action_type' => 'konseling',
        'notes' => 'Konseling kedisiplinan.',
        'tanggal_kejadian' => '2026-06-30',
    ])
        ->assertCreated()
        ->assertJsonPath('status', 'success');

    expect(BkAction::query()->where('student_id', $student->id)->where('action_type', 'konseling')->exists())->toBeTrue();

    $this->getJson("/api/v1/bk/history/{$student->id}")
        ->assertOk()
        ->assertJsonPath('student.name', 'Siswa Bimbingan')
        ->assertJsonPath('total', 1);
});

test('non bk user cannot store bk follow up action', function () {
    $guru = User::query()->create([
        'nip' => 'GURU-020',
        'name' => 'Guru Mapel',
        'password' => 'Password123',
        'role' => User::ROLE_GURU,
    ]);
    DB::table('school_classes')->insert([
        'id' => '8D',
        'name' => 'Kelas 8D',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $student = Student::query()->create([
        'nisn' => '0088008800',
        'nis' => '8800',
        'class_id' => '8D',
        'name' => 'Siswa Lain',
        'gender' => 'L',
    ]);
    Sanctum::actingAs($guru);

    $this->postJson('/api/v1/bk/action', [
        'student_id' => $student->id,
        'action_type' => 'konseling',
        'notes' => 'Tidak boleh tersimpan.',
    ])->assertForbidden();
});
