<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Schedule;
use App\Models\Journal;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Terima parameter date dari query, default hari ini
        $dateParam = $request->query('date');
        if ($dateParam) {
            $currentDate = Carbon::parse($dateParam);
        } else {
            $currentDate = Carbon::now();
        }

        $hariIniStr = $this->indonesianDayName($currentDate);
        $sekarang = Carbon::now(); // Waktu aktual jam
        $todayString = $currentDate->toDateString(); // Tanggal kalender dicari

        $regularSchedules = Schedule::with(['timeSlot', 'subject', 'schoolClass'])
            ->where('teacher_id', $user->nip)
            ->where('day_of_week', $hariIniStr)
            ->get();

        $invalAssignments = \App\Models\InvalAssignment::with(['schedule.timeSlot', 'schedule.subject', 'schedule.schoolClass'])
            ->where('replacement_teacher_id', $user->nip)
            ->where('date', $todayString)
            ->get();

        $invalSchedules = $invalAssignments->map(function ($assignment) {
            $schedule = $assignment->schedule;
            if ($schedule) {
                // Injeksi flag metadata Inval ke dalam objek Schedule agar UI Flutter bisa mengenalinya
                $schedule->keterangan = 'INVAL'; 
            }
            return $schedule;
        })->filter();

        // Merge jadwal reguler dan inval, lalu urutkan kembali berdasarkan time_slot_id
        $schedules = $regularSchedules->concat($invalSchedules)->sortBy('time_slot_id')->values();

        // Ambil journal hari yg dicari supaya bisa membedakan status 'DONE' atau 'OPEN'
        $journalsToday = Journal::where('user_id', $user->nip)
            ->whereDate('created_at', $todayString)
            ->get();

        // VALIDASI: jurnal hari ini hanya terbuka setelah guru check-in hadir.
        $teacherAttendance = \App\Models\TeacherAttendance::where('user_id', $user->id)
            ->where('date', $todayString)
            ->first();
        $hasCheckedInToday = $teacherAttendance !== null;
        $isAbsentToday = $teacherAttendance?->status === 'tidak_hadir';

        // GROUPING LOGIC
        $groupedSchedules = [];
        $currentGroup = null;

        foreach ($schedules as $schedule) {
            // Cek apakah nyambung dengan grup sebelumnya
            if ($currentGroup !== null && 
                $currentGroup['subject_id'] == $schedule->subject_id && 
                $currentGroup['class_id'] == $schedule->class_id) {
                
                // Nyambung -> Update End Time dan kumpulkan ID
                if ($schedule->timeSlot) {
                    $currentGroup['end_time'] = substr($schedule->timeSlot->end_time, 0, 5);
                }
                $currentGroup['schedule_ids'][] = $schedule->id;
            } else {
                // Berbeda -> Simpan grup lama jika ada, lalu buat grup baru
                if ($currentGroup !== null) {
                    $groupedSchedules[] = $currentGroup;
                }
                
                $currentGroup = [
                    'start_time' => $schedule->timeSlot ? substr($schedule->timeSlot->start_time, 0, 5) : '00:00',
                    'end_time' => $schedule->timeSlot ? substr($schedule->timeSlot->end_time, 0, 5) : '00:00',
                    'subject_id' => $schedule->subject_id,
                    'class_id' => $schedule->class_id,
                    'subject_name' => $schedule->subject->name ?? 'Mata Pelajaran',
                    'class_name' => $schedule->schoolClass->name ?? '',
                    'keterangan' => $schedule->keterangan,
                    // Primary reference schedule_id untuk buka form jurnal
                    'primary_schedule_id' => $schedule->id, 
                    'schedule_ids' => [$schedule->id]
                ];
            }
        }
        // Masukkan grup terakhir
        if ($currentGroup !== null) {
            $groupedSchedules[] = $currentGroup;
        }


        // VALIDASI STATUS JURNAL
        $data = array_values(array_map(function ($group) use ($sekarang, $journalsToday, $currentDate, $hasCheckedInToday, $isAbsentToday) {
            $statusJurnal = 'LOCKED';
            $journalId = null;

            // Apakah di kalender cari ada jurnal yg cocok dgn primary_schedule_id ?
            $existingJournal = $journalsToday->firstWhere('schedule_id', $group['primary_schedule_id']);

            if ($existingJournal) {
                $statusJurnal = 'DONE';
                $journalId = $existingJournal->id;
            } else {
                // Untuk menentukan OPEN/LOCKED, patokan pake $sekarang (waktu riil) dibanding startDate (kalender)
                if ($currentDate->isToday() && ! $hasCheckedInToday) {
                    $statusJurnal = 'LOCKED';
                    $group['keterangan'] = 'Belum Check-In';
                } elseif ($isAbsentToday) {
                    // Blokir tombol jurnal jika hari ini sang guru sudah terdata sakit/izin
                    $statusJurnal = 'LOCKED';
                    $group['keterangan'] = 'Guru Berhalangan Hadir';
                } elseif ($currentDate->isToday()) {
                    $start = Carbon::createFromFormat('H:i', $group['start_time']);
                    if ($sekarang->greaterThanOrEqualTo($start)) {
                        $statusJurnal = 'OPEN';
                    }
                } elseif ($currentDate->isPast() && !$currentDate->isToday()) {
                     // Hari kemaren belum ngisi jurnal -> tetap OPEN 
                    $statusJurnal = 'OPEN';
                }
            }

            return [
                'id' => $group['primary_schedule_id'],
                'status_jurnal' => $statusJurnal, // LOCKED, OPEN, DONE
                'subject' => $group['subject_name'],
                'className' => $group['class_name'],
                'time_slot' => [
                    'start_time' => $group['start_time'],
                    'end_time'   => $group['end_time'],
                ],
                'keterangan' => $group['keterangan'],
                'journal_id' => $journalId,
            ];
        }, $groupedSchedules));

        return response()->json([
            'status'   => 'success',
            'hari_ini' => $hariIniStr,
            'tanggal'  => $todayString,
            'data'     => $data
        ]);
    }

    private function indonesianDayName(Carbon $date): string
    {
        return [
            1 => 'SENIN',
            2 => 'SELASA',
            3 => 'RABU',
            4 => 'KAMIS',
            5 => 'JUMAT',
            6 => 'SABTU',
            7 => 'MINGGU',
        ][$date->dayOfWeekIso];
    }
}
