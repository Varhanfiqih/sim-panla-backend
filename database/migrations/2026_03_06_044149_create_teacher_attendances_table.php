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
        Schema::create('teacher_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->date('date');
            $table->enum('status', ['hadir', 'tidak_hadir'])->default('hadir');
            $table->string('reason')->nullable(); // Misal: 'Sakit', 'Izin', 'Dinas Luar'
            $table->text('description')->nullable(); // Keterangan tambahan
            $table->string('attachment')->nullable(); // Path foto surat/bukti
            $table->timestamps();

            // Mencegah duplicate entry untuk 1 guru di hari yang sama
            $table->unique(['user_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_attendances');
    }
};
