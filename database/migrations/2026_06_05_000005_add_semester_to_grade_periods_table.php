<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grade_periods', function (Blueprint $table): void {
            $table->enum('semester', ['ganjil', 'genap'])
                ->nullable()
                ->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('grade_periods', function (Blueprint $table): void {
            $table->dropColumn('semester');
        });
    }
};
