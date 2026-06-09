<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

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

test('changing a password records when it was changed', function () {
    $user = User::query()->create([
        'nip' => '1987654321',
        'name' => 'Guru Penguji',
        'password' => 'Password123',
        'role' => User::ROLE_GURU,
    ]);

    $initialChangedAt = $user->password_changed_at;
    $this->travel(5)->minutes();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/profile/change-password', [
        'current_password' => 'Password123',
        'password' => 'Password456',
        'password_confirmation' => 'Password456',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true);

    $user->refresh();

    expect(Hash::check('Password456', $user->password))->toBeTrue()
        ->and($user->password_changed_at)->not->toBeNull()
        ->and($user->password_changed_at->greaterThan($initialChangedAt))->toBeTrue();
});

test('saving an unchanged password does not change its timestamp', function () {
    $user = User::query()->create([
        'nip' => '1987654322',
        'name' => 'Guru Penguji Kedua',
        'password' => 'Password123',
        'role' => User::ROLE_GURU,
    ]);

    $initialChangedAt = $user->password_changed_at;
    $this->travel(5)->minutes();

    $user->update(['name' => 'Nama Guru Diperbarui']);

    expect($user->fresh()->password_changed_at->equalTo($initialChangedAt))->toBeTrue();
});
