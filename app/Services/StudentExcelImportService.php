<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\Student;
use OpenSpout\Common\Entity\Row;
use Illuminate\Support\Arr;
use OpenSpout\Reader\XLSX\Reader;

class StudentExcelImportService
{
    /**
     * @return array{created: int, updated: int, skipped: int, errors: array<int, string>}
     */
    public function import(string $path): array
    {
        $reader = new Reader();
        $reader->open($path);

        $summary = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($reader->getSheetIterator() as $sheet) {
            $classId = $this->normalizeClassId($sheet->getName());
            $headers = [];
            $previousValues = [];

            foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                $values = $this->rowValues($row);

                $classId = $this->classIdFromRow($values) ?? $classId;
                $presensiData = $this->presensiRowData($values);

                if ($presensiData !== null) {
                    $result = $this->importRow($presensiData, $classId, $sheet->getName(), $rowNumber);

                    $summary['created'] += $result['created'];
                    $summary['updated'] += $result['updated'];
                    $summary['skipped'] += $result['skipped'];

                    if ($result['message']) {
                        $summary['errors'][] = $result['message'];
                    }

                    continue;
                }

                if ($headers === []) {
                    $headers = $this->normalizeHeaders($values);

                    if (! $this->hasRequiredHeaders($headers)) {
                        $combinedHeaders = $this->combineHeaderRows($previousValues, $values);
                        $headers = $this->normalizeHeaders($combinedHeaders);
                    }

                    if (! $this->hasRequiredHeaders($headers)) {
                        $previousValues = $values;
                        continue;
                    }

                    continue;
                }

                if ($this->isEmptyRow($values)) {
                    continue;
                }

                $data = $this->rowData($headers, $values);
                $result = $this->importRow($data, $classId, $sheet->getName(), $rowNumber);

                $summary['created'] += $result['created'];
                $summary['updated'] += $result['updated'];
                $summary['skipped'] += $result['skipped'];

                if ($result['message']) {
                    $summary['errors'][] = $result['message'];
                }
            }
        }

        $reader->close();

        return $summary;
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<string, int>
     */
    private function normalizeHeaders(array $headers): array
    {
        $aliases = [
            'nisn' => 'nisn',
            'nis' => 'nis',
            'induk' => 'nis',
            'nomor_induk' => 'nis',
            'no_induk' => 'nis',
            'student_id' => 'nisn',
            'nama' => 'name',
            'nama_siswa' => 'name',
            'nama_murid' => 'name',
            'siswa' => 'name',
            'murid' => 'name',
            'l_p' => 'gender',
            'lp' => 'gender',
            'jk' => 'gender',
            'jenis_kelamin' => 'gender',
            'gender' => 'gender',
            'kelas' => 'class',
            'class' => 'class',
            'qrcode' => 'qr_code',
            'qr_code' => 'qr_code',
        ];

        $normalized = [];

        foreach ($headers as $index => $header) {
            $key = strtolower(trim($header));
            $key = preg_replace('/[^a-z0-9]+/', '_', $key);
            $key = trim((string) $key, '_');

            if (isset($aliases[$key])) {
                $normalized[$aliases[$key]] = $index;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<int, string>  $previous
     * @param  array<int, string>  $current
     * @return array<int, string>
     */
    private function combineHeaderRows(array $previous, array $current): array
    {
        $length = max(count($previous), count($current));
        $combined = [];

        for ($index = 0; $index < $length; $index++) {
            $currentValue = trim((string) Arr::get($current, $index, ''));
            $previousValue = trim((string) Arr::get($previous, $index, ''));
            $combined[$index] = $currentValue !== '' ? $currentValue : $previousValue;
        }

        return $combined;
    }

    /**
     * @param  array<string, int>  $headers
     * @param  array<int, string>  $values
     * @return array<string, string|null>
     */
    private function rowData(array $headers, array $values): array
    {
        return [
            'nisn' => $this->value($headers, $values, 'nisn'),
            'nis' => $this->value($headers, $values, 'nis'),
            'name' => $this->value($headers, $values, 'name'),
            'gender' => $this->value($headers, $values, 'gender'),
            'class' => $this->value($headers, $values, 'class'),
            'qr_code' => $this->value($headers, $values, 'qr_code'),
        ];
    }

    /**
     * @param  array<int, string>  $values
     * @return array<string, string|null>|null
     */
    private function presensiRowData(array $values): ?array
    {
        $nis = $this->numericText($values[1] ?? null);
        $name = trim((string) ($values[2] ?? ''));
        $gender = $this->normalizeGender((string) ($values[3] ?? ''));

        if (! is_numeric($values[0] ?? null) || blank($nis) || blank($name) || blank($gender)) {
            return null;
        }

        return [
            'nisn' => null,
            'nis' => $nis,
            'name' => $name,
            'gender' => $gender,
            'class' => null,
            'qr_code' => null,
        ];
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array{created: int, updated: int, skipped: int, message: string|null}
     */
    private function importRow(array $data, ?string $sheetClassId, string $sheetName, int $rowNumber): array
    {
        $nis = $this->numericText($data['nis']);
        $providedNisn = $this->numericText($data['nisn']);
        $nisn = $providedNisn ?: $nis;
        $name = trim((string) $data['name']);
        $gender = $this->normalizeGender((string) $data['gender']);
        $classId = $this->normalizeClassId((string) ($data['class'] ?: $sheetClassId));

        if (blank($nisn) && blank($name) && blank($data['gender'])) {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 1,
                'message' => null,
            ];
        }

        if (blank($nisn) || blank($name) || blank($gender) || blank($classId)) {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 1,
                'message' => "Sheet {$sheetName} baris {$rowNumber} dilewati: NIS/NISN, nama, jenis kelamin, atau kelas kosong.",
            ];
        }

        SchoolClass::firstOrCreate(['id' => $classId], ['name' => $classId]);

        $student = $this->findStudent($providedNisn, $nis);
        $attributes = [
            'nis' => $nis,
            'name' => $name,
            'class_id' => $classId,
            'gender' => $gender,
        ];

        if (filled($data['qr_code'])) {
            $attributes['qr_code'] = $data['qr_code'];
        }

        if ($student) {
            $student->update($attributes);

            return [
                'created' => 0,
                'updated' => 1,
                'skipped' => 0,
                'message' => null,
            ];
        }

        $student = Student::create([
            'nisn' => $nisn,
            ...$attributes,
        ]);

        return [
            'created' => 1,
            'updated' => 0,
            'skipped' => 0,
            'message' => null,
        ];
    }

    /**
     * @param  array<string, int>  $headers
     * @param  array<int, string>  $values
     */
    private function value(array $headers, array $values, string $key): ?string
    {
        $index = $headers[$key] ?? null;

        if ($index === null) {
            return null;
        }

        $value = Arr::get($values, $index);

        return filled($value) ? trim((string) $value) : null;
    }

    /**
     * @param  array<string, int>  $headers
     */
    private function hasRequiredHeaders(array $headers): bool
    {
        return isset($headers['name'])
            && (isset($headers['nis']) || isset($headers['nisn']))
            && isset($headers['gender']);
    }

    /**
     * @param  array<int, string>  $values
     */
    private function classIdFromRow(array $values): ?string
    {
        foreach ($values as $value) {
            if (preg_match('/\b(?:IX|VIII|VII)\s*-\s*([A-Z])\b/i', $value, $matches) === 1) {
                return match (strtoupper(preg_replace('/\s+/', '', $matches[0]))) {
                    'VII-'.$matches[1] => '7'.strtoupper($matches[1]),
                    'VIII-'.$matches[1] => '8'.strtoupper($matches[1]),
                    'IX-'.$matches[1] => '9'.strtoupper($matches[1]),
                    default => null,
                };
            }
        }

        return null;
    }

    private function normalizeClassId(?string $class): ?string
    {
        $class = strtoupper(trim((string) $class));

        if ($class === '') {
            return null;
        }

        $class = str_replace(['KELAS', 'CLASS', ' ', '-'], '', $class);
        $class = str_replace(['VII', 'VIII', 'IX'], ['7', '8', '9'], $class);

        return preg_match('/^[789][A-Z]$/', $class) === 1 ? $class : null;
    }

    private function normalizeGender(string $gender): ?string
    {
        $gender = strtoupper(trim($gender));

        return match ($gender) {
            'L', 'LAKI-LAKI', 'LAKI LAKI', 'MALE' => 'L',
            'P', 'PEREMPUAN', 'FEMALE' => 'P',
            default => null,
        };
    }

    private function numericText(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d+(?:\.0)?$/', $value) === 1) {
            return (string) (int) $value;
        }

        return $value;
    }

    private function findStudent(?string $nisn, ?string $nis): ?Student
    {
        if (filled($nisn)) {
            $student = Student::where('nisn', $nisn)->first();

            if ($student) {
                return $student;
            }
        }

        if (filled($nis)) {
            return Student::where('nis', $nis)
                ->get()
                ->sortBy(fn (Student $student): int => $student->nisn === $nis ? 1 : 0)
                ->first();
        }

        return null;
    }

    private function normalizeCellValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return trim((string) $value);
    }

    /**
     * @return array<int, string>
     */
    private function rowValues(Row $row): array
    {
        $values = [];

        for ($index = 0; $index < $row->getNumCells(); $index++) {
            $values[$index] = $this->normalizeCellValue(
                $row->getCellAtIndex($index)?->getValue(),
            );
        }

        return $values;
    }

    /**
     * @param  array<int, string>  $values
     */
    private function isEmptyRow(array $values): bool
    {
        return collect($values)->every(fn (string $value): bool => blank($value));
    }
}
