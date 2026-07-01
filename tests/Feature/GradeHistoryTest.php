<?php

use App\Models\GradeCategory;
use App\Models\GradePeriod;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

uses()->group('grade-history');

beforeEach(function () {
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
});

function seedGradeHistoryData(): array
{
    $wali = User::query()->create(['nip' => 'WALI-RIWAYAT-001', 'name' => 'Wali Riwayat', 'password' => 'Password123', 'role' => User::ROLE_GURU]);
    DB::table('school_classes')->insert(['id' => '7R', 'name' => 'Kelas 7R', 'homeroom_teacher_id' => $wali->nip, 'created_at' => now(), 'updated_at' => now()]);
    $student = Student::query()->create(['nisn' => '5200000001', 'nis' => '5201', 'class_id' => '7R', 'name' => 'Siswa Riwayat', 'gender' => 'L']);
    $subject = Subject::query()->create(['name' => 'Bahasa Indonesia']);
    $period = GradePeriod::query()->create(['name' => 'Semester Genap', 'is_active' => true]);
    $category = GradeCategory::query()->create(['name' => 'Tugas', 'is_active' => true]);
    DB::table('time_slots')->insert(['id' => 1, 'start_time' => '08:00:00', 'end_time' => '08:45:00', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('schedules')->insert(['class_id' => '7R', 'subject_id' => $subject->id, 'time_slot_id' => 1, 'created_at' => now(), 'updated_at' => now()]);

    return compact('wali', 'student', 'subject', 'period', 'category');
}

test('homeroom teacher can view grade summary as grade history basis', function () {
    ['wali' => $wali, 'student' => $student, 'subject' => $subject, 'period' => $period, 'category' => $category] = seedGradeHistoryData();
    Sanctum::actingAs($wali);

    $this->postJson('/api/v1/grades/scores/bulk-upsert', [
        'period_id' => $period->id,
        'subject_id' => $subject->id,
        'entries' => [[
            'student_id' => $student->id,
            'category_id' => $category->id,
            'item_no' => 1,
            'score' => 92,
        ]],
    ])->assertOk();

    $this->getJson("/api/v1/grades/summary?period_id={$period->id}&subject_id={$subject->id}&category_id={$category->id}&item_no=1")
        ->assertOk()
        ->assertJsonPath('data.completed', 1)
        ->assertJsonPath('data.average', 92);
});
