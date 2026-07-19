<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Support\Arr;
use OpenSpout\Reader\XLSX\Reader;

class ScheduleExcelImportService
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
            $headers = [];

            foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                $values = array_map(
                    fn ($value): string => $this->normalizeCellValue($value),
                    $row->toArray(),
                );

                if ($rowNumber === 1) {
                    $headers = $this->normalizeHeaders($values);
                    continue;
                }

                if ($this->isEmptyRow($values)) {
                    continue;
                }

                $data = $this->rowData($headers, $values);
                $result = $this->importRow($data, $rowNumber);
                $summary[$result['status']]++;

                if ($result['message']) {
                    $summary['errors'][] = $result['message'];
                }
            }

            break;
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
            'hari' => 'day',
            'day' => 'day',
            'kelas' => 'class',
            'class' => 'class',
            'jam_mulai' => 'start_time',
            'mulai' => 'start_time',
            'start' => 'start_time',
            'start_time' => 'start_time',
            'waktu_mulai' => 'start_time',
            'jam_selesai' => 'end_time',
            'selesai' => 'end_time',
            'end' => 'end_time',
            'end_time' => 'end_time',
            'waktu_selesai' => 'end_time',
            'mata_pelajaran' => 'subject',
            'mapel' => 'subject',
            'subject' => 'subject',
            'pelajaran' => 'subject',
            'nip_guru' => 'teacher_nip',
            'nip' => 'teacher_nip',
            'teacher_nip' => 'teacher_nip',
            'nama_guru' => 'teacher_name',
            'guru' => 'teacher_name',
            'teacher' => 'teacher_name',
            'keterangan' => 'description',
            'catatan' => 'description',
            'description' => 'description',
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
     * @param  array<string, int>  $headers
     * @param  array<int, string>  $values
     * @return array<string, string|null>
     */
    private function rowData(array $headers, array $values): array
    {
        return [
            'day' => $this->value($headers, $values, 'day'),
            'class' => $this->value($headers, $values, 'class'),
            'start_time' => $this->value($headers, $values, 'start_time'),
            'end_time' => $this->value($headers, $values, 'end_time'),
            'subject' => $this->value($headers, $values, 'subject'),
            'teacher_nip' => $this->value($headers, $values, 'teacher_nip'),
            'teacher_name' => $this->value($headers, $values, 'teacher_name'),
            'description' => $this->value($headers, $values, 'description'),
        ];
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array{status: 'created'|'updated'|'skipped', message: string|null}
     */
    private function importRow(array $data, int $rowNumber): array
    {
        $required = ['day', 'class', 'start_time', 'end_time', 'subject'];

        foreach ($required as $field) {
            if (blank($data[$field])) {
                return [
                    'status' => 'skipped',
                    'message' => "Baris {$rowNumber} dilewati: kolom {$field} kosong.",
                ];
            }
        }

        $day = $this->normalizeDay((string) $data['day']);

        if (! $day) {
            return [
                'status' => 'skipped',
                'message' => "Baris {$rowNumber} dilewati: hari tidak valid.",
            ];
        }

        $startTime = $this->normalizeTime((string) $data['start_time']);
        $endTime = $this->normalizeTime((string) $data['end_time']);

        if (! $startTime || ! $endTime) {
            return [
                'status' => 'skipped',
                'message' => "Baris {$rowNumber} dilewati: format jam tidak valid.",
            ];
        }

        $teacher = $this->findTeacher($data);

        if (! $teacher) {
            return [
                'status' => 'skipped',
                'message' => "Baris {$rowNumber} dilewati: guru tidak ditemukan.",
            ];
        }

        $classId = strtoupper(str_replace(' ', '', (string) $data['class']));
        SchoolClass::firstOrCreate(['id' => $classId], ['name' => $classId]);

        $subject = Subject::firstOrCreate([
            'name' => trim((string) $data['subject']),
        ]);

        $timeSlot = TimeSlot::firstOrCreate([
            'start_time' => $startTime,
            'end_time' => $endTime,
        ], [
            'type' => 'KBM',
        ]);

        $schedule = Schedule::updateOrCreate([
            'day_of_week' => $day,
            'class_id' => $classId,
            'time_slot_id' => $timeSlot->id,
        ], [
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->nip,
            'keterangan' => filled($data['description']) ? $data['description'] : null,
        ]);

        return [
            'status' => $schedule->wasRecentlyCreated ? 'created' : 'updated',
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

    private function normalizeDay(string $day): ?string
    {
        $key = strtoupper(trim($day));

        $days = [
            'SENIN' => 'SENIN',
            'MONDAY' => 'SENIN',
            'SELASA' => 'SELASA',
            'TUESDAY' => 'SELASA',
            'RABU' => 'RABU',
            'WEDNESDAY' => 'RABU',
            'KAMIS' => 'KAMIS',
            'THURSDAY' => 'KAMIS',
            "JUM'AT" => 'JUMAT',
            'JUMAT' => 'JUMAT',
            'FRIDAY' => 'JUMAT',
            'SABTU' => 'SABTU',
            'SATURDAY' => 'SABTU',
        ];

        return $days[$key] ?? null;
    }

    private function normalizeTime(string $time): ?string
    {
        $time = trim($time);

        if (is_numeric($time) && (float) $time >= 0 && (float) $time < 1) {
            $minutes = (int) round((float) $time * 24 * 60);

            return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
        }

        $time = str_replace('.', ':', $time);

        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $time, $matches) !== 1) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour > 23 || $minute > 59) {
            return null;
        }

        return sprintf('%02d:%02d:00', $hour, $minute);
    }

    private function normalizeCellValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        return trim((string) $value);
    }

    /**
     * @param  array<string, string|null>  $data
     */
    private function findTeacher(array $data): ?User
    {
        if (filled($data['teacher_nip'])) {
            $teacher = User::where('nip', (string) $data['teacher_nip'])->first();

            if ($teacher) {
                return $teacher;
            }
        }

        if (filled($data['teacher_name'])) {
            return User::where('name', (string) $data['teacher_name'])->first();
        }

        return null;
    }

    /**
     * @param  array<int, string>  $values
     */
    private function isEmptyRow(array $values): bool
    {
        return collect($values)->every(fn (string $value): bool => blank($value));
    }
}
