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

    Schema::create('school_classes', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('name');
        $table->string('homeroom_teacher_id')->nullable();
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
});

test('mobile user can login using nip and password', function () {
    User::query()->create([
        'nip' => 'GURU-MOBILE-001',
        'name' => 'Guru Mobile',
        'password' => 'Password123',
        'role' => User::ROLE_GURU,
    ]);

    $this->postJson('/api/v1/login', [
        'nip' => 'GURU-MOBILE-001',
        'password' => 'Password123',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.role', User::ROLE_GURU)
        ->assertJsonStructure(['data' => ['token']]);
});

test('mobile login rejects invalid password', function () {
    User::query()->create([
        'nip' => 'GURU-MOBILE-002',
        'name' => 'Guru Mobile Dua',
        'password' => 'Password123',
        'role' => User::ROLE_GURU,
    ]);

    $this->postJson('/api/v1/login', [
        'nip' => 'GURU-MOBILE-002',
        'password' => 'SalahPassword',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('success', false);
});
