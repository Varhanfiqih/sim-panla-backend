<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\GradeCategoryResource;
use App\Filament\Resources\GradePeriodResource;
use App\Filament\Resources\ScheduleResource;
use App\Filament\Resources\StudentResource;
use App\Filament\Resources\UserResource;
use App\Models\GradeCategory;
use App\Models\GradePeriod;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use Filament\Widgets\Widget;

class DataHealthOverview extends Widget
{
    protected static string $view = 'filament.widgets.data-health-overview';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    protected function getViewData(): array
    {
        $studentsWithoutClass = Student::query()
            ->where(function ($query) {
                $query
                    ->whereNull('class_id')
                    ->orWhere('class_id', '')
                    ->orWhereDoesntHave('schoolClass');
            })
            ->count();

        $teachersWithoutSubject = User::query()
            ->where('role', User::ROLE_GURU)
            ->whereDoesntHave('subjects')
            ->count();

        $invalidSchedules = Schedule::query()
            ->where(function ($query) {
                $query
                    ->whereNull('teacher_id')
                    ->orWhere('teacher_id', '')
                    ->orWhereDoesntHave('teacher')
                    ->orWhereNull('subject_id')
                    ->orWhereDoesntHave('subject');
            })
            ->count();

        $activePeriods = GradePeriod::query()
            ->where('is_active', true)
            ->count();

        $activeCategories = GradeCategory::query()
            ->where('is_active', true)
            ->count();

        $checks = collect([
            [
                'label' => 'Siswa tanpa kelas',
                'description' => 'Siswa harus terhubung dengan kelas yang valid.',
                'issue_count' => $studentsWithoutClass,
                'healthy_text' => 'Semua siswa sudah memiliki kelas',
                'issue_text' => "{$studentsWithoutClass} siswa perlu diperbaiki",
                'url' => StudentResource::getUrl('index'),
                'action' => 'Kelola siswa',
                'icon' => 'heroicon-o-academic-cap',
            ],
            [
                'label' => 'Guru tanpa mata pelajaran',
                'description' => 'Guru reguler harus memiliki minimal satu mata pelajaran.',
                'issue_count' => $teachersWithoutSubject,
                'healthy_text' => 'Semua guru sudah memiliki mata pelajaran',
                'issue_text' => "{$teachersWithoutSubject} guru perlu dilengkapi",
                'url' => UserResource::getUrl('index'),
                'action' => 'Kelola guru',
                'icon' => 'heroicon-o-user-group',
            ],
            [
                'label' => 'Jadwal tanpa guru atau mapel',
                'description' => 'Setiap jadwal harus memiliki guru dan mata pelajaran yang valid.',
                'issue_count' => $invalidSchedules,
                'healthy_text' => 'Semua jadwal sudah valid',
                'issue_text' => "{$invalidSchedules} jadwal perlu diperbaiki",
                'url' => ScheduleResource::getUrl('index'),
                'action' => 'Kelola jadwal',
                'icon' => 'heroicon-o-calendar-days',
            ],
            [
                'label' => 'Periode nilai aktif',
                'description' => 'Minimal satu periode nilai harus aktif untuk proses penilaian.',
                'issue_count' => $activePeriods > 0 ? 0 : 1,
                'healthy_text' => "{$activePeriods} periode nilai aktif",
                'issue_text' => 'Belum ada periode nilai aktif',
                'url' => GradePeriodResource::getUrl('index'),
                'action' => 'Kelola periode',
                'icon' => 'heroicon-o-calendar',
            ],
            [
                'label' => 'Kategori nilai tersedia',
                'description' => 'Kategori aktif diperlukan sebelum guru mengisi nilai.',
                'issue_count' => $activeCategories > 0 ? 0 : 1,
                'healthy_text' => "{$activeCategories} kategori nilai aktif",
                'issue_text' => 'Belum ada kategori nilai aktif',
                'url' => GradeCategoryResource::getUrl('index'),
                'action' => 'Kelola kategori',
                'icon' => 'heroicon-o-clipboard-document-list',
            ],
        ]);

        return [
            'checks' => $checks,
            'healthyCount' => $checks->where('issue_count', 0)->count(),
            'issueCount' => $checks->where('issue_count', '>', 0)->count(),
        ];
    }
}
