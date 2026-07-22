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

                if ($headers === []) {
                    $headers = $this->normalizeHeaders($values);

                    if (! $this->hasRequiredHeaders($headers)) {
                        continue;
                    }

                    continue;
                }

                if ($this->isEmptyRow($values)) {
                    continue;
                }

                $data = $this->rowData($headers, $values);
                $result = $this->importRow($data, $rowNumber);
                $summary['created'] += $result['created'];
                $summary['updated'] += $result['updated'];
                $summary['skipped'] += $result['skipped'];

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
            'jam_ke' => 'periods',
            'jam' => 'periods',
            'periode' => 'periods',
            'period' => 'periods',
            'periods' => 'periods',
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
            'periods' => $this->value($headers, $values, 'periods'),
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
     * @return array{created: int, updated: int, skipped: int, message: string|null}
     */
    private function importRow(array $data, int $rowNumber): array
    {
        $required = ['day', 'class', 'subject'];

        foreach ($required as $field) {
            if (blank($data[$field])) {
                return [
                    'created' => 0,
                    'updated' => 0,
                    'skipped' => 1,
                    'message' => "Baris {$rowNumber} dilewati: kolom {$field} kosong.",
                ];
            }
        }

        $day = $this->normalizeDay((string) $data['day']);

        if (! $day) {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 1,
                'message' => "Baris {$rowNumber} dilewati: hari tidak valid.",
            ];
        }

        $timeSlots = $this->timeSlotsForRow($data);

        if ($timeSlots === []) {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 1,
                'message' => "Baris {$rowNumber} dilewati: kolom Jam ke atau format jam tidak valid.",
            ];
        }

        $teacher = $this->findTeacher($data);

        if (! $teacher) {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 1,
                'message' => "Baris {$rowNumber} dilewati: guru tidak ditemukan.",
            ];
        }

        $classId = strtoupper(str_replace(' ', '', (string) $data['class']));
        SchoolClass::firstOrCreate(['id' => $classId], ['name' => $classId]);

        $subject = $this->findOrCreateSubject((string) $data['subject']);
        $created = 0;
        $updated = 0;

        foreach ($timeSlots as $timeSlot) {
            $schedule = Schedule::updateOrCreate([
                'day_of_week' => $day,
                'class_id' => $classId,
                'time_slot_id' => $timeSlot->id,
            ], [
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->nip,
                'keterangan' => filled($data['description']) ? $data['description'] : null,
            ]);

            if ($schedule->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
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
            $needle = $this->normalizeSearchText((string) $data['teacher_name']);

            return User::query()
                ->get()
                ->first(fn (User $user): bool => $this->normalizeSearchText((string) $user->name) === $needle)
                ?? User::query()
                    ->get()
                    ->first(fn (User $user): bool => str_contains($this->normalizeSearchText((string) $user->name), $needle));
        }

        return null;
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<int, TimeSlot>
     */
    private function timeSlotsForRow(array $data): array
    {
        if (filled($data['periods'])) {
            return collect($this->parsePeriods((string) $data['periods']))
                ->map(fn (int $period): TimeSlot => $this->timeSlotForPeriod($period))
                ->filter()
                ->values()
                ->all();
        }

        $startTime = $this->normalizeTime((string) $data['start_time']);
        $endTime = $this->normalizeTime((string) $data['end_time']);

        if (! $startTime || ! $endTime) {
            return [];
        }

        return [
            TimeSlot::firstOrCreate([
                'start_time' => $startTime,
                'end_time' => $endTime,
            ], [
                'type' => 'KBM',
            ]),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function parsePeriods(string $periods): array
    {
        $periods = trim(str_replace(' ', '', $periods));

        if (preg_match('/^(\d+)-(\d+)$/', $periods, $matches) === 1) {
            $start = (int) $matches[1];
            $end = (int) $matches[2];

            if ($start > $end) {
                return [];
            }

            return range($start, $end);
        }

        if (preg_match('/^\d+$/', $periods) === 1) {
            return [(int) $periods];
        }

        return collect(explode(',', $periods))
            ->map(fn (string $period): int => (int) $period)
            ->filter(fn (int $period): bool => $period > 0)
            ->values()
            ->all();
    }

    private function findOrCreateSubject(string $name): Subject
    {
        $needle = $this->normalizeSearchText($name);
        $subject = Subject::query()
            ->get()
            ->first(fn (Subject $subject): bool => $this->normalizeSearchText((string) $subject->name) === $needle);

        return $subject ?? Subject::create(['name' => trim($name)]);
    }

    private function timeSlotForPeriod(int $period): TimeSlot
    {
        $timeSlot = TimeSlot::find($period);

        if ($timeSlot) {
            return $timeSlot;
        }

        $timeSlot = new TimeSlot();
        $timeSlot->forceFill([
            'id' => $period,
            'start_time' => sprintf('%02d:00:00', 6 + $period),
            'end_time' => sprintf('%02d:45:00', 6 + $period),
            'type' => 'KBM',
        ]);
        $timeSlot->save();

        return $timeSlot;
    }

    /**
     * @param  array<string, int>  $headers
     */
    private function hasRequiredHeaders(array $headers): bool
    {
        return isset($headers['day'], $headers['class'], $headers['subject'])
            && (isset($headers['periods']) || (isset($headers['start_time'], $headers['end_time'])))
            && (isset($headers['teacher_nip']) || isset($headers['teacher_name']));
    }

    private function normalizeSearchText(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/\b(s|m|drs?|dra|ir|h|hj|spd|skom|spsi|mpd|pd|kom|psi)\b/u', '', $value);
        $value = preg_replace('/[^a-z0-9]+/', '', (string) $value);

        return (string) $value;
    }

    /**
     * @param  array<int, string>  $values
     */
    private function isEmptyRow(array $values): bool
    {
        return collect($values)->every(fn (string $value): bool => blank($value));
    }
}
