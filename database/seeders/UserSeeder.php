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
     */
    public function run(): void
    {
        // 1. Kepala Sekolah (Admin)
        User::create([
            'nip' => '198106202009041003',
            'name' => 'ARIF SYAIFURROHMAN, S.Pd',
            'password' => Hash::make('1'),
            'role' => 'Admin',
            'mata_pelajaran' => 'Ilmu Pengetahuan Sosial;Program Sekolah',
            'wali_kelas' => 'ALL',
            'is_inval_piket' => false,
        ]);

        // 2. Guru / Wali Kelas
        User::create([
            'nip' => '196912111997031009',
            'name' => 'ABD HALIM, S.Pd',
            'password' => Hash::make('1'),
            'role' => 'Guru',
            'mata_pelajaran' => 'Seni, Budaya dan Prakarya;Program Sekolah',
            'wali_kelas' => '7F',
            'is_inval_piket' => false,
        ]);

        // 3. Guru BK
        User::create([
            'nip' => '198508042022212022',
            'name' => 'AGUSTIN NUR HIDAYATI, S.Psi',
            'password' => Hash::make('1'),
            'role' => 'Guru BK',
            'mata_pelajaran' => 'Bimbingan Konseling;Program Sekolah',
            'wali_kelas' => 'ALL',
            'is_inval_piket' => false,
        ]);
    }
}
