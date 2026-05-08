Paket CSV ini sudah dinormalisasi supaya AI/agent gampang memahami relasi:
- teacher.csv: daftar guru (teacher_id)
- student.csv: daftar murid (student_id)
- class.csv: daftar kelas (class_id)
- subject.csv: daftar mapel (subject_id)
- schedule.csv: sumber kebenaran penugasan mengajar (guru-mapel-kelas + hari/jam)
- teaching_assignment_from_schedule.csv: versi ringkas (distinct)
- homeroom_assignment.csv: guru yang jadi wali kelas
- journal*.csv: aktivitas KBM + detail absensi/catatan dari JSON
- attendance.csv: presensi event murid
- absence_confirmation.csv: konfirmasi alpa (journal_id = ID Jurnal - 1)

Catatan:
- NIP/NISN dipertahankan sebagai string.
- teacher_auth_raw.csv berisi password mentah dari Excel; JANGAN dibagikan ke pihak lain.
