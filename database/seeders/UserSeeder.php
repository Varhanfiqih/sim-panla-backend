<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Struktur Hak Akses SIM SPANLA:
     *   1. Super Admin  – Latif Abdillah          (full system access)
     *   2. Admin IT     – Agung Susanto            (operasional harian)
     *   2. Admin IT     – Denni Kurniawan          (operasional harian)
     *   3. Kepala Sekolah – Arif Syaifurrohman    (monitoring & evaluasi, read-only)
     *   4. Guru         – Guru reguler / Wali Kelas
     *   5. Guru BK      – Guru Bimbingan Konseling
     */
    public function run(): void
    {
        // ─── 1. SUPER ADMIN ───────────────────────────────────────────────────
        // Full system access: user management, system config, bulk delete
        User::updateOrCreate(
            ['nip' => '000000000000000001'],
            [
                'name'          => 'Latif Abdillah',
                'password'      => Hash::make('superadmin123'),
                'role'          => User::ROLE_SUPER_ADMIN,
                'is_inval_piket' => false,
            ]
        );

        // ─── 2. ADMIN IT ──────────────────────────────────────────────────────
        // Operasional harian: CRUD data master, absensi, jurnal, laporan
        User::updateOrCreate(
            ['nip' => '000000000000000002'],
            [
                'name'          => 'Agung Susanto',
                'password'      => Hash::make('adminit123'),
                'role'          => User::ROLE_ADMIN_IT,
                'is_inval_piket' => false,
            ]
        );

        User::updateOrCreate(
            ['nip' => '000000000000000003'],
            [
                'name'          => 'Denni Kurniawan',
                'password'      => Hash::make('adminit123'),
                'role'          => User::ROLE_ADMIN_IT,
                'is_inval_piket' => false,
            ]
        );

        // ─── 3. KEPALA SEKOLAH ────────────────────────────────────────────────
        // Monitoring & evaluasi: read-only dashboard, laporan, export
        // NIP asli Arif Syaifurrohman (diupdate dari 'Admin' → 'Kepala Sekolah' via migration)
        User::updateOrCreate(
            ['nip' => '198106202009041003'],
            [
                'name'          => 'ARIF SYAIFURROHMAN, S.Pd',
                'password'      => Hash::make('kepsek123'),
                'role'          => User::ROLE_KEPALA_SEKOLAH,
                'is_inval_piket' => false,
            ]
        );

        // ─── 4. GURU (Contoh) ─────────────────────────────────────────────────
        User::updateOrCreate(
            ['nip' => '196912111997031009'],
            [
                'name'          => 'ABD HALIM, S.Pd',
                'password'      => Hash::make('1'),
                'role'          => User::ROLE_GURU,
                'is_inval_piket' => false,
            ]
        );

        // ─── 5. GURU BK (Contoh) ──────────────────────────────────────────────
        User::updateOrCreate(
            ['nip' => '198508042022212022'],
            [
                'name'          => 'AGUSTIN NUR HIDAYATI, S.Psi',
                'password'      => Hash::make('1'),
                'role'          => User::ROLE_GURU_BK,
                'is_inval_piket' => false,
            ]
        );
    }
}
