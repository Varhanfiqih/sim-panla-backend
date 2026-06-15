<?php

use App\Models\Permission;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('nip')->nullable()->unique();
        $table->string('name');
        $table->string('password');
        $table->timestamp('password_changed_at')->nullable();
        $table->string('role');
        $table->boolean('is_inval_piket')->default(false);
        $table->string('profile_photo_path')->nullable();
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
});

test('super admin can open the student permission list and edit page', function () {
    $superAdmin = User::query()->create([
        'nip' => 'ADMIN-001',
        'name' => 'Super Admin',
        'password' => 'password',
        'role' => User::ROLE_SUPER_ADMIN,
    ]);

    $teacher = User::query()->create([
        'nip' => 'GURU-001',
        'name' => 'Wali Kelas',
        'password' => 'password',
        'role' => User::ROLE_GURU,
    ]);

    SchoolClass::query()->create([
        'id' => '7A',
        'name' => 'Kelas 7A',
        'homeroom_teacher_id' => $teacher->nip,
    ]);

    $student = Student::query()->create([
        'nisn' => '1234567890',
        'class_id' => '7A',
        'name' => 'Adam Rizky',
        'gender' => 'L',
    ]);

    $permission = Permission::query()->create([
        'nip_guru' => $teacher->nip,
        'student_id' => $student->id,
        'type' => 'izin',
        'start_date' => '2026-06-15',
        'end_date' => '2026-06-15',
        'keterangan' => 'Keperluan keluarga',
        'status' => 'approved',
    ]);

    $this->actingAs($superAdmin)
        ->get('/admin/student-notes')
        ->assertOk()
        ->assertSee('Adam Rizky');

    $this->actingAs($superAdmin)
        ->get("/admin/student-notes/{$permission->id}/edit")
        ->assertOk()
        ->assertSee('Adam Rizky');
});
