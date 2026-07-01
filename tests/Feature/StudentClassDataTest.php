<?php

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
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
});

test('admin can store student and class data', function () {
    $wali = User::query()->create([
        'nip' => 'WALI-201',
        'name' => 'Wali Kelas',
        'password' => 'Password123',
        'role' => User::ROLE_GURU,
    ]);

    SchoolClass::query()->create([
        'id' => '7A',
        'name' => 'Kelas 7A',
        'homeroom_teacher_id' => $wali->nip,
    ]);

    Student::query()->create([
        'nisn' => '2026000001',
        'nis' => '260001',
        'class_id' => '7A',
        'name' => 'Siswa Kelas 7A',
        'gender' => 'L',
    ]);

    expect(SchoolClass::query()->where('id', '7A')->exists())->toBeTrue()
        ->and(Student::query()->where('class_id', '7A')->count())->toBe(1)
        ->and($wali->homeroomClass?->id)->toBe('7A');
});

test('student data can be updated inside selected class', function () {
    SchoolClass::query()->create(['id' => '8B', 'name' => 'Kelas 8B']);

    $student = Student::query()->create([
        'nisn' => '2026000002',
        'nis' => '260002',
        'class_id' => '8B',
        'name' => 'Nama Lama',
        'gender' => 'P',
    ]);

    $student->update(['name' => 'Nama Baru']);

    expect($student->fresh()->name)->toBe('Nama Baru')
        ->and($student->fresh()->class_id)->toBe('8B');
});
