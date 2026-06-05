<?php

namespace App\Filament\Widgets;

use App\Models\Journal;
use App\Models\Schedule;
use App\Models\TeacherAttendance;
use Carbon\Carbon;
use Filament\Widgets\Widget;

class TeacherJournalMonitoring extends Widget
{
    protected static string $view = 'filament.widgets.teacher-journal-monitoring';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user?->isKepsek() || $user?->isAdminIT();
    }

    protected function getViewData(): array
    {
        $now = now();
        $today = $now->toDateString();
        $day = strtoupper($now->copy()->locale('id')->isoFormat('dddd'));

        $schedules = Schedule::query()
            ->with(['teacher', 'timeSlot', 'subject', 'schoolClass'])
            ->where('day_of_week', $day)
            ->whereHas('timeSlot', fn ($query) => $query->where('type', 'KBM'))
            ->get()
            ->filter(fn (Schedule $schedule) => $schedule->teacher && $schedule->timeSlot)
            ->sortBy(fn (Schedule $schedule) => sprintf(
                '%s|%s|%08d',
                $schedule->teacher->name,
                $schedule->timeSlot->start_time,
                $schedule->id,
            ))
            ->values();

        $journals = Journal::query()
            ->whereDate('created_at', $today)
            ->get()
            ->keyBy('schedule_id');

        $absentUserIds = TeacherAttendance::query()
            ->whereDate('date', $today)
            ->where('status', 'tidak_hadir')
            ->pluck('user_id')
            ->all();

        $rows = collect();

        foreach ($schedules->groupBy('teacher_id') as $teacherSchedules) {
            $groups = [];
            $current = null;

            foreach ($teacherSchedules as $schedule) {
                $sameBlock = $current
                    && $current['subject_id'] === $schedule->subject_id
                    && $current['class_id'] === $schedule->class_id;

                if ($sameBlock) {
                    $current['end_time'] = $schedule->timeSlot->end_time;
                    $current['schedule_ids'][] = $schedule->id;
                    continue;
                }

                if ($current) {
                    $groups[] = $current;
                }

                $current = [
                    'teacher' => $schedule->teacher,
                    'subject_id' => $schedule->subject_id,
                    'class_id' => $schedule->class_id,
                    'subject' => $schedule->subject?->name ?? '-',
                    'class' => $schedule->schoolClass?->name ?? (string) $schedule->class_id,
                    'start_time' => $schedule->timeSlot->start_time,
                    'end_time' => $schedule->timeSlot->end_time,
                    'schedule_ids' => [$schedule->id],
                ];
            }

            if ($current) {
                $groups[] = $current;
            }

            foreach ($groups as $group) {
                $journal = collect($group['schedule_ids'])
                    ->map(fn ($scheduleId) => $journals->get($scheduleId))
                    ->filter()
                    ->sortByDesc('created_at')
                    ->first();

                $startAt = Carbon::parse($today . ' ' . $group['start_time']);
                $isAbsent = in_array($group['teacher']->id, $absentUserIds, true);

                [$status, $color] = match (true) {
                    (bool) $journal => ['Sudah mengisi', 'success'],
                    $isAbsent => ['Tidak hadir', 'gray'],
                    $now->lt($startAt) => ['Belum waktunya', 'info'],
                    default => ['Belum mengisi', 'danger'],
                };

                $rows->push([
                    'teacher' => $group['teacher']->name,
                    'nip' => $group['teacher']->nip,
                    'subject' => $group['subject'],
                    'class' => $group['class'],
                    'time' => substr($group['start_time'], 0, 5) . ' - ' . substr($group['end_time'], 0, 5),
                    'status' => $status,
                    'color' => $color,
                    'submitted_at' => $journal?->created_at?->format('H:i'),
                    'material' => $journal?->material,
                ]);
            }
        }

        return [
            'rows' => $rows,
            'dateLabel' => $now->copy()->locale('id')->translatedFormat('l, d F Y'),
            'summary' => [
                'total' => $rows->count(),
                'done' => $rows->where('status', 'Sudah mengisi')->count(),
                'pending' => $rows->where('status', 'Belum mengisi')->count(),
                'upcoming' => $rows->where('status', 'Belum waktunya')->count(),
            ],
        ];
    }
}
