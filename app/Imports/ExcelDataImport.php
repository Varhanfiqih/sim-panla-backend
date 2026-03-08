<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ExcelDataImport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'Guru' => new GuruSheetImport(),
            'Murid' => new MuridSheetImport(),
        ];
    }
}

class GuruSheetImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // Cek NIP
            if (!isset($row['nip']) || empty($row['nip'])) {
                continue;
            }

            // Parsing Role dari "Akses"
            $role = 'guru';
            $akses = strtolower(trim($row['akses'] ?? ''));
            if (str_contains($akses, 'admin') || str_contains($akses, 'kepala sekolah')) {
                $role = 'admin';
            } elseif (str_contains($akses, 'bk')) {
                $role = 'bk';
            }

            // Parsing is_inval_piket
            $isInvalPiket = false;
            // Jika ada info tambahan piket di kolom catatan/akses

            User::updateOrCreate(
                ['nip' => (string) $row['nip']],
                [
                    'name' => $row['nama_guru'] ?? 'Tanpa Nama',
                    'password' => Hash::make((string) ($row['password'] ?? '123456')),
                    'role' => $role,
                    'wali_kelas' => $row['wali_kelas'] ?? null,
                    'is_inval_piket' => $isInvalPiket,
                ]
            );
        }
    }
}

class MuridSheetImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // Cek NISN/NIS
            if (!isset($row['nisn']) && !isset($row['nis'])) {
                continue;
            }

            \App\Models\Student::updateOrCreate(
                ['nisn' => (string) ($row['nisn'] ?? $row['nis'])], // fallback nisn ke nis
                [
                    'nis' => (string) ($row['nis'] ?? ''),
                    'name' => $row['nama_murid'] ?? 'Tanpa Nama',
                    'gender' => strtoupper(trim($row['lp'] ?? 'L')),
                    'class_id' => $row['kelas'] ?? null,
                    'qrcode' => $row['qrcode'] ?? null,
                ]
            );
        }
    }
}
