<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('personal_access_tokens');
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

    Schema::create('personal_access_tokens', function (Blueprint $table) {
        $table->id();
        $table->morphs('tokenable');
        $table->string('name');
        $table->string('token', 64)->unique();
        $table->text('abilities')->nullable();
        $table->timestamp('last_used_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->timestamps();
    });

    Schema::create('school_classes', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('name');
        $table->string('homeroom_teacher_id')->nullable();
        $table->timestamps();
    });
});

test('super admin can login through api and receive sanctum token', function () {
    User::query()->create([
        'nip' => 'ADMIN-001',
        'name' => 'Super Admin',
        'password' => 'Password123',
        'role' => User::ROLE_SUPER_ADMIN,
    ]);

    $this->postJson('/api/v1/login', [
        'nip' => 'ADMIN-001',
        'password' => 'Password123',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.role', User::ROLE_SUPER_ADMIN)
        ->assertJsonStructure(['data' => ['token']]);
});

test('panel roles are separated from mobile-only teacher roles', function () {
    $superAdmin = new User(['role' => User::ROLE_SUPER_ADMIN]);
    $adminIt = new User(['role' => User::ROLE_ADMIN_IT]);
    $kepalaSekolah = new User(['role' => User::ROLE_KEPALA_SEKOLAH]);
    $guru = new User(['role' => User::ROLE_GURU]);
    $guruBk = new User(['role' => User::ROLE_GURU_BK]);

    expect($superAdmin->isSuperAdmin())->toBeTrue()
        ->and($adminIt->isAdminIT())->toBeTrue()
        ->and($kepalaSekolah->isKepsek())->toBeTrue()
        ->and($guru->isGuru())->toBeTrue()
        ->and($guruBk->isGuroBK())->toBeTrue()
        ->and($superAdmin->isStaff())->toBeTrue()
        ->and($adminIt->isStaff())->toBeTrue()
        ->and($guru->isStaff())->toBeFalse();
});

test('invalid login is rejected before token is created', function () {
    User::query()->create([
        'nip' => 'ADMIN-002',
        'name' => 'Super Admin Dua',
        'password' => 'Password123',
        'role' => User::ROLE_SUPER_ADMIN,
    ]);

    $this->postJson('/api/v1/login', [
        'nip' => 'ADMIN-002',
        'password' => 'PasswordSalah',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('success', false);
});
