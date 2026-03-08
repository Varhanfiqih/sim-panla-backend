<div class="space-y-6">
    <!-- Header Controls -->
    <div class="flex items-center justify-between p-4 bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h2 class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">
            Papan Jadwal Interaktif
        </h2>
        <div class="flex items-center gap-3">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Pilih Kelas:</label>
            <select wire:model.live="selectedClass" class="block w-48 rounded-lg border-gray-300 bg-gray-50 text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm">
                @foreach($classes as $c)
                    <option value="{{ $c->id }}">{{ $c->id }} - {{ $c->name ?? 'Kelas ' . $c->id }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- The Schedule Grid Matrix -->
    <div class="overflow-x-auto rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 bg-white dark:bg-gray-900 p-2">
        <table class="w-full text-sm text-left table-fixed min-w-[1000px]">
            <thead class="bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                <tr>
                    <th class="w-24 px-4 py-3 font-semibold text-center border-b border-r dark:border-gray-700">Waktu</th>
                    @foreach($days as $day)
                        <th class="px-4 py-3 font-semibold text-center border-b dark:border-gray-700">{{ ucfirst(strtolower($day)) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($slots as $slot)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition-colors">
                        <!-- Kolom Jam (Y-Axis Header) -->
                        <td class="px-2 py-3 text-center border-r dark:border-gray-700 bg-gray-50/30 dark:bg-gray-800/30">
                            <div class="font-bold text-gray-900 dark:text-white text-xs">Jam Ke-{{ $slot->id }}</div>
                            <div class="text-[10px] text-gray-500 mt-1">{{ \Carbon\Carbon::parse($slot->start_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($slot->end_time)->format('H:i') }}</div>
                        </td>

                        <!-- Kolom Hari (Matrix Cells) -->
                        @foreach($days as $day)
                            <td class="p-2 border-r dark:border-gray-700 dark:border-opacity-50 align-top relative h-28 mix-blend-normal"
                                x-data="{
                                    isHovered: false,
                                    dayTarget: '{{ $day }}',
                                    slotTarget: {{ $slot->id }}
                                }"
                                @dragover.prevent="isHovered = true"
                                @dragleave.prevent="isHovered = false"
                                @drop.prevent="
                                    isHovered = false;
                                    let draggedId = event.dataTransfer.getData('scheduleId');
                                    if(draggedId) {
                                        $wire.moveSchedule(draggedId, dayTarget, slotTarget);
                                    }
                                "
                                :class="isHovered ? 'bg-primary-50 dark:bg-primary-900/30 shadow-inner' : ''"
                            >
                                @php
                                    $schedule = $matrix[$day][$slot->id] ?? null;
                                @endphp

                                @if($schedule)
                                    <!-- Ada Jadwal -->
                                    <div 
                                        draggable="true"
                                        @dragstart="event.dataTransfer.setData('scheduleId', '{{ $schedule->id }}')"
                                        class="h-full w-full rounded-lg bg-primary-50 dark:bg-primary-900/40 border border-primary-200 dark:border-primary-800 p-2 flex flex-col justify-between shadow-sm hover:shadow transition-shadow cursor-grab active:cursor-grabbing relative z-10"
                                    >
                                        <div>
                                            <div class="font-bold text-primary-700 dark:text-primary-300 line-clamp-2 leading-tight">
                                                {{ $schedule->subject->name ?? 'Subject Deleted' }}
                                            </div>
                                            <div class="text-[11px] text-gray-600 dark:text-gray-400 mt-1 flex items-center gap-1">
                                                <svg class="w-3 h-3 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                                <span class="truncate">{{ $schedule->teacher->name ?? 'Teacher Deleted' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <!-- Slot Kosong -->
                                    <div class="absolute inset-2 flex items-center justify-center rounded-lg border-2 border-dashed border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 text-gray-300 transition-colors pointer-events-none">
                                        <svg class="w-5 h-5 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                    </div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
