<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_grades', function (Blueprint $table) {
            $table->id();
            $table->string('student_nisn');
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->foreignId('grade_category_id')->constrained('grade_categories')->onDelete('cascade');
            $table->foreignId('grade_period_id')->constrained('grade_periods')->onDelete('cascade');
            $table->decimal('score', 5, 2)->default(0); // Nilai 0.00 s/d 100.00
            $table->string('notes')->nullable();
            $table->timestamps();

            // Referensi nisn ke tabel students
            $table->foreign('student_nisn')->references('nisn')->on('students')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_grades');
    }
};
