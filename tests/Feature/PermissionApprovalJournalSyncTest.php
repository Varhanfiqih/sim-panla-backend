<?php

use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Event::fake();

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('nip')->unique();
        $table->string('name');
        $table->string('password');
        $table->timestamp('password_changed_at')->nullable();
        $table->string('role');
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

    Schema::create('school_classes', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('name');
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
        $table->string('class_id');
        $table->foreignId('subject_id');
        $table->foreignId('time_slot_id');
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

test('bk approval updates existing journal history in the permission date range', function (
    string $permissionType,
    string $expectedJournalStatus,
    string $expectedAttendanceStatus,
) {
    $homeroomTeacher = User::query()->create([
        'nip' => 'wali-001',
        'name' => 'Wali Kelas',
        'password' => 'Password123',
        'role' => User::ROLE_GURU,
    ]);
    $bkTeacher = User::query()->create([
        'nip' => 'bk-001',
        'name' => 'Guru BK',
        'password' => 'Password123',
        'role' => User::ROLE_GURU_BK,
    ]);

    $studentId = DB::table('students')->insertGetId([
        'nisn' => '0012345678',
        'nis' => '1234',
        'class_id' => '7A',
        'name' => 'Siswa Sakit',
        'gender' => 'L',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('school_classes')->insert([
        'id' => '7A',
        'name' => '7A',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('subjects')->insert([
        'id' => 1,
        'name' => 'IPA',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('time_slots')->insert([
        'id' => 1,
        'start_time' => '08:00:00',
        'end_time' => '08:45:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('schedules')->insert([
        'id' => 1,
        'class_id' => '7A',
        'subject_id' => 1,
        'time_slot_id' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $journalId = DB::table('journals')->insertGetId([
        'schedule_id' => 1,
        'user_id' => $homeroomTeacher->nip,
        'is_inval' => false,
        'material' => 'Materi',
        'created_at' => '2026-06-11 08:00:00',
        'updated_at' => '2026-06-11 08:00:00',
    ]);
    DB::table('student_notes')->insert([
        'journal_id' => $journalId,
        'student_id' => $studentId,
        'note_type' => 'KBM_Alpa',
        'created_at' => '2026-06-11 08:00:00',
        'updated_at' => '2026-06-11 08:00:00',
    ]);

    $outsideJournalId = DB::table('journals')->insertGetId([
        'schedule_id' => 1,
        'user_id' => $homeroomTeacher->nip,
        'is_inval' => false,
        'material' => 'Materi hari sebelumnya',
        'created_at' => '2026-06-10 08:00:00',
        'updated_at' => '2026-06-10 08:00:00',
    ]);
    DB::table('student_notes')->insert([
        'journal_id' => $outsideJournalId,
        'student_id' => $studentId,
        'note_type' => 'KBM_Alpa',
        'created_at' => '2026-06-10 08:00:00',
        'updated_at' => '2026-06-10 08:00:00',
    ]);

    DB::table('attendances')->insert([
        'nip_guru' => $homeroomTeacher->nip,
        'nisn_student' => '0012345678',
        'kelas' => '7A',
        'presensi' => 'Alpa',
        'kegiatan' => 'KBM',
        'created_at' => '2026-06-11 08:00:00',
        'updated_at' => '2026-06-11 08:00:00',
    ]);

    $permission = Permission::query()->create([
        'nip_guru' => $homeroomTeacher->nip,
        'student_id' => $studentId,
        'type' => $permissionType,
        'start_date' => '2026-06-11',
        'end_date' => '2026-06-11',
        'keterangan' => 'Surat dokter',
        'status' => 'pending',
    ]);

    Sanctum::actingAs($bkTeacher);

    $this->postJson("/api/v1/bk/permissions/{$permission->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect($permission->fresh()->status)->toBe('approved')
        ->and(DB::table('student_notes')->where('journal_id', $journalId)->value('note_type'))
        ->toBe($expectedJournalStatus)
        ->and(DB::table('student_notes')->where('journal_id', $outsideJournalId)->value('note_type'))
        ->toBe('KBM_Alpa')
        ->and(DB::table('attendances')->where('nisn_student', '0012345678')->value('presensi'))
        ->toBe($expectedAttendanceStatus);

    DB::table('student_notes')
        ->where('journal_id', $journalId)
        ->update(['note_type' => 'KBM_Hadir']);

    $this->getJson("/api/v1/journal/history/{$journalId}")
        ->assertOk()
        ->assertJsonPath('data.absensi.0.nis', '1234')
        ->assertJsonPath('data.absensi.0.status', $expectedJournalStatus);
})->with([
    'sakit' => ['sakit', 'KBM_Sakit', 'Sakit'],
    'izin' => ['izin', 'KBM_Izin', 'Izin'],
]);
