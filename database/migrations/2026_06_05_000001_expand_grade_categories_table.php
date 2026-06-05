<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grade_categories', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('name');
            $table->boolean('is_repeatable')->default(false)->after('code');
            $table->unsignedInteger('max_item')->default(1)->after('is_repeatable');
            $table->decimal('max_score', 8, 2)->default(100)->after('max_item');
            $table->unsignedInteger('sort_order')->default(0)->after('weight');
            $table->boolean('is_active')->default(true)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('grade_categories', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn([
                'code',
                'is_repeatable',
                'max_item',
                'max_score',
                'sort_order',
                'is_active',
            ]);
        });
    }
};
