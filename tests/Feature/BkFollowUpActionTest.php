<?php

use App\Models\BkAction;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

uses()->group('bk-follow-up');

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
        'nip' => 'BK-FOLLOW-001',
        'name' => 'Guru BK Follow',
        'password' => 'Password123',
        'role' => User::ROLE_GURU_BK,
    ]);
    DB::table('school_classes')->insert([
        'id' => '9F',
        'name' => 'Kelas 9F',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $student = Student::query()->create([
        'nisn' => '7100000001',
        'nis' => '7101',
        'class_id' => '9F',
        'name' => 'Siswa Follow',
        'gender' => 'P',
    ]);
    Sanctum::actingAs($bk);

    $this->postJson('/api/v1/bk/action', [
        'student_id' => $student->id,
        'action_type' => 'konseling',
        'notes' => 'Catatan tindak lanjut.',
    ])->assertCreated();

    expect(BkAction::query()->where('student_id', $student->id)->where('action_type', 'konseling')->exists())->toBeTrue();

    $this->getJson("/api/v1/bk/history/{$student->id}")
        ->assertOk()
        ->assertJsonPath('total', 1);
});
