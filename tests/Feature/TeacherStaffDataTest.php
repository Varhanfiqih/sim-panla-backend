<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
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
});

test('admin can store teacher and staff data with role', function () {
    $teacher = User::query()->create([
        'nip' => 'GURU-101',
        'name' => 'Guru Mapel',
        'password' => 'Password123',
        'role' => User::ROLE_GURU,
    ]);

    $admin = User::query()->create([
        'nip' => 'ADMIN-101',
        'name' => 'Admin IT',
        'password' => 'Password123',
        'role' => User::ROLE_ADMIN_IT,
    ]);

    expect(User::query()->where('nip', 'GURU-101')->exists())->toBeTrue()
        ->and($teacher->role)->toBe(User::ROLE_GURU)
        ->and($admin->role)->toBe(User::ROLE_ADMIN_IT);
});

test('admin can update teacher role and staff data', function () {
    $user = User::query()->create([
        'nip' => 'STAFF-101',
        'name' => 'Staf Lama',
        'password' => 'Password123',
        'role' => User::ROLE_GURU,
    ]);

    $user->update([
        'name' => 'Staf Diperbarui',
        'role' => User::ROLE_GURU_BK,
    ]);

    expect($user->fresh()->name)->toBe('Staf Diperbarui')
        ->and($user->fresh()->role)->toBe(User::ROLE_GURU_BK);
});
