<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->enum('day_of_week', ['SENIN', 'SELASA', 'RABU', 'KAMIS', 'JUMAT', 'SABTU']);
            $table->string('class_id'); // e.g. 7A
            $table->foreignId('time_slot_id')->constrained('time_slots')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->string('teacher_id');
            $table->string('keterangan')->nullable();

            $table->foreign('class_id')->references('id')->on('school_classes')->onDelete('cascade');
            $table->foreign('teacher_id')->references('nip')->on('users')->onDelete('cascade');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
