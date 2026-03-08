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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->string('nip_guru');
            $table->string('nisn_student');
            $table->string('kelas');
            $table->string('presensi');
            $table->string('ekstra')->nullable();
            $table->timestamps();

            $table->foreign('nip_guru')->references('nip')->on('users')->onDelete('cascade');
            $table->foreign('nisn_student')->references('nisn')->on('students')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
