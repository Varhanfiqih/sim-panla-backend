<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-2">

        {{-- Widget Export Presensi Harian --}}
        <x-filament::section icon="heroicon-o-qr-code" heading="Laporan Kehadiran (Gerbang)">
            <form wire:submit="exportAttendance" class="space-y-4">
                
                <div class="space-y-2">
                    <label for="attendance_date" class="text-sm font-medium dark:text-gray-300">Pilih Tanggal</label>
                    <input type="date" id="attendance_date" wire:model.defer="attendance_date" class="block w-full border-gray-300 bg-white text-gray-900 rounded-lg shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white" required>
                </div>

                <div class="space-y-2">
                    <label for="attendance_class" class="text-sm font-medium dark:text-gray-300">Filter Kelas (Opsional)</label>
                    <select id="attendance_class" wire:model.defer="attendance_class" class="block w-full border-gray-300 bg-white text-gray-900 rounded-lg shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white">
                        <option value="">-- Semua Kelas --</option>
                        @foreach ($classes as $kls)
                            <option value="{{ $kls->id }}">{{ $kls->id }}</option>
                        @endforeach
                    </select>
                </div>

                <x-slot name="footerActions">
                    <x-filament::button type="submit" color="success" icon="heroicon-m-arrow-down-tray">
                        Unduh Excel
                    </x-filament::button>
                </x-slot>
            </form>
        </x-filament::section>

        {{-- Widget Export Jurnal --}}
        <x-filament::section icon="heroicon-o-book-open" heading="Laporan Jurnal Mengajar & Absen">
            <form wire:submit="exportJournal" class="space-y-4">
                
                <div class="space-y-2">
                    <label for="journal_date" class="text-sm font-medium dark:text-gray-300">Pilih Tanggal Jurnal</label>
                    <input type="date" id="journal_date" wire:model.defer="journal_date" class="block w-full border-gray-300 bg-white text-gray-900 rounded-lg shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white" required>
                </div>

                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Rekapan otomatis mencakup: Mata Pelajaran, Guru Pengajar, Kelas, Jam Ke, Materi yang diisi serta <strong>Daftar Siswa yang Absen (Sakit/Izin/Alpa)</strong> di jam tersebut.
                </p>

                <x-slot name="footerActions">
                    <x-filament::button type="submit" color="success" icon="heroicon-m-arrow-down-tray">
                        Unduh Excel
                    </x-filament::button>
                </x-slot>
            </form>
        </x-filament::section>

    </div>
</x-filament-panels::page>
