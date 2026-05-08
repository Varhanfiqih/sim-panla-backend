<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Menambah role baru: Super Admin, Admin IT, Kepala Sekolah
     * Role lama 'Admin' dipertahankan sementara untuk backward compatibility
     * kemudian akan diubah ke 'Kepala Sekolah' via seeder/tinker.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('Super Admin', 'Admin IT', 'Kepala Sekolah', 'Admin', 'Guru', 'Guru BK') DEFAULT 'Guru'");

        // Migrasi data lama: semua user dengan role 'Admin' → 'Kepala Sekolah'
        // (Sesuai spesifikasi: Arif Syaifurrohman yang sebelumnya Admin = Kepala Sekolah)
        DB::statement("UPDATE users SET role = 'Kepala Sekolah' WHERE role = 'Admin'");

        // Setelah migrasi data, hapus nilai enum lama 'Admin' agar tidak bisa dipakai lagi
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('Super Admin', 'Admin IT', 'Kepala Sekolah', 'Guru', 'Guru BK') DEFAULT 'Guru'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback: kembalikan ke enum lama
        // Terlebih dahulu update data baru → lama
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('Super Admin', 'Admin IT', 'Kepala Sekolah', 'Admin', 'Guru', 'Guru BK') DEFAULT 'Guru'");
        DB::statement("UPDATE users SET role = 'Admin' WHERE role IN ('Super Admin', 'Admin IT', 'Kepala Sekolah')");
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('Admin', 'Guru', 'Guru BK') DEFAULT 'Guru'");
    }
};
