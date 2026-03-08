<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Student;
use App\Models\Schedule;
use Illuminate\Support\Facades\Hash;
use OpenSpout\Reader\XLSX\Reader;

class DataExcelSeeder extends Seeder
{
    public function run(): void
    {
        $filePath = base_path('DATA_SMPN8.xlsx');
        if (!file_exists($filePath)) {
            $this->command->error("File DATA_SMPN8.xlsx tidak ditemukan!");
            return;
        }

        $this->command->info('Memproses data dari DATA_SMPN8.xlsx menggunakan OpenSpout...');
        
        $reader = new Reader();
        $reader->open($filePath);

        foreach ($reader->getSheetIterator() as $sheet) {
            $sheetName = $sheet->getName();
            
            if ($sheetName === 'Guru') {
                $this->command->info('-> Mengimport Guru...');
                $this->importGuru($sheet);
            } elseif ($sheetName === 'Murid') {
                $this->command->info('-> Mengimport Murid...');
                $this->importMurid($sheet);
            } elseif ($sheetName === 'Jadwal') {
                $this->command->info('-> Mengimport Jadwal...');
                $this->importJadwal($sheet);
            } elseif ($sheetName === 'Pengaturan') {
                $this->command->info('-> Mengimport Pengaturan...');
                $this->importPengaturan($sheet);
            }
        }

        $reader->close();
        $this->command->info('Import Selesai!');
    }

    private function importGuru($sheet)
    {
        $isFirst = true;
        foreach ($sheet->getRowIterator() as $row) {
            if ($isFirst) { $isFirst = false; continue; } // skip header
            
            $cells = $row->toArray();
            if (empty($cells[0])) continue; // NIP kosong

            $nip = (string) $cells[0];
            $nama = $cells[1] ?? 'Tanpa Nama';
            $password = $cells[2] ?? '123456';
            $akses = strtolower(trim($cells[3] ?? ''));
            $wali_kelas = $cells[8] ?? null;

            $role = 'Guru';
            if (str_contains($akses, 'admin') || str_contains($akses, 'kepala sekolah')) {
                $role = 'Admin';
            } elseif (str_contains($akses, 'bk')) {
                $role = 'Guru BK';
            }

            User::updateOrCreate(
                ['nip' => $nip],
                [
                    'name' => $nama,
                    'password' => Hash::make((string) $password),
                    'role' => $role,
                    'wali_kelas' => $wali_kelas,
                    'is_inval_piket' => false,
                ]
            );
        }
    }

    private function importMurid($sheet)
    {
        $isFirst = true;
        foreach ($sheet->getRowIterator() as $row) {
            if ($isFirst) { $isFirst = false; continue; } // skip header
            
            $cells = $row->toArray();
            if (empty($cells[0]) && empty($cells[1])) continue; // nisn dan nis kosong

            $nisn = (string) ($cells[0] ?? $cells[1]);
            $nis = (string) ($cells[1] ?? '');
            $kelas = $cells[2] ?? null;
            $nama = $cells[3] ?? 'Tanpa Nama';
            $jk = strtoupper(trim($cells[4] ?? 'L'));
            $qrcode = trim($cells[5] ?? '');
            if ($qrcode === '=' || empty($qrcode)) {
                $qrcode = null;
            }

            Student::updateOrCreate(
                ['nisn' => $nisn],
                [
                    'nis' => $nis,
                    'name' => $nama,
                    'gender' => $jk,
                    'class_id' => $kelas,
                    'qr_code' => $qrcode,
                ]
            );
        }
    }

    private function importJadwal($sheet)
    {
        $isFirst = true;
        foreach ($sheet->getRowIterator() as $row) {
            if ($isFirst) { $isFirst = false; continue; } // skip header
            
            $cells = $row->toArray();
            if (empty($cells[0]) || empty($cells[1])) continue; // hari atau kelas kosong

            $hari = strtoupper(trim($cells[0] ?? ''));
            $kelas = $cells[1] ?? '';
            $jam_ke = (string) ($cells[2] ?? '');
            $mapel = $cells[3] ?? '';
            $namaGuru = trim($cells[4] ?? '');
            $keterangan = $cells[5] ?? null;

            // Cari NIP berdasarkan nama guru
            $guru = null;
            if (!empty($namaGuru)) {
                $guru = User::where('name', 'LIKE', '%' . $namaGuru . '%')->first();
            }

            if ($guru) {
                // Jangan ada duplikat jadwal persis (hari, kelas, jam_ke, guru)
                Schedule::updateOrCreate(
                    [
                        'hari' => $hari,
                        'kelas' => $kelas,
                        'jam_ke' => $jam_ke,
                        'nip_guru_pengajar' => $guru->nip,
                    ],
                    [
                        'mata_pelajaran' => $mapel,
                        'keterangan' => $keterangan,
                    ]
                );
            }
        }
    }

    private function importPengaturan($sheet)
    {
        $isFirst = true;
        
        // Membersihkan tabel kategori sebelumnya
        \App\Models\Category::truncate();
        
        foreach ($sheet->getRowIterator() as $row) {
            if ($isFirst) { $isFirst = false; continue; } // skip header 'Jenis Presensi' & 'Nama Ekstra'
            
            $cells = $row->toArray();
            
            // Kolom Jenis Presensi berada di index 3
            if (!empty($cells[3])) {
                \App\Models\Category::create([
                    'type' => 'jenis_presensi',
                    'name' => trim($cells[3])
                ]);
            }
            
            // Kolom Nama Ekstra berada di index 4
            if (!empty($cells[4])) {
                \App\Models\Category::create([
                    'type' => 'ekstra',
                    'name' => trim($cells[4])
                ]);
            }
        }
    }
}
