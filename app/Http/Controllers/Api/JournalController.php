<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Journal;
use App\Models\StudentNote;
use App\Models\Attendance;
use App\Models\Permission;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\InvalAssignment;
use Carbon\Carbon;

class JournalController extends Controller
{
    /**
     * Daftar Kelas Kosong (Inval) yang BELUM diisi Jurnal hari ini
     * GET /api/v1/journal/inval-classes
     */
    public function invalClasses(Request $request)
    {
        try {
            Carbon::setLocale('id'); // Pastikan format nama hari bahasa Indonesia
            $now = Carbon::now();
            $today = $now->toDateString();
            $hariIniStr = strtoupper($now->isoFormat('dddd'));

            // 1. Ambil ID Guru (NIP) yang absen hari ini
            $absentTeacherNips = \App\Models\TeacherAttendance::where('date', $today)
                ->where('status', 'tidak_hadir')
                ->join('users', 'teacher_attendances.user_id', '=', 'users.id')
                ->pluck('users.nip')
                ->toArray();

            // Ambil schedule_id yang sudah dklaim atau ditugaskan hari ini
            $filledInvalIds = Journal::where('is_inval', true)
                ->whereDate('created_at', $today)
                ->pluck('schedule_id')
                ->toArray();

            $claimedInvalIds = InvalAssignment::where('date', $today)
                ->pluck('schedule_id')
                ->toArray();
                
            $excludedIds = array_merge($filledInvalIds, $claimedInvalIds);

            // 2. Ambil Jadwal dari Guru-guru yang absen tersebut pada hari ini
            $invalSchedules = \App\Models\Schedule::with(['timeSlot', 'schoolClass', 'subject', 'teacher'])
                ->where('day_of_week', $hariIniStr)
                ->whereIn('teacher_id', $absentTeacherNips)
                ->get();

            // 3. Ambil time_slot_id dari jadwal Reguler guru yang sedang login (untuk mencegah bentrok/overlap)
            $user = $request->user();
            $myRegularTimeSlots = \App\Models\Schedule::where('teacher_id', $user->nip)
                ->where('day_of_week', $hariIniStr)
                ->pluck('time_slot_id')
                ->toArray();

            // Filter: hapus yang sudah diisi/diklaim ATAU yang BENTROK dengan jadwal Reguler sendiri
            $availableSchedules = $invalSchedules->filter(function ($s) use ($excludedIds, $myRegularTimeSlots) {
                return !in_array($s->id, $excludedIds) && !in_array($s->time_slot_id, $myRegularTimeSlots);
            })->sortBy('time_slot_id')->values();

            $groupedInval = [];
            $currentGroup = null;

            foreach ($availableSchedules as $schedule) {
                if ($currentGroup !== null && 
                    $currentGroup['subject_name'] == ($schedule->subject->name ?? '') && 
                    $currentGroup['className'] == ($schedule->schoolClass->name ?? '') &&
                    $currentGroup['teacher_id'] == $schedule->teacher_id) {
                    
                    // Nyambung -> Update End Time
                    if ($schedule->timeSlot) {
                        $currentGroup['end_time'] = substr($schedule->timeSlot->end_time, 0, 5);
                    }
                    $currentGroup['schedule_ids'][] = $schedule->id;
                } else {
                    if ($currentGroup !== null) {
                        $groupedInval[] = $currentGroup;
                    }
                    
                    $reason = \App\Models\TeacherAttendance::whereHas('teacher', function ($query) use ($schedule) {
                        $query->where('nip', $schedule->teacher_id);
                    })
                        ->where('date', $today)
                        ->value('reason') ?? 'Tanpa Keterangan';

                    $currentGroup = [
                        'id'            => $schedule->id, 
                        'schedule_ids'  => [$schedule->id],
                        'date'          => $today,
                        'teacher_id'    => $schedule->teacher_id,
                        'subject_name'  => $schedule->subject->name ?? 'Unknown Mapel',
                        'className'     => $schedule->schoolClass->name ?? 'Unknown Kelas',
                        'start_time'    => $schedule->timeSlot ? substr($schedule->timeSlot->start_time, 0, 5) : '00:00',
                        'end_time'      => $schedule->timeSlot ? substr($schedule->timeSlot->end_time, 0, 5) : '00:00',
                        'teacherAbsent' => ($schedule->teacher->name ?? 'Unknown Teacher') . ' (' . $reason . ')',
                    ];
                }
            }
            if ($currentGroup !== null) {
                $groupedInval[] = $currentGroup;
            }

            // Convert ke format response Flutter
            $remaining = array_values(array_map(function ($g) {
                $start = Carbon::createFromFormat('H:i', $g['start_time']);
                $end = Carbon::createFromFormat('H:i', $g['end_time']);

                return [
                    'id'            => $g['id'],
                    'schedule_ids'  => $g['schedule_ids'],
                    'date'          => $g['date'],
                    'subject'       => $g['subject_name'],
                    'time'          => $g['start_time'] . ' - ' . $g['end_time'],
                    'duration_minutes' => $start->diffInMinutes($end),
                    'className'     => $g['className'],
                    'teacherAbsent' => $g['teacherAbsent'],
                ];
            }, $groupedInval));

            return response()->json([
                'status' => 'success',
                'data'   => $remaining,
                'total'  => count($remaining),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal mengambil daftar kelas kosong.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Riwayat klaim kelas inval pada hari ini.
     * GET /api/v1/journal/inval-history
     */
    public function invalHistory(Request $request)
    {
        try {
            $today = Carbon::today()->toDateString();

            $assignments = InvalAssignment::query()
                ->with([
                    'schedule.timeSlot',
                    'schedule.subject',
                    'schedule.schoolClass',
                    'replacementTeacher',
                ])
                ->whereDate('date', $today)
                ->latest('created_at')
                ->get()
                ->map(function (InvalAssignment $assignment) {
                    $schedule = $assignment->schedule;

                    return [
                        'id' => $assignment->id,
                        'schedule_id' => $assignment->schedule_id,
                        'subject' => $schedule?->subject?->name ?? '-',
                        'class_name' => $schedule?->schoolClass?->name ?? '-',
                        'time' => $schedule?->timeSlot
                            ? substr($schedule->timeSlot->start_time, 0, 5)
                                . ' - '
                                . substr($schedule->timeSlot->end_time, 0, 5)
                            : '-',
                        'claimed_by_nip' => $assignment->replacement_teacher_id,
                        'claimed_by_name' => $assignment->replacementTeacher?->name ?? '-',
                        'status' => $assignment->status,
                        'claimed_at' => $assignment->created_at?->format('H:i'),
                    ];
                })
                ->values();

            return response()->json([
                'status' => 'success',
                'data' => $assignments,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil riwayat klaim inval.',
            ], 500);
        }
    }

    /**
     * Klaim Kelas Inval (Guru Pengganti)
     * POST /api/v1/journal/inval-claim
     */
    public function claimInvalClass(Request $request)
    {
        $request->validate([
            'schedule_ids'   => 'required_without:schedule_id|array',
            'schedule_ids.*' => 'integer',
            'schedule_id'    => 'required_without:schedule_ids|integer',
        ]);

        try {
            $user = $request->user();
            $today = Carbon::today()->toDateString();

            $scheduleIds = $request->has('schedule_ids') ? $request->schedule_ids : [$request->schedule_id];

            // Cek apakah ada yang sudah diklaim
            $existingClaims = InvalAssignment::whereIn('schedule_id', $scheduleIds)
                ->where('date', $today)
                ->get();

            if ($existingClaims->isNotEmpty()) {
                $alreadyOurs = $existingClaims->where('replacement_teacher_id', $user->nip)->count();
                if ($alreadyOurs === count($scheduleIds)) {
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Anda sudah mengklaim kelas-kelas ini sebelumnya.',
                    ], 200);
                }
                return response()->json([
                    'status' => 'error',
                    'message' => 'Maaf, sebagian atau seluruh kelas ini baru saja diklaim oleh guru lain.',
                ], 400);
            }

            // Insert claim batch (multi-inserasi)
            $inserts = [];
            foreach ($scheduleIds as $id) {
                $inserts[] = [
                    'schedule_id'            => $id,
                    'replacement_teacher_id' => $user->nip,
                    'date'                   => $today,
                    'status'                 => 'claimed',
                    'created_at'             => now(),
                    'updated_at'             => now(),
                ];
            }
            InvalAssignment::insert($inserts);

            $firstSchedule = Schedule::with(['schoolClass', 'subject', 'timeSlot'])
                ->find($scheduleIds[0]);
            app(\App\Services\MobileNotificationService::class)->send(
                $user,
                'inval_claim',
                'Jadwal Inval Berhasil Diambil',
                $firstSchedule
                    ? "Anda mengambil inval {$firstSchedule->subject?->name} di kelas {$firstSchedule->schoolClass?->name}."
                    : 'Kelas inval berhasil masuk ke jadwal mengajar Anda.',
                [
                    'schedule_ids' => $scheduleIds,
                    'class_id' => $firstSchedule?->class_id,
                ],
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Kelas berhasil diklaim dan masuk ke Jadwal Mengajar Anda hari ini.',
            ], 200);

        } catch (\Illuminate\Database\QueryException $e) {
            // Tangkap error jika unique constraint dilanggar di DB secara concurrent
            $errorCode = $e->errorInfo[1] ?? 0;
            if ($errorCode == 1062 || $errorCode == 19) { // Duplicate entry MySQL/SQLite
                return response()->json([
                    'status' => 'error',
                    'message' => 'Maaf, balapan klaim terdeteksi. Kelas ini sudah diamankan guru lain.',
                ], 400);
            }
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan sistem database.',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal melakukan klaim kelas.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function students($scheduleId)
    {
        try {
            // Coba ambil dari Schedule biasa (Bila ID ditemukan, sertakan relasi kelas dan mapel)
            $schedule = \App\Models\Schedule::with(['schoolClass', 'subject'])->find($scheduleId);
            
            if ($schedule) {
                $classId = $schedule->class_id;
                $className = $schedule->schoolClass->name ?? 'Kelas Tidak Ditemukan';
                $subjectName = $schedule->subject->name ?? 'Mata Pelajaran';
                
                $students = Student::where('class_id', $classId)->get();
                $scheduleData = [
                    'id'             => $schedule->id,
                    'kelas'          => $className,
                    'mata_pelajaran' => $subjectName,
                    'is_inval_mock'  => false,
                ];
            } else {
                // FALLBACK KHUSUS INVAL (Toleransi ID Dummy dari Flutter)
                // Di Flutter, id 101 = 7A, id 102 = 8C  
                $className = ($scheduleId == 102) ? '8C' : '7A';
                
                // Cari ID aslinya jika ada
                $schoolClass = \App\Models\SchoolClass::where('name', $className)->first();
                $students = $schoolClass ? Student::where('class_id', $schoolClass->id)->get() : collect([]);
                
                $scheduleData = [
                    'id'             => (int) $scheduleId,
                    'kelas'          => $className,
                    'mata_pelajaran' => 'Inval',
                    'is_inval_mock'  => true,
                ];
            }

            // ── SMART PRE-FILL: Sinkronisasi status hadir & izin wali kelas ──
            $today = Carbon::today()->toDateString();

            // 1. Ambil daftar NISN siswa yang sudah scan gerbang masuk hari ini
            $scannedNisns = Attendance::whereDate('created_at', $today)
                ->whereIn('keterangan', ['Masuk', 'Terlambat'])
                ->pluck('nisn_student')
                ->toArray();

            // 2. Ambil seluruh cuti izin (Permission) yang sah pada hari ini berdasarkan model
            $activePermissions = \App\Models\Permission::activeOnDate($today)->get()->keyBy('student_id');

            // Tambahkan field status_awal, is_locked, & keterangan_izin ke setiap siswa
            $studentsWithStatus = $students->map(function ($student) use ($scannedNisns, $activePermissions) {
                $statusAwal = 'none';
                $isLocked   = false;
                $keterangan = null;
                
                // Prioritas 1: Izin/Sakit dari Wali Kelas (Single Source of Truth mutlak)
                if ($activePermissions->has($student->id)) {
                    $permission = $activePermissions->get($student->id);
                    $statusAwal = $permission->type === 'sakit' ? 'KBM_Sakit' : 'KBM_Izin';
                    $isLocked   = true;
                    $keterangan = $permission->keterangan;
                }
                // Prioritas 2: Tap Scan QR Gerbang
                elseif (in_array($student->nisn, $scannedNisns)) {
                    $statusAwal = 'KBM_Hadir';
                }

                return [
                    'id'                 => $student->id,
                    'name'               => $student->name,
                    'nisn'               => $student->nisn,
                    'nis'                => $student->nis,
                    'class_id'           => $student->class_id,
                    'status_awal'        => $statusAwal,
                    'is_locked'          => $isLocked,
                    'keterangan_izin'    => $keterangan,
                    'sudah_scan_gerbang' => in_array($student->nisn, $scannedNisns),
                ];
            });

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'schedule' => $scheduleData,
                    'students' => $studentsWithStatus,
                    'total_sudah_scan' => count($scannedNisns),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal mengambil data siswa kelas.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Menyimpan data Jurnal Kelas & Rekap Presensi KBM
     * POST /api/v1/journal/store
     */
    public function store(Request $request)
    {
        $request->validate([
            'schedule_id'      => 'required|integer',
            'materi'           => 'required|string',
            'kebersihan_kelas' => 'nullable|string',
            'koordinat'        => 'nullable|string',
            'is_inval'         => 'required|boolean',
            'attendances'      => 'required|array',
            'attendances.*.student_id' => 'required|integer',
            'attendances.*.status'     => 'required|string', // e.g. 'KBM_Hadir', 'KBM_Sakit', dsb.
            'attendances.*.notes'      => 'nullable|array', // Catatan sikap (Array of strings)
            'attachment' => [
                'nullable',
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,webp,pdf,doc,docx,ppt,pptx,xls,xlsx,csv,zip,rar',
            ],
        ]);

        if ($request->hasFile('attachment')) {
            $attachment = $request->file('attachment');
            $imageExtensions = ['jpg', 'jpeg', 'png', 'webp'];

            if (
                in_array(strtolower($attachment->getClientOriginalExtension()), $imageExtensions, true)
                && $attachment->getSize() > 1024 * 1024
            ) {
                return response()->json([
                    'message' => 'Ukuran lampiran gambar maksimal 1 MB.',
                    'errors' => [
                        'attachment' => ['Ukuran lampiran gambar maksimal 1 MB.'],
                    ],
                ], 422);
            }
        }

        $attachmentPath = null;

        try {
            DB::beginTransaction();

            $user = $request->user();
            $todayString = Carbon::today()->toDateString();

            $teacherAttendance = \App\Models\TeacherAttendance::where('user_id', $user->id)
                ->whereDate('date', $todayString)
                ->first();

            if (! $teacherAttendance) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Silakan melakukan check-in presensi guru sebelum mengisi jurnal harian.',
                ], 403);
            }

            if ($teacherAttendance->status !== 'hadir') {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Jurnal tidak dapat diisi karena Anda tercatat tidak hadir hari ini.',
                ], 403);
            }

            // GUARD: Cegah duplikasi jurnal (submit ganda / double-tap)
            $duplicate = Journal::where('schedule_id', $request->schedule_id)
                ->whereDate('created_at', $todayString)
                ->exists();

            if ($duplicate) {
                DB::rollBack();
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Jurnal untuk kelas ini sudah pernah diisi hari ini.',
                ], 409);
            }

            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')
                    ->store('journal-attachments', 'public');
            }

            // 1. Simpan tabel journals Utama
            $journal = Journal::create([
                'schedule_id' => $request->schedule_id,
                'user_id'     => $user->nip,
                'is_inval'    => $request->is_inval,
                'material'    => $request->materi,
                'cleanliness' => $request->kebersihan_kelas,
                'attachment_path' => $attachmentPath,
            ]);

            // 2. Loop per Siswa untuk menyimpan Catatan Sikap (StudentNote) & Rekam Jejak KBM
            $today = Carbon::today()->toDateString();
            
            // Tarik ulang daftar izin wali kelas aktif HARI INI sebagai perisai Backend
            $activePermissions = \App\Models\Permission::activeOnDate($today)->get()->keyBy('student_id');

            foreach ($request->attendances as $att) {
                $studentId = $att['student_id'];
                $statusKBM = $att['status'];

                // DOMINASI PENIMPAAN: Jika anak digembok Wali Kelas, tolak manipulasi KBM dari frontend.
                if ($activePermissions->has($studentId)) {
                    $perm = $activePermissions->get($studentId);
                    $statusKBM = $perm->type === 'sakit' ? 'KBM_Sakit' : 'KBM_Izin';
                }

                $notesArr = $att['notes'] ?? [];
                // Gabungkan catatan array menjadi string dipisahkan koma
                $notesStr = empty($notesArr) ? null : implode(', ', $notesArr);

                StudentNote::create([
                    'journal_id' => $journal->id,
                    'student_id' => $att['student_id'],
                    'note_type'  => $statusKBM,
                    'notes'      => $notesStr,
                ]);

                // Status sakit/izin dari jurnal otomatis masuk ke daftar
                // perizinan wali kelas dan menunggu verifikasi BK.
                if (in_array($statusKBM, ['KBM_Sakit', 'KBM_Izin', 'KBM_Sakit_atau_Izin'], true)) {
                    $student = Student::with('schoolClass')->find($studentId);

                    if ($student) {
                        $permissionType = match ($statusKBM) {
                            'KBM_Sakit' => 'sakit',
                            'KBM_Izin' => 'izin',
                            default => str_contains(strtolower((string) $notesStr), 'sakit')
                                ? 'sakit'
                                : 'izin',
                        };

                        $existingPermission = Permission::query()
                            ->where('student_id', $studentId)
                            ->whereDate('start_date', '<=', $today)
                            ->whereDate('end_date', '>=', $today)
                            ->first();

                        $permissionData = [
                            'nip_guru' => $student->schoolClass?->homeroom_teacher_id ?: $user->nip,
                            'type' => $permissionType,
                            'keterangan' => $notesStr ?: 'Dicatat dari jurnal mengajar oleh '.$user->name.'.',
                        ];

                        if ($existingPermission) {
                            $existingPermission->update($permissionData);
                        } else {
                            Permission::create($permissionData + [
                                'student_id' => $studentId,
                                'start_date' => $today,
                                'end_date' => $today,
                                'status' => 'pending',
                            ]);
                        }
                    }
                }

                // Opsional: Rekam di tabel attendances global (untuk monitoring BK)
                // Hanya untuk siswa yang Alpa/Sakit/Izin (bukan Hadir)
                if (in_array($statusKBM, ['KBM_Sakit_atau_Izin', 'KBM_Alpa', 'KBM_Sakit', 'KBM_Izin'])) {
                    $student = Student::find($att['student_id']);
                    // Guard: pastikan siswa ditemukan dan punya nisn valid
                    if ($student && !empty($student->nisn)) {
                        try {
                            Attendance::create([
                                'nip_guru'     => $user->nip,
                                'nisn_student' => $student->nisn,
                                'kelas'        => $student->class_id ?? 'N/A',
                                'presensi'     => match ($statusKBM) {
                                    'KBM_Sakit' => 'Sakit',
                                    'KBM_Izin', 'KBM_Sakit_atau_Izin' => 'Izin',
                                    default => 'Alpa',
                                },
                                'kegiatan'     => 'KBM',
                                'keterangan'   => $notesStr,
                            ]);
                        } catch (\Exception $attErr) {
                            // Log saja, jangan batalkan seluruh transaksi
                            \Log::warning('Gagal insert attendance KBM: ' . $attErr->getMessage());
                        }
                    }
                }
            }

            DB::commit();

            app(\App\Services\MobileNotificationService::class)->send(
                $user,
                'journal_submitted',
                'Jurnal Berhasil Disimpan',
                "Jurnal mengajar untuk kelas {$journal->schedule?->schoolClass?->name} telah selesai dikirim.",
                [
                    'journal_id' => $journal->id,
                    'schedule_id' => $request->schedule_id,
                ],
            );

            // 3. TODO: Broadcast Event WebSockets di sini nantinya (Fase 9)
            // event(new ClassStartedEvent($journal));

            try {
                $scheduleModel = \App\Models\Schedule::find($request->schedule_id);
                if ($scheduleModel) {
                    event(new \App\Events\JournalSubmitted($scheduleModel->id, $user->name, $scheduleModel->class_id));

                    foreach ($request->attendances as $att) {
                        $stKBM = $att['status'];
                        if (in_array($stKBM, ['KBM_Sakit', 'KBM_Izin', 'KBM_Alpa', 'KBM_Sakit_atau_Izin'])) {
                            event(new \App\Events\StudentStatusUpdated(
                                $att['student_id'],
                                $scheduleModel->class_id,
                                $stKBM,
                                'Guru'
                            ));
                        }
                    }
                }
            } catch (\Exception $bcErr) {
                // Jangan batalkan save jika broadcast gagal
                \Log::error('Broadcast error JournalController: ' . $bcErr->getMessage());
            }


            return response()->json([
                'status'  => 'success',
                'message' => 'Jurnal kelas berhasil disimpan',
                'data'    => ['journal_id' => $journal->id]
            ], 201);

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            if ($attachmentPath) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($attachmentPath);
            }

            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal menyimpan Jurnal. Terdapat kesalahan sistem.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mengambil Detail History Jurnal yang sudah Disubmit.
     * GET /api/v1/journal/history/{journal_id}
     */
    public function history($journal_id)
    {
        try {
            $journal = Journal::with([
                'schedule.subject', 
                'schedule.schoolClass', 
                'schedule.timeSlot',
                'teacher', 
                'studentNotes.student'
            ])->find($journal_id);

            if (!$journal) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data jurnal tidak ditemukan'
                ], 404);
            }

            // Hitung rekap absensi otomatis
            $absenKosong = $journal->studentNotes->count();

            $journalDate = $journal->created_at->toDateString();
            $approvedPermissions = Permission::query()
                ->whereIn('student_id', $journal->studentNotes->pluck('student_id'))
                ->activeOnDate($journalDate)
                ->latest('id')
                ->get()
                ->keyBy('student_id');

            // Siapkan Map data absensi
            $rekapSiswa = $journal->studentNotes->map(function ($note) use ($approvedPermissions) {
                $permission = $approvedPermissions->get($note->student_id);
                $status = $permission
                    ? ($permission->type === 'sakit' ? 'KBM_Sakit' : 'KBM_Izin')
                    : $note->note_type;

                return [
                    'student_name' => $note->student->name ?? 'Siswa',
                    'nis'          => $note->student->nis,
                    'status'       => $status,
                    'notes'        => $note->notes,
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'journal_id' => $journal->id,
                    'created_at' => $journal->created_at->format('Y-m-d H:i:s'),
                    'subject'    => $journal->schedule->subject->name ?? 'N/A',
                    'class_name' => $journal->schedule->schoolClass->name ?? 'N/A',
                    'time_slot'  => [
                        'start_time' => $journal->schedule->timeSlot ? substr($journal->schedule->timeSlot->start_time, 0, 5) : '00:00',
                        'end_time'   => $journal->schedule->timeSlot ? substr($journal->schedule->timeSlot->end_time, 0, 5) : '00:00',
                    ],
                    'material'    => $journal->material,
                    'cleanliness' => $journal->cleanliness,
                    'is_inval'    => $journal->is_inval,
                    'attachment_url' => $journal->attachment_path
                        ? url(\Illuminate\Support\Facades\Storage::disk('public')->url($journal->attachment_path))
                        : null,
                    'total_absen' => $absenKosong,
                    'absensi'     => $rekapSiswa
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal memuat history jurnal.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
