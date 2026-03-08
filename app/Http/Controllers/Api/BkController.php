<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\StudentNote;
use App\Models\Student;
use App\Models\BkAction;
use Carbon\Carbon;

class BkController extends Controller
{
    /**
     * Guard: hanya Guru BK yang boleh akses
     */
    private function assertBkRole(Request $request): ?\Illuminate\Http\JsonResponse
    {
        if ($request->user()->role !== 'Guru BK') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Akses ditolak. Endpoint ini khusus Guru BK.',
            ], 403);
        }
        return null;
    }

    /**
     * Daftar siswa ALPA hari ini (dari student_notes jurnal guru)
     * GET /api/v1/bk/absentees?date=2026-03-04
     */
    public function absentees(Request $request)
    {
        if ($err = $this->assertBkRole($request)) return $err;

        try {
            $date = $request->query('date', Carbon::today()->toDateString());

            // Ambil semua student_note dengan status alpa yang dibuat hari ini
            $alpaList = StudentNote::with(['student', 'journal.teacher'])
                ->whereIn('note_type', ['KBM_Alpa', 'Alpa'])
                ->whereHas('journal', fn($q) => $q->whereDate('created_at', $date))
                ->get()
                ->map(function ($note) {
                    // Cek apakah sudah pernah dikonfirmasi BK hari ini
                    $sudahKonfirmasi = BkAction::where('student_id', $note->student_id)
                        ->whereDate('tanggal_kejadian', $note->journal?->created_at?->toDateString())
                        ->exists();

                    return [
                        'note_id'        => $note->id,
                        'student'        => [
                            'id'       => $note->student?->id,
                            'name'     => $note->student?->name,
                            'nis'      => $note->student?->nis,
                            'class_id' => $note->student?->class_id,
                        ],
                        'mapel'          => $note->journal?->schedule?->subject_name ?? 'Tidak diketahui',
                        'guru'           => $note->journal?->teacher?->name ?? 'Tidak diketahui',
                        'jam_jurnal'     => $note->journal?->created_at?->format('H:i'),
                        'notes'          => $note->notes,
                        'sudah_konfirmasi' => $sudahKonfirmasi,
                    ];
                });

            // Group by siswa (satu siswa bisa alpa di banyak mapel)
            $grouped = $alpaList->groupBy('student.id')->map(function ($items) {
                $first = $items->first();
                return [
                    'student'        => $first['student'],
                    'total_mapel_alpa'=> $items->count(),
                    'detail_mapel'   => $items->map(fn($i) => [
                        'note_id'    => $i['note_id'],
                        'mapel'      => $i['mapel'],
                        'guru'       => $i['guru'],
                        'jam'        => $i['jam_jurnal'],
                    ])->values(),
                    'sudah_konfirmasi' => $items->every(fn($i) => $i['sudah_konfirmasi']),
                ];
            })->values();

            return response()->json([
                'status' => 'success',
                'date'   => $date,
                'total'  => $grouped->count(),
                'data'   => $grouped,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal mengambil daftar absensi.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Konfirmasi & update status siswa alpa oleh BK
     * POST /api/v1/bk/confirm
     */
    public function confirm(Request $request)
    {
        if ($err = $this->assertBkRole($request)) return $err;

        $request->validate([
            'student_id'     => 'required|integer|exists:students,id',
            'action_type'    => 'required|in:panggilan_ortu,home_visit,surat_peringatan,konseling,lainnya',
            'status_sesudah' => 'required|in:KBM_Sakit,KBM_Izin,tetap_alpa',
            'notes'          => 'nullable|string|max:1000',
            'tanggal_kejadian' => 'nullable|date',
        ]);

        try {
            $user = $request->user();
            $tanggal = $request->tanggal_kejadian
                ? Carbon::parse($request->tanggal_kejadian)
                : Carbon::today();

            $action = BkAction::create([
                'student_id'         => $request->student_id,
                'handled_by_user_id' => $user->nip,
                'action_type'        => $request->action_type,
                'notes'              => $request->notes,
                'status_sebelum'     => 'KBM_Alpa',
                'status_sesudah'     => $request->status_sesudah,
                'tanggal_kejadian'   => $tanggal,
            ]);

            // Jika BK update status → update student_notes juga
            if ($request->status_sesudah !== 'tetap_alpa') {
                StudentNote::whereHas('journal', fn($q) => $q->whereDate('created_at', $tanggal))
                    ->where('student_id', $request->student_id)
                    ->whereIn('note_type', ['KBM_Alpa', 'Alpa'])
                    ->update(['note_type' => $request->status_sesudah]);

                $student = Student::find($request->student_id);
                if ($student) {
                    event(new \App\Events\StudentStatusUpdated(
                        $request->student_id,
                        $student->class_id,
                        $request->status_sesudah,
                        'BK'
                    ));
                }
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Konfirmasi BK berhasil dicatat.',
                'data'    => ['action_id' => $action->id],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal menyimpan konfirmasi BK.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cari siswa untuk di-follow up
     * GET /api/v1/bk/students?q=nama
     */
    public function students(Request $request)
    {
        if ($err = $this->assertBkRole($request)) return $err;

        $search = $request->query('q');
        
        $students = Student::when($search, function($q, $search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%");
            })
            ->select('id', 'name', 'nis', 'class_id')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $students,
        ]);
    }

    /**
     * Simpan Surat Peringatan / Sanksi
     * POST /api/v1/bk/action
     */
    public function storeAction(Request $request)
    {
        if ($err = $this->assertBkRole($request)) return $err;

        $request->validate([
            'student_id'  => 'required|integer|exists:students,id',
            'action_type' => 'required|string',
            'notes'       => 'required|string',
            'tanggal_kejadian' => 'nullable|date',
        ]);

        try {
            $user = $request->user();
            $tanggal = $request->tanggal_kejadian
                ? Carbon::parse($request->tanggal_kejadian)
                : Carbon::today();

            $action = BkAction::create([
                'student_id'         => $request->student_id,
                'handled_by_user_id' => $user->nip,
                'action_type'        => $request->action_type,
                'notes'              => $request->notes,
                'status_sebelum'     => null, // Tindakan murni (sanksi independen)
                'status_sesudah'     => null,
                'tanggal_kejadian'   => $tanggal,
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Tindak Lanjut / Sanksi berhasil dicatat.',
                'data'    => ['action_id' => $action->id],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal menyimpan Tindak Lanjut BK.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Riwayat tindakan BK untuk seorang siswa
     * GET /api/v1/bk/history/{student_id}
     */
    public function history($studentId)
    {
        try {
            $actions = BkAction::with(['handler', 'student'])
                ->where('student_id', $studentId)
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($action) {
                    return [
                        'id'             => $action->id,
                        'action_type'    => $action->action_type,
                        'notes'          => $action->notes,
                        'status_sebelum' => $action->status_sebelum,
                        'status_sesudah' => $action->status_sesudah,
                        'tanggal'        => $action->tanggal_kejadian?->format('d M Y'),
                        'ditangani_oleh' => $action->handler?->name,
                        'created_at'     => $action->created_at?->format('d M Y H:i'),
                    ];
                });

            $student = Student::find($studentId);

            return response()->json([
                'status'  => 'success',
                'student' => ['id' => $student?->id, 'name' => $student?->name, 'class_id' => $student?->class_id],
                'total'   => $actions->count(),
                'data'    => $actions,
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Semua catatan pelanggaran/sanksi BK
     * GET /api/v1/bk/actions
     */
    public function allActions(Request $request)
    {
        if ($err = $this->assertBkRole($request)) return $err;

        try {
            $actions = BkAction::with(['student', 'student.schoolClass', 'handler'])
                ->orderByDesc('tanggal_kejadian')
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($action) {
                    return [
                        'id'             => $action->id,
                        'action_type'    => $action->action_type,
                        'notes'          => $action->notes,
                        'tanggal'        => $action->tanggal_kejadian?->format('d M Y'),
                        'ditangani_oleh' => $action->handler?->name,
                        'created_at'     => $action->created_at?->format('d M Y H:i'),
                        'student'        => [
                            'id'    => $action->student?->id,
                            'name'  => $action->student?->name,
                            'nis'   => $action->student?->nis,
                            'kelas' => $action->student?->schoolClass?->name ?? 'Kelas ' . $action->student?->class_id,
                        ],
                    ];
                });

            return response()->json([
                'status' => 'success',
                'total'  => $actions->count(),
                'data'   => $actions,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }


    /**
     * Pantau Status Absensi Kelas Hari ini
     * GET /api/v1/bk/monitoring
     */
    public function monitoring(Request $request)
    {
        if ($err = $this->assertBkRole($request)) return $err;

        try {
            Carbon::setLocale('id');
            $today     = Carbon::today()->toDateString();
            $dayOfWeek = strtoupper(Carbon::now()->isoFormat('dddd'));

            // Semua jadwal hari ini
            $schedules = \App\Models\Schedule::with(['schoolClass.homeroomTeacher', 'subject', 'teacher', 'timeSlot'])
                ->where('day_of_week', $dayOfWeek)
                ->get();

            // Schedule IDs yang sudah punya journal hari ini
            $journaledIds = \App\Models\Journal::whereDate('created_at', $today)
                ->pluck('schedule_id')
                ->toArray();

            // Total alpa per kelas hari ini
            $alpaPerKelas = StudentNote::whereIn('note_type', ['KBM_Alpa', 'Alpa'])
                ->whereHas('journal', fn($q) => $q->whereDate('created_at', $today))
                ->with('student:id,class_id')
                ->get()
                ->groupBy(fn($n) => $n->student?->class_id)
                ->map(fn($g) => $g->count());

            // Group by kelas
            $grouped = $schedules->groupBy(fn($s) => $s->class_id ?? $s->schoolClass?->id);

            $result = $grouped->map(function ($kelasSchedules, $classId) use ($journaledIds, $alpaPerKelas) {
                $firstSched  = $kelasSchedules->first();
                $schoolClass = $firstSched->schoolClass;

                // Kelas dianggap "sudah absen" jika setidaknya 1 jadwalnya hari ini sudah ada journal
                $sudahAbsen = $kelasSchedules->contains(fn($s) => in_array($s->id, $journaledIds));

                // Merge jadwal yang sama mapel+guru → tampilkan sebagai satu baris dengan rentang jam
                $jadwalMerged = $kelasSchedules
                    ->sortBy(fn($s) => $s->timeSlot?->start_time ?? '99:99')
                    ->groupBy(fn($s) => ($s->subject?->name ?? '-') . '||' . ($s->teacher?->name ?? '-'))
                    ->map(function ($group) use ($journaledIds) {
                        $times = $group
                            ->map(fn($s) => $s->timeSlot?->start_time ? substr($s->timeSlot->start_time, 0, 5) : null)
                            ->filter()
                            ->sort()
                            ->values();

                        $jamLabel = $times->count() > 1
                            ? $times->first() . ' – ' . $times->last()
                            : ($times->first() ?? '-');

                        return [
                            'mapel'        => $group->first()->subject?->name ?? '-',
                            'guru'         => $group->first()->teacher?->name ?? '-',
                            'jam'          => $jamLabel,
                            'sudah_jurnal' => $group->contains(fn($s) => in_array($s->id, $journaledIds)),
                        ];
                    })
                    ->values();

                return [
                    'kelas'        => $classId,
                    'nama_kelas'   => $schoolClass?->name ?? $classId,
                    'wali_kelas'   => $schoolClass?->homeroomTeacher?->name ?? 'Belum Ditetapkan',
                    'total_alpa'   => $alpaPerKelas[$classId] ?? 0,
                    'total_jadwal' => $jadwalMerged->count(),
                    'sudah_absen'  => $sudahAbsen,
                    'jadwal'       => $jadwalMerged,
                ];
            })->values();

            // Sort: belum absen dulu
            $sorted = $result->sortBy('sudah_absen')->values();

            $totalKelas  = $sorted->count();
            $sudahAbsen  = $sorted->where('sudah_absen', true)->count();
            $belumAbsen  = $totalKelas - $sudahAbsen;

            return response()->json([
                'status'  => 'success',
                'summary' => [
                    'total_kelas'  => $totalKelas,
                    'sudah_absen'  => $sudahAbsen,
                    'belum_absen'  => $belumAbsen,
                    'total_alpa'   => $alpaPerKelas->sum(),
                ],
                'data' => $sorted,
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
