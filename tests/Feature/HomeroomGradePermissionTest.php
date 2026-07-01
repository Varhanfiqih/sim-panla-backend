<?php

use App\Models\GradeCategory;
use App\Models\GradePeriod;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Schema::dropIfExists('mobile_notifications');
    Schema::dropIfExists('permissions');
    Schema::dropIfExists('student_grades');
    Schema::dropIfExists('grade_categories');
    Schema::dropIfExists('grade_periods');
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

    Schema::create('grade_periods', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->boolean('is_active')->default(false);
        $table->timestamps();
    });

    Schema::create('grade_categories', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->boolean('is_active')->default(true);
        $table->boolean('is_repeatable')->default(false);
        $table->integer('max_item')->default(1);
        $table->integer('max_score')->default(100);
        $table->integer('sort_order')->default(1);
        $table->timestamps();
    });

    Schema::create('student_grades', function (Blueprint $table) {
        $table->id();
        $table->string('student_nisn');
        $table->foreignId('subject_id');
        $table->foreignId('grade_category_id');
        $table->foreignId('grade_period_id');
        $table->integer('item_no')->default(1);
        $table->decimal('score', 5, 2);
        $table->string('notes')->nullable();
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

function seedHomeroomGradeData(): array
{
    $wali = User::query()->create([
        'nip' => 'WALI-001',
        'name' => 'Wali Kelas',
        'password' => 'Password123',
        'role' => User::ROLE_GURU,
    ]);
    User::query()->create([
        'nip' => 'BK-001',
        'name' => 'Guru BK',
        'password' => 'Password123',
        'role' => User::ROLE_GURU_BK,
    ]);
    DB::table('school_classes')->insert([
        'id' => '7B',
        'name' => 'Kelas 7B',
        'homeroom_teacher_id' => $wali->nip,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $student = Student::query()->create([
        'nisn' => '0011223344',
        'nis' => '1122',
        'class_id' => '7B',
        'name' => 'Siswa Wali',
        'gender' => 'L',
    ]);
    $subject = Subject::query()->create(['name' => 'IPA']);
    $period = GradePeriod::query()->create(['name' => 'Semester Ganjil', 'is_active' => true]);
    $category = GradeCategory::query()->create(['name' => 'Ulangan Harian', 'is_active' => true]);
    DB::table('time_slots')->insert(['id' => 1, 'start_time' => '08:00:00', 'end_time' => '08:45:00', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('schedules')->insert(['class_id' => '7B', 'subject_id' => $subject->id, 'time_slot_id' => 1, 'created_at' => now(), 'updated_at' => now()]);

    return compact('wali', 'student', 'subject', 'period', 'category');
}

test('homeroom teacher can input and summarize student grades', function () {
    ['wali' => $wali, 'student' => $student, 'subject' => $subject, 'period' => $period, 'category' => $category] = seedHomeroomGradeData();
    Sanctum::actingAs($wali);

    $this->postJson('/api/v1/grades/scores/bulk-upsert', [
        'period_id' => $period->id,
        'subject_id' => $subject->id,
        'entries' => [[
            'student_id' => $student->id,
            'category_id' => $category->id,
            'item_no' => 1,
            'score' => 88,
            'notes' => 'Baik',
        ]],
    ])->assertOk()->assertJsonPath('status', 'success');

    expect(StudentGrade::query()->where('student_nisn', $student->nisn)->where('score', 88)->exists())->toBeTrue();

    $this->getJson("/api/v1/grades/summary?period_id={$period->id}&subject_id={$subject->id}&category_id={$category->id}&item_no=1")
        ->assertOk()
        ->assertJsonPath('data.completed', 1)
        ->assertJsonPath('data.average', 88);
});

test('homeroom teacher can submit student permission for bk verification', function () {
    ['wali' => $wali, 'student' => $student] = seedHomeroomGradeData();
    Sanctum::actingAs($wali);

    $this->postJson('/api/v1/permissions/store', [
        'student_id' => $student->id,
        'type' => 'izin',
        'start_date' => '2026-06-30',
        'end_date' => '2026-06-30',
        'keterangan' => 'Keperluan keluarga',
    ])
        ->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.status', 'pending');

    expect(DB::table('permissions')->where('student_id', $student->id)->where('status', 'pending')->exists())->toBeTrue();
});
