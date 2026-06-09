<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Permission;
use App\Models\Student;
use App\Models\User;
use App\Services\MobileNotificationService;
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
        try {
            $user = $request->user();

            if ($user->role !== 'Guru BK') {
                if ($err = $this->assertWaliKelas($request)) return $err;
            }

            $query = Permission::with('student')->orderByDesc('created_at');

            if ($user->role !== 'Guru BK') {
                $query->where('nip_guru', $user->nip);
            }

            $permissions = $query->get()->map(fn ($perm) => $this->formatPermission($perm));

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

            $notificationService = app(MobileNotificationService::class);
            $notificationService->send(
                $user,
                'permission_submitted',
                'Izin Berhasil Diajukan',
                "Pengajuan izin untuk {$student->name} berhasil dikirim ke Guru BK.",
                ['permission_id' => $permission->id],
            );
            $notificationService->sendToUsers(
                User::query()->where('role', User::ROLE_GURU_BK)->get(),
                'permission_submitted',
                'Pengajuan Izin Baru',
                "{$student->name} dari kelas {$student->class_id} mengajukan izin.",
                ['permission_id' => $permission->id],
            );

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

    /**
     * Approve pengajuan izin oleh Guru BK.
     * POST /api/v1/bk/permissions/{permission}/approve
     */
    public function approveByBk(Request $request, int $permission)
    {
        if ($request->user()->role !== 'Guru BK') {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        $permissionModel = Permission::findOrFail($permission);
        $permissionModel->update(['status' => 'approved']);
        $permissionModel->load(['guru', 'student']);

        if ($permissionModel->guru) {
            app(MobileNotificationService::class)->send(
                $permissionModel->guru,
                'permission_approved',
                'Pengajuan Izin Disetujui',
                "Pengajuan izin {$permissionModel->student?->name} telah disetujui Guru BK.",
                ['permission_id' => $permissionModel->id],
            );
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Pengajuan berhasil disetujui BK.',
            'data'    => $this->formatPermission($permissionModel->fresh('student')),
        ]);
    }

    /**
     * Reject pengajuan izin oleh Guru BK.
     * POST /api/v1/bk/permissions/{permission}/reject
     */
    public function rejectByBk(Request $request, int $permission)
    {
        if ($request->user()->role !== 'Guru BK') {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        $permissionModel = Permission::findOrFail($permission);
        $permissionModel->update(['status' => 'rejected']);
        $permissionModel->load(['guru', 'student']);

        if ($permissionModel->guru) {
            app(MobileNotificationService::class)->send(
                $permissionModel->guru,
                'permission_rejected',
                'Pengajuan Izin Ditolak',
                "Pengajuan izin {$permissionModel->student?->name} ditolak oleh Guru BK.",
                ['permission_id' => $permissionModel->id],
            );
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Pengajuan berhasil ditolak BK.',
            'data'    => $this->formatPermission($permissionModel->fresh('student')),
        ]);
    }

    private function formatPermission(Permission $perm): array
    {
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
    }
}
