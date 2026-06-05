<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GradeCategory;
use App\Models\GradePeriod;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GradeController extends Controller
{
    private function homeroomClass(Request $request)
    {
        $class = $request->user()->homeroomClass;

        if (!$class) {
            abort(403, 'Fitur Penilaian hanya tersedia untuk wali kelas.');
        }

        return $class;
    }

    private function errorResponse(\Throwable $e)
    {
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException && $e->getStatusCode() === 403) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 403);
        }

        throw $e;
    }

    private function populatedTable(array $candidates): ?string
    {
        foreach ($candidates as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                return $table;
            }
        }

        foreach ($candidates as $table) {
            if (Schema::hasTable($table)) {
                return $table;
            }
        }

        return null;
    }

    private function periodData(): array
    {
        $table = $this->populatedTable([
            'assessment_periods',
            'grade_periods',
        ]);
        if (!$table) {
            return ['active' => null, 'items' => collect()];
        }

        $query = DB::table($table);
        $activeColumn = match (true) {
            Schema::hasColumn($table, 'is_active') => 'is_active',
            Schema::hasColumn($table, 'active') => 'active',
            default => null,
        };

        if ($activeColumn) {
            $query->orderByDesc($activeColumn);
        }

        $items = $query->orderByDesc('id')->get()->map(fn ($period) => [
            'id' => (int) $period->id,
            'name' => (string) ($period->name ?? $period->title ?? $period->label ?? '-'),
        ])->values();

        $active = null;
        if ($activeColumn) {
            $activeRow = DB::table($table)->where($activeColumn, true)->orderByDesc('id')->first();
            if ($activeRow) {
                $active = [
                    'id' => (int) $activeRow->id,
                    'name' => (string) ($activeRow->name ?? $activeRow->title ?? $activeRow->label ?? '-'),
                ];
            }
        }
        $active ??= $items->first();

        return ['active' => $active, 'items' => $items];
    }

    private function categoryData()
    {
        $table = $this->populatedTable([
            'assessment_categories',
            'grade_categories',
        ]);
        if (!$table) {
            return collect();
        }

        $query = DB::table($table);
        $activeColumn = match (true) {
            Schema::hasColumn($table, 'is_active') => 'is_active',
            Schema::hasColumn($table, 'active') => 'active',
            default => null,
        };
        if ($activeColumn) {
            $query->where($activeColumn, true);
        }

        $orderColumn = match (true) {
            Schema::hasColumn($table, 'sort_order') => 'sort_order',
            Schema::hasColumn($table, 'order') => 'order',
            default => 'id',
        };

        return $query->orderBy($orderColumn)->get()->map(fn ($category) => [
            'id' => (int) $category->id,
            'name' => (string) ($category->name ?? $category->title ?? $category->label ?? '-'),
            'is_repeatable' => (bool) (
                $category->is_repeatable ??
                $category->is_multi ??
                $category->multi ??
                false
            ),
            'max_item' => (int) ($category->max_item ?? $category->maximum_item ?? 1),
            'max_score' => (float) ($category->max_score ?? $category->maximum_score ?? 100),
        ])->values();
    }

    public function meta(Request $request)
    {
        try {
            $class = $this->homeroomClass($request);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }

        // Wali kelas menginput nilai seluruh mata pelajaran yang terjadwal
        // di kelasnya, bukan hanya mata pelajaran yang diampu akun tersebut.
        $subjects = Subject::query()
            ->whereHas('schedules', fn ($query) => $query->where('class_id', $class->id))
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        if ($subjects->isEmpty()) {
            $subjects = Subject::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        }

        $periodData = $this->periodData();

        return response()->json([
            'status' => 'success',
            'data' => [
                'class_id' => (string) $class->id,
                'period_active' => $periodData['active'],
                'periods' => $periodData['items'],
                'subjects' => $subjects,
                'categories' => $this->categoryData(),
            ],
        ]);
    }

    public function students(Request $request)
    {
        try {
            $class = $this->homeroomClass($request);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'class_id' => (string) $class->id,
                'students' => Student::where('class_id', $class->id)
                    ->orderBy('name')
                    ->get(['id', 'nisn', 'nis', 'name']),
            ],
        ]);
    }

    public function scores(Request $request)
    {
        try {
            $this->homeroomClass($request);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
        $validated = $request->validate([
            'period_id' => 'required|integer|exists:grade_periods,id',
            'subject_id' => 'required|integer|exists:subjects,id',
            'category_id' => 'required|integer|exists:grade_categories,id',
            'item_no' => 'nullable|integer|min:1',
        ]);

        $scores = StudentGrade::query()
            ->where('grade_period_id', $validated['period_id'])
            ->where('subject_id', $validated['subject_id'])
            ->where('grade_category_id', $validated['category_id'])
            ->where('item_no', $validated['item_no'] ?? 1)
            ->get()
            ->map(fn ($grade) => [
                'student_id' => $grade->student?->id,
                'score' => (float) $grade->score,
                'notes' => $grade->notes,
            ])
            ->filter(fn ($grade) => !empty($grade['student_id']))
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => ['scores' => $scores],
        ]);
    }

    public function bulkUpsert(Request $request)
    {
        try {
            $class = $this->homeroomClass($request);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
        $validated = $request->validate([
            'period_id' => 'required|integer|exists:grade_periods,id',
            'subject_id' => 'required|integer|exists:subjects,id',
            'entries' => 'required|array',
            'entries.*.student_id' => 'required|integer|exists:students,id',
            'entries.*.category_id' => 'required|integer|exists:grade_categories,id',
            'entries.*.item_no' => 'nullable|integer|min:1',
            'entries.*.score' => 'required|numeric|min:0|max:100',
            'entries.*.notes' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($validated, $class) {
            foreach ($validated['entries'] as $entry) {
                $student = Student::where('id', $entry['student_id'])
                    ->where('class_id', $class->id)
                    ->firstOrFail();

                StudentGrade::updateOrCreate(
                    [
                        'student_nisn' => $student->nisn,
                        'subject_id' => $validated['subject_id'],
                        'grade_category_id' => $entry['category_id'],
                        'grade_period_id' => $validated['period_id'],
                        'item_no' => $entry['item_no'] ?? 1,
                    ],
                    [
                        'score' => $entry['score'],
                        'notes' => $entry['notes'] ?? null,
                    ]
                );
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Nilai berhasil disimpan.',
        ]);
    }

    public function summary(Request $request)
    {
        try {
            $class = $this->homeroomClass($request);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
        $validated = $request->validate([
            'period_id' => 'required|integer|exists:grade_periods,id',
            'subject_id' => 'required|integer|exists:subjects,id',
            'category_id' => 'required|integer|exists:grade_categories,id',
            'item_no' => 'nullable|integer|min:1',
        ]);

        $students = Student::where('class_id', $class->id)->get(['id', 'nisn']);
        $nisns = $students->pluck('nisn');
        $grades = StudentGrade::whereIn('student_nisn', $nisns)
            ->where('grade_period_id', $validated['period_id'])
            ->where('subject_id', $validated['subject_id'])
            ->where('grade_category_id', $validated['category_id'])
            ->where('item_no', $validated['item_no'] ?? 1)
            ->get();

        $completed = $grades->where('score', '>', 0)->count();
        $average = $completed > 0 ? round($grades->where('score', '>', 0)->avg('score'), 2) : 0;
        $topScore = $grades->max('score') ?? 0;

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_students' => $students->count(),
                'completed' => $completed,
                'pending' => max($students->count() - $completed, 0),
                'average' => (float) $average,
                'top_score' => (float) $topScore,
                'pass_rate' => $students->count() > 0 ? round(($completed / $students->count()) * 100, 2) : 0,
                'last_synced_at' => $grades->max('updated_at'),
                'is_locked' => false,
            ],
        ]);
    }

    public function finishLock(Request $request)
    {
        try {
            $this->homeroomClass($request);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Input nilai selesai.',
        ]);
    }
}
