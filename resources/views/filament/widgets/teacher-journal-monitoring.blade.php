<x-filament-widgets::widget>
    <x-filament::section wire:poll.15s>
        <x-slot name="heading">
            Live Monitoring Jurnal Guru
        </x-slot>

        <x-slot name="description">
            {{ $dateLabel }}. Data diperbarui otomatis setiap 15 detik.
        </x-slot>

        <div class="mb-5 flex justify-end">
            <div class="w-full sm:max-w-xs">
                <label for="journal-class-filter" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Filter Kelas
                </label>
                <select
                    id="journal-class-filter"
                    wire:change="filterByClass($event.target.value)"
                    class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                >
                    <option value="">Semua Kelas</option>
                    @foreach ($classOptions as $classId => $className)
                        <option value="{{ $classId }}" @selected($classFilter === (string) $classId)>{{ $className }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total sesi</p>
                <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $summary['total'] }}</p>
            </div>
            <div class="rounded-lg border border-success-200 bg-success-50 p-3 dark:border-success-500/20 dark:bg-success-500/10">
                <p class="text-xs font-medium text-success-700 dark:text-success-400">Sudah mengisi</p>
                <p class="mt-1 text-2xl font-semibold text-success-700 dark:text-success-400">{{ $summary['done'] }}</p>
            </div>
            <div class="rounded-lg border border-danger-200 bg-danger-50 p-3 dark:border-danger-500/20 dark:bg-danger-500/10">
                <p class="text-xs font-medium text-danger-700 dark:text-danger-400">Belum mengisi</p>
                <p class="mt-1 text-2xl font-semibold text-danger-700 dark:text-danger-400">{{ $summary['pending'] }}</p>
            </div>
            <div class="rounded-lg border border-info-200 bg-info-50 p-3 dark:border-info-500/20 dark:bg-info-500/10">
                <p class="text-xs font-medium text-info-700 dark:text-info-400">Belum waktunya</p>
                <p class="mt-1 text-2xl font-semibold text-info-700 dark:text-info-400">{{ $summary['upcoming'] }}</p>
            </div>
        </div>

        <div class="max-h-[36rem] overflow-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full table-auto divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-gray-950 dark:text-white">Guru</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-950 dark:text-white">Jam</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-950 dark:text-white">Mata Pelajaran</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-950 dark:text-white">Kelas</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-950 dark:text-white">Status Jurnal</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-950 dark:text-white">Diisi Pukul</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-950 dark:text-white">Materi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white dark:divide-white/10 dark:bg-gray-900">
                    @forelse ($rows as $row)
                        <tr wire:key="journal-monitor-{{ $row['nip'] }}-{{ $row['time'] }}-{{ $row['class'] }}">
                            <td class="whitespace-nowrap px-4 py-3">
                                <p class="font-medium text-gray-950 dark:text-white">{{ $row['teacher'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row['nip'] }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row['time'] }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row['subject'] }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row['class'] }}</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <x-filament::badge :color="$row['color']">
                                    {{ $row['status'] }}
                                </x-filament::badge>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">
                                {{ $row['submitted_at'] ?? '-' }}
                            </td>
                            <td class="max-w-xs truncate px-4 py-3 text-gray-700 dark:text-gray-300" title="{{ $row['material'] }}">
                                {{ $row['material'] ?: '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                {{ filled($classFilter) ? 'Tidak ada jadwal mengajar untuk kelas yang dipilih pada hari ini.' : 'Tidak ada jadwal mengajar pada hari ini.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
