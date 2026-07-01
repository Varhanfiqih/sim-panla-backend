<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('app_settings');

    Schema::create('app_settings', function (Blueprint $table) {
        $table->id();
        $table->string('key')->unique();
        $table->text('value')->nullable();
        $table->timestamps();
    });
});

test('admin can store attendance time configuration', function () {
    DB::table('app_settings')->insert([
        'key' => 'jam_masuk',
        'value' => '07:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('app_settings')->where('key', 'jam_masuk')->value('value'))
        ->toBe('07:00:00');
});

test('admin can update attendance tolerance configuration', function () {
    DB::table('app_settings')->insert([
        'key' => 'jam_terlambat_toleransi',
        'value' => '07:15:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('app_settings')
        ->where('key', 'jam_terlambat_toleransi')
        ->update(['value' => '07:20:00', 'updated_at' => now()]);

    expect(DB::table('app_settings')->where('key', 'jam_terlambat_toleransi')->value('value'))
        ->toBe('07:20:00');
});
