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
        Schema::create('student_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained('journals')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->string('note_type'); // e.g., 'KBM_Hadir', 'KBM_Sakit', 'KBM_Izin', 'KBM_Alpa'
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_notes');
    }
};
