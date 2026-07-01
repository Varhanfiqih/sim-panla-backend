<?php

use App\Models\GradeCategory;
use App\Models\GradePeriod;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('grade_categories');
    Schema::dropIfExists('grade_periods');

    Schema::create('grade_periods', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('semester')->nullable();
        $table->string('academic_year')->nullable();
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
});

test('admin can create active grade period', function () {
    $period = GradePeriod::query()->create([
        'name' => 'Semester Ganjil',
        'semester' => 'Ganjil',
        'academic_year' => '2026/2027',
        'is_active' => true,
    ]);

    expect($period->name)->toBe('Semester Ganjil')
        ->and($period->is_active)->toBeTrue();
});

test('admin can create grade category with maximum score', function () {
    $category = GradeCategory::query()->create([
        'name' => 'UTS',
        'is_active' => true,
        'max_score' => 100,
        'sort_order' => 2,
    ]);

    expect($category->name)->toBe('UTS')
        ->and((int) $category->max_score)->toBe(100)
        ->and($category->is_active)->toBeTrue();
});
