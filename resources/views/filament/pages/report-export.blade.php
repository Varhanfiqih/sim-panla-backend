<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-2">

        {{-- Widget Export Presensi Harian --}}
        <x-filament::section icon="heroicon-o-qr-code" heading="Laporan Kehadiran (Gerbang)">
            <form method="GET" action="{{ route('admin.reports.attendance', [], false) }}" target="_blank" class="space-y-4">
                
                <div class="space-y-2">
                    <label for="attendance_date" class="text-sm font-medium dark:text-gray-300">Pilih Tanggal</label>
                    <input type="date" id="attendance_date" name="date" value="{{ $attendance_date }}" class="block w-full border-gray-300 bg-white text-gray-900 rounded-lg shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white" required>
                </div>

                <div class="space-y-2">
                    <label for="attendance_class" class="text-sm font-medium dark:text-gray-300">Filter Kelas (Opsional)</label>
                    <select id="attendance_class" name="class_id" class="block w-full border-gray-300 bg-white text-gray-900 rounded-lg shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white">
                        <option value="">-- Semua Kelas --</option>
                        @foreach ($classes as $kls)
                            <option value="{{ $kls->id }}">{{ $kls->id }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 gap-2 pt-2 sm:flex sm:justify-end">
                    <x-filament::button type="submit" color="gray" icon="heroicon-m-document-arrow-down" formaction="{{ url('/admin/reports/attendance.pdf') }}" class="w-full sm:w-auto">
                        Unduh PDF
                    </x-filament::button>
                    <x-filament::button type="submit" color="success" icon="heroicon-m-arrow-down-tray" class="w-full sm:w-auto">
                        Unduh Excel
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        {{-- Widget Export Jurnal --}}
        <x-filament::section icon="heroicon-o-book-open" heading="Laporan Jurnal Mengajar & Absen">
            <form method="GET" action="{{ route('admin.reports.journal', [], false) }}" target="_blank" class="space-y-4">
                
                <div class="space-y-2">
                    <label for="journal_date" class="text-sm font-medium dark:text-gray-300">Pilih Tanggal Jurnal</label>
                    <input type="date" id="journal_date" name="date" value="{{ $journal_date }}" class="block w-full border-gray-300 bg-white text-gray-900 rounded-lg shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white" required>
                </div>

                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Rekapan otomatis mencakup: Mata Pelajaran, Guru Pengajar, Kelas, Jam Ke, Materi yang diisi serta <strong>Daftar Siswa yang Absen (Sakit/Izin/Alpa)</strong> di jam tersebut.
                </p>

                <div class="grid grid-cols-1 gap-2 pt-2 sm:flex sm:justify-end">
                    <x-filament::button type="submit" color="gray" icon="heroicon-m-document-arrow-down" formaction="{{ url('/admin/reports/journal.pdf') }}" class="w-full sm:w-auto">
                        Unduh PDF
                    </x-filament::button>
                    <x-filament::button type="submit" color="success" icon="heroicon-m-arrow-down-tray" class="w-full sm:w-auto">
                        Unduh Excel
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        {{-- Widget Export Nilai Siswa --}}
        <x-filament::section icon="heroicon-o-academic-cap" heading="Laporan Nilai Siswa">
            <form method="GET" action="{{ route('admin.reports.grades', [], false) }}" target="_blank" class="space-y-4">
                <div class="space-y-2">
                    <label for="grade_period" class="text-sm font-medium dark:text-gray-300">Periode Nilai</label>
                    <select id="grade_period" name="period_id" class="block w-full rounded-lg border-gray-300 bg-white text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white" required>
                        <option value="">-- Pilih Periode --</option>
                        @foreach ($gradePeriods as $period)
                            <option value="{{ $period->id }}" @selected((string) $grade_period === (string) $period->id)>{{ $period->name }}{{ $period->academic_year ? ' - '.$period->academic_year : '' }}</option>
                        @endforeach
                    </select>
                    @error('grade_period') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="space-y-2">
                        <label for="grade_class" class="text-sm font-medium dark:text-gray-300">Kelas</label>
                        <select id="grade_class" name="class_id" class="block w-full rounded-lg border-gray-300 bg-white text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                            <option value="">Semua Kelas</option>
                            @foreach ($classes as $class)
                                <option value="{{ $class->id }}">{{ $class->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label for="grade_subject" class="text-sm font-medium dark:text-gray-300">Mata Pelajaran</label>
                        <select id="grade_subject" name="subject_id" class="block w-full rounded-lg border-gray-300 bg-white text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                            <option value="">Semua Mapel</option>
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label for="grade_category" class="text-sm font-medium dark:text-gray-300">Kategori</label>
                        <select id="grade_category" name="category_id" class="block w-full rounded-lg border-gray-300 bg-white text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                            <option value="">Semua Kategori</option>
                            @foreach ($gradeCategories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-2 pt-2 sm:flex sm:justify-end">
                    <x-filament::button type="submit" color="gray" icon="heroicon-m-document-arrow-down" formaction="{{ url('/admin/reports/grades.pdf') }}" class="w-full sm:w-auto">
                        Unduh PDF
                    </x-filament::button>
                    <x-filament::button type="submit" color="success" icon="heroicon-m-arrow-down-tray" class="w-full sm:w-auto">
                        Unduh Excel
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

    </div>
</x-filament-panels::page>
