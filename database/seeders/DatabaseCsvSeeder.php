<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Student;
use App\Models\TimeSlot;
use App\Models\Schedule;

class DatabaseCsvSeeder extends Seeder
{
    public function run()
    {
        $path = storage_path('app/csv/');
        
        // 1. Time Slots (Dummy for 1 - 10 hour periods)
        for ($i=1; $i<=10; $i++) {
            TimeSlot::updateOrCreate(
                ['id' => $i],
                [
                    'start_time' => sprintf('%02d:00:00', 6+$i), // e.g. 07:00
                    'end_time' => sprintf('%02d:45:00', 6+$i),   // e.g. 07:45
                    'type' => 'KBM'
                ]
            );
        }
        $this->command->info('TimeSlot generated.');

        // 2. Classes
        $classes = $this->readCSV($path . 'class.csv');
        foreach($classes as $c) {
            SchoolClass::updateOrCreate(['id' => $c['class_id']], ['name' => $c['class_name']]);
        }
        $this->command->info('Classes imported.');

        // 3. Subjects
        $subjects = $this->readCSV($path . 'subject.csv');
        foreach($subjects as $s) {
            if (!empty($s['subject_id'])) {
                Subject::updateOrCreate(['id' => $s['subject_id']], ['name' => $s['subject_name']]);
            }
        }
        $this->command->info('Subjects imported.');

        // 4. Admin Account (Static)
        User::updateOrCreate(
            ['nip' => 'admin'],
            [
                'name' => 'Administrator Utama',
                'password' => Hash::make('123456'), // Default password Admin
                'role' => 'Admin'
            ]
        );

        // 5. Teachers & Credentials
        $teachers = $this->readCSV($path . 'teacher.csv');
        $passwords = [];
        foreach($this->readCSV($path . 'teacher_auth_raw.csv') as $p) {
            $passwords[$p['teacher_id']] = $p['password_raw'];
        }
        
        foreach($teachers as $t) {
            $pwd = $passwords[$t['teacher_id']] ?? '123456'; // Default if null
            if (!empty($t['teacher_id'])) {
                User::updateOrCreate(
                    ['nip' => $t['teacher_id']],
                    [
                        'name' => $t['teacher_name'],
                        'password' => Hash::make($pwd),
                        'role' => ($t['role'] === 'Bimbingan Konseling' || $t['role'] === 'Guru BK') ? 'Guru BK' : 'Guru'
                    ]
                );
            }
        }
        $this->command->info('Teachers imported.');

        // 6. Homeroom Assignments
        foreach($this->readCSV($path . 'homeroom_assignment.csv') as $h) {
            $cls = SchoolClass::find($h['class_id']);
            if ($cls && !empty($h['teacher_id'])) {
                $cls->update(['homeroom_teacher_id' => $h['teacher_id']]);
            }
        }
        $this->command->info('Homerooms imported.');

        // 7. Teacher Subject Scope (Pivot)
        foreach($this->readCSV($path . 'teacher_subject_scope.csv') as $ts) {
            if (!empty($ts['teacher_id']) && !empty($ts['subject_id'])) {
                DB::table('subject_user')->updateOrInsert([
                    'user_nip' => $ts['teacher_id'],
                    'subject_id' => $ts['subject_id'],
                ]);
            }
        }

        // 8. Teacher Class Scope (Pivot)
        foreach($this->readCSV($path . 'teacher_class_scope.csv') as $tc) {
            if (!empty($tc['teacher_id']) && !empty($tc['class_id'])) {
                DB::table('class_user')->updateOrInsert([
                    'user_nip' => $tc['teacher_id'],
                    'class_id' => $tc['class_id'],
                ]);
            }
        }
        $this->command->info('Teacher Scopes Pivot imported.');

        // 9. Students
        $students = $this->readCSV($path . 'student.csv');
        foreach($students as $s) {
            if(!empty($s['student_id'])) { // Assuming student_id is NISN
                Student::updateOrCreate(
                    ['nisn' => $s['student_id']],
                    [
                        'nis' => $s['nis'],
                        'name' => $s['student_name'],
                        'class_id' => $s['class_id'],
                        'gender' => $s['gender'],
                        'qr_code' => $s['qrcode'] ?: null,
                    ]
                );
            }
        }
        $this->command->info('Students imported.');

        // 10. Schedules Extract Time Ranges
        $schedules = $this->readCSV($path . 'schedule.csv');
        foreach($schedules as $sch) {
            $start = (int) $sch['period_start'];
            $end = (int) $sch['period_end'];
            for($i = $start; $i <= $end; $i++) {
                // Pastikan tidak kosong dan Valid Record (ID > 0)
                if($i > 0 && !empty($sch['subject_id']) && !empty($sch['teacher_id']) && !empty($sch['class_id'])) {
                    Schedule::updateOrCreate(
                        [
                            'day_of_week' => strtoupper($sch['day']),
                            'class_id' => $sch['class_id'],
                            'time_slot_id' => $i,
                            'teacher_id' => $sch['teacher_id'],
                        ],
                        [
                            'subject_id' => (int) $sch['subject_id'],
                            'keterangan' => $sch['note'] !== '-' ? $sch['note'] : null,
                        ]
                    );
                }
            }
        }
        $this->command->info('Schedules imported.');
    }

    private function readCSV($filename) {
        if (!file_exists($filename)) return [];
        $data = [];
        if (($handle = fopen($filename, "r")) !== FALSE) {
            $header = fgetcsv($handle, 1000, ",");
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if(count($header) == count($row)) {
                    $data[] = array_combine($header, $row);
                }
            }
            fclose($handle);
        }
        return $data;
    }
}
