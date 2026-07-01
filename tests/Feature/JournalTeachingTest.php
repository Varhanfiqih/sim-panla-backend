<?php

use App\Models\Journal;
use App\Models\Student;
use App\Models\StudentNote;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Event::fake();

    Schema::dropIfExists('attendances');
    Schema::dropIfExists('mobile_notifications');
    Schema::dropIfExists('student_notes');
    Schema::dropIfExists('journals');
    Schema::dropIfExists('permissions');
    Schema::dropIfExists('teacher_attendances');
    Schema::dropIfExists('schedules');
    Schema::dropIfExists('time_slots');
    Schema::dropIfExists('subjects');
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
    Schema::create('journals', function (Blueprint $table) {
        $table->id();
        $table->foreignId('schedule_id')->nullable();
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

function seedJournalTeachingData(): array
{
    $teacher = User::query()->create([
        'nip' => 'GURU-JURNAL-001',
        'name' => 'Guru Jurnal',
        'password' => 'Password123',
        'role' => User::ROLE_GURU,
    ]);
    DB::table('school_classes')->insert(['id' => '7D', 'name' => 'Kelas 7D', 'created_at' => now(), 'updated_at' => now()]);
    $student = Student::query()->create(['nisn' => '4000000001', 'nis' => '4001', 'class_id' => '7D', 'name' => 'Siswa Jurnal', 'gender' => 'L']);
    DB::table('subjects')->insert(['id' => 1, 'name' => 'IPS', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('time_slots')->insert(['id' => 1, 'start_time' => '10:00:00', 'end_time' => '10:45:00', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('schedules')->insert(['id' => 1, 'class_id' => '7D', 'subject_id' => 1, 'time_slot_id' => 1, 'teacher_id' => $teacher->nip, 'created_at' => now(), 'updated_at' => now()]);
    TeacherAttendance::query()->create(['user_id' => $teacher->id, 'date' => now()->toDateString(), 'status' => 'hadir']);

    return compact('teacher', 'student');
}

test('teacher can store teaching journal after check in', function () {
    ['teacher' => $teacher, 'student' => $student] = seedJournalTeachingData();
    Sanctum::actingAs($teacher);

    $this->postJson('/api/v1/journal/store', [
        'schedule_id' => 1,
        'materi' => 'Materi pembelajaran',
        'kebersihan_kelas' => 'bersih',
        'is_inval' => false,
        'attendances' => [[
            'student_id' => $student->id,
            'status' => 'KBM_Hadir',
            'notes' => [],
        ]],
    ])
        ->assertCreated()
        ->assertJsonPath('status', 'success');

    expect(Journal::query()->where('schedule_id', 1)->exists())->toBeTrue()
        ->and(StudentNote::query()->where('student_id', $student->id)->where('note_type', 'KBM_Hadir')->exists())->toBeTrue();
});

test('teacher cannot submit duplicate journal for same schedule on same day', function () {
    ['teacher' => $teacher, 'student' => $student] = seedJournalTeachingData();
    Sanctum::actingAs($teacher);
    $payload = [
        'schedule_id' => 1,
        'materi' => 'Materi pembelajaran',
        'kebersihan_kelas' => 'bersih',
        'is_inval' => false,
        'attendances' => [['student_id' => $student->id, 'status' => 'KBM_Hadir', 'notes' => []]],
    ];

    $this->postJson('/api/v1/journal/store', $payload)->assertCreated();
    $this->postJson('/api/v1/journal/store', $payload)->assertStatus(409);
});
