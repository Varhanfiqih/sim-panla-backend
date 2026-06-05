<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_grades', function (Blueprint $table) {
            $table->unsignedInteger('item_no')->default(1)->after('grade_period_id');
            $table->index([
                'student_nisn',
                'subject_id',
                'grade_category_id',
                'grade_period_id',
                'item_no',
            ], 'student_grades_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::table('student_grades', function (Blueprint $table) {
            $table->dropIndex('student_grades_lookup_index');
            $table->dropColumn('item_no');
        });
    }
};
