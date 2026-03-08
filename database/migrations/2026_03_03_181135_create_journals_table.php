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
        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            // Tidak menggunakan constrained() agar jadwal Inval (ID dummy) tetap bisa disimpan
            $table->unsignedBigInteger('schedule_id')->nullable();
            $table->string('user_id'); // NIP Guru
            $table->boolean('is_inval')->default(false);
            $table->text('material')->nullable();
            $table->string('cleanliness')->nullable(); // e.g. 'sudah_bersih', 'kotor'
            $table->timestamps();

            $table->foreign('user_id')->references('nip')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journals');
    }
};
