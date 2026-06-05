<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grade_periods', function (Blueprint $table): void {
            $table->string('academic_year', 9)
                ->nullable()
                ->after('semester');
        });
    }

    public function down(): void
    {
        Schema::table('grade_periods', function (Blueprint $table): void {
            $table->dropColumn('academic_year');
        });
    }
};
