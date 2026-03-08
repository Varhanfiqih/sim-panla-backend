<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            // Guru Wali Kelas yang mengajukan
            $table->string('nip_guru');
            $table->foreign('nip_guru')->references('nip')->on('users')->onDelete('cascade');
            // Siswa yang diizinkan
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            // Tipe: sakit | izin | keluarga
            $table->enum('type', ['sakit', 'izin', 'keluarga'])->default('izin');
            // Rentang tanggal multi-hari
            $table->date('start_date');
            $table->date('end_date');
            // Keterangan tambahan
            $table->text('keterangan')->nullable();
            // Path file surat dokter/ortu (opsional)
            $table->string('foto_path')->nullable();
            // Status: pending | approved | rejected
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
