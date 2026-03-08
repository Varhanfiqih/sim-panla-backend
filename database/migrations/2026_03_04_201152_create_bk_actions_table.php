<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bk_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            // Guru BK yang menangani
            $table->string('handled_by_user_id'); // NIP Guru BK
            $table->foreign('handled_by_user_id')->references('nip')->on('users')->onDelete('cascade');
            // Tipe tindakan: panggilan_ortu | home_visit | surat_peringatan | konseling | lainnya
            $table->string('action_type');
            // Catatan hasil tindakan
            $table->text('notes')->nullable();
            // Upload bukti (foto home visit, dll)
            $table->string('attachment_url')->nullable();
            // Status sebelum & sesudah konfirmasi BK
            $table->string('status_sebelum')->nullable(); // KBM_Alpa
            $table->string('status_sesudah')->nullable(); // KBM_Sakit | KBM_Izin | tetap_alpa
            // Tanggal kejadian yang dikonfirmasi
            $table->date('tanggal_kejadian')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bk_actions');
    }
};
