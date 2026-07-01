<?php

use App\Models\Journal;
use App\Models\Student;
use App\Models\StudentNote;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Schema::dropIfExists('bk_actions');
    Schema::dropIfExists('student_notes');
    Schema::dropIfExists('journals');
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
    Schema::create('journals', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('schedule_id')->nullable();
        $table->string('user_id');
        $table->boolean('is_inval')->default(false);
        $table->text('material')->nullable();
        $table->string('cleanliness')->nullable();
        $table->string('attachment_path')->nullable();
        $table->timestamps();
    });
    Schema::create('student_notes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('journal_id');
        $table->foreignId('student_id');
        $table->string('note_type');
        $table->text('notes')->nullable();
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

test('guru bk dashboard can list absent students from journal notes', function () {
    $teacher = User::query()->create(['nip' => 'GURU-BK-DASH-001', 'name' => 'Guru Mapel', 'password' => 'Password123', 'role' => User::ROLE_GURU]);
    $bk = User::query()->create(['nip' => 'BK-DASH-001', 'name' => 'Guru BK', 'password' => 'Password123', 'role' => User::ROLE_GURU_BK]);
    DB::table('school_classes')->insert(['id' => '9D', 'name' => 'Kelas 9D', 'created_at' => now(), 'updated_at' => now()]);
    $student = Student::query()->create(['nisn' => '7000000001', 'nis' => '7001', 'class_id' => '9D', 'name' => 'Siswa Alpa', 'gender' => 'L']);
    $journal = Journal::query()->create(['user_id' => $teacher->nip, 'material' => 'Materi']);
    StudentNote::query()->create(['journal_id' => $journal->id, 'student_id' => $student->id, 'note_type' => 'KBM_Alpa']);
    Sanctum::actingAs($bk);

    $this->getJson('/api/v1/bk/absentees?date='.now()->toDateString())
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('total', 1);
});
