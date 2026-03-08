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
        Schema::create('inval_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_id');
            $table->string('replacement_teacher_id'); // NIP Guru pengganti
            $table->date('date');
            $table->enum('status', ['assigned', 'claimed', 'completed'])->default('claimed');
            $table->timestamps();

            // Kunci utama Anti-Tabrakan / Race Condition
            $table->unique(['schedule_id', 'date']);

            $table->foreign('replacement_teacher_id')->references('nip')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inval_assignments');
    }
};
