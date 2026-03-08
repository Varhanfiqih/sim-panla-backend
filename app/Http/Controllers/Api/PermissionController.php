<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Permission;
use App\Models\Student;
use Carbon\Carbon;

class PermissionController extends Controller
{
    /**
     * Cek akses: hanya Wali Kelas yang boleh kelola perizinan (sesuai PRD Bab 2)
     * Ditandai dengan kolom `wali_kelas` yang berisi kode kelas (mis: '7F'), bukan null.
     * Di masa depan, Web Admin akan punya UI untuk assign/rolling Wali Kelas ini.
     */
    private function assertWaliKelas(Request $request): ?\Illuminate\Http\JsonResponse
    {
        // Validasi Relasional: Apakah Guru mempunyai entitas Kelas yang diampuh?
        $homeroomClass = $request->user()->homeroomClass;
        $isValidKelas = !empty($homeroomClass);

        if (!$isValidKelas) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Akses ditolak. Fitur Perizinan hanya untuk Wali Kelas.',
            ], 403);
        }
        return null;
    }

    /**
     * Daftar izin siswa milik wali kelas yang login
     * GET /api/v1/permissions
     */
    public function index(Request $request)
    {
        if ($err = $this->assertWaliKelas($request)) return $err;

        try {
            $user = $request->user();

            $permissions = Permission::with('student')
                ->where('nip_guru', $user->nip)
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($perm) {
                    return [
                        'id'         => $perm->id,
                        'student'    => [
                            'id'       => $perm->student?->id,
                            'name'     => $perm->student?->name,
                            'nis'      => $perm->student?->nis,
                            'class_id' => $perm->student?->class_id,
                        ],
                        'type'       => $perm->type,
                        'start_date' => $perm->start_date?->format('Y-m-d'),
                        'end_date'   => $perm->end_date?->format('Y-m-d'),
                        'total_hari' => $perm->total_hari,
                        'keterangan' => $perm->keterangan,
                        'foto_url'   => $perm->foto_path ? url('storage/' . $perm->foto_path) : null,
                        'status'     => $perm->status,
                        'created_at' => $perm->created_at?->format('d M Y'),
                    ];
                });

            return response()->json(['status' => 'success', 'data' => $permissions]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Gagal mengambil data izin.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Simpan pengajuan izin (hanya Wali Kelas, hanya untuk siswa di kelasnya)
     * POST /api/v1/permissions/store
     */
    public function store(Request $request)
    {
        if ($err = $this->assertWaliKelas($request)) return $err;

        $request->validate([
            'student_id' => 'required|integer|exists:students,id',
            'type'       => 'required|in:sakit,izin,keluarga',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'keterangan' => 'nullable|string|max:500',
            'foto'       => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        try {
            $user = $request->user();

            // Pastikan siswa memang di kelas yang dipegang wali kelas ini
            $student = Student::find($request->student_id);
            $myClassId = $user->homeroomClass ? $user->homeroomClass->id : null;

            if ($student && $myClassId && $student->class_id !== $myClassId) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Siswa bukan bagian dari kelas wali Anda (' . $myClassId . ').',
                ], 403);
            }

            $fotoPath = null;
            if ($request->hasFile('foto')) {
                $file = $request->file('foto');
                $filename = 'izin_' . $user->nip . '_' . time() . '.' . $file->getClientOriginalExtension();
                $fotoPath = $file->storeAs('permissions', $filename, 'public');
            }

            $permission = Permission::create([
                'nip_guru'   => $user->nip,
                'student_id' => $request->student_id,
                'type'       => $request->type,
                'start_date' => Carbon::parse($request->start_date),
                'end_date'   => Carbon::parse($request->end_date),
                'keterangan' => $request->keterangan,
                'foto_path'  => $fotoPath,
                'status'     => 'pending',
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Izin berhasil diajukan.',
                'data'    => [
                    'id'         => $permission->id,
                    'total_hari' => $permission->total_hari,
                    'status'     => $permission->status,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Gagal menyimpan data izin.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Daftar siswa yang menjadi tanggung jawab wali kelas yang login
     * GET /api/v1/permissions/students
     */
    public function studentsByClass(Request $request)
    {
        if ($err = $this->assertWaliKelas($request)) return $err;

        $user = $request->user();

        // Filter otomatis berdasarkan relasi kelas dari `homeroom_teacher_id`
        $classId = $user->homeroomClass ? $user->homeroomClass->id : $request->query('class_id');

        $students = Student::when($classId, fn($q) => $q->where('class_id', $classId))
            ->select('id', 'name', 'nis', 'class_id')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $students,
            'kelas'  => $classId,
        ]);
    }
}
