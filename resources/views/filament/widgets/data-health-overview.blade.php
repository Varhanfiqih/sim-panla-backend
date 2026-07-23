<x-filament-widgets::widget>
    <x-filament::section wire:poll.30s>
        <x-slot name="heading">
            Kesehatan dan Kelengkapan Data
        </x-slot>

        <x-slot name="description">
            Pemeriksaan otomatis data utama sistem. Diperbarui setiap 30 detik.
        </x-slot>

        <div class="mb-5 flex flex-wrap gap-3">
            <x-filament::badge color="success" icon="heroicon-m-check-circle">
                {{ $healthyCount }} pemeriksaan aman
            </x-filament::badge>

            <x-filament::badge :color="$issueCount > 0 ? 'danger' : 'gray'" icon="heroicon-m-exclamation-triangle">
                {{ $issueCount }} perlu ditangani
            </x-filament::badge>
        </div>

        <div class="divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-white/10 dark:border-white/10">
            @foreach ($checks as $check)
                @php($isHealthy = $check['issue_count'] === 0)

                <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center">
                    <div @class([
                        'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg',
                        'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400' => $isHealthy,
                        'bg-danger-50 text-danger-600 dark:bg-danger-500/10 dark:text-danger-400' => ! $isHealthy,
                    ])>
                        <x-dynamic-component :component="$check['icon']" class="h-5 w-5" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-semibold text-gray-950 dark:text-white">{{ $check['label'] }}</p>
                            <x-filament::badge :color="$isHealthy ? 'success' : 'danger'">
                                {{ $isHealthy ? 'Aman' : 'Perlu ditangani' }}
                            </x-filament::badge>
                        </div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $check['description'] }}</p>
                        <p @class([
                            'mt-1 text-sm font-medium',
                            'text-success-600 dark:text-success-400' => $isHealthy,
                            'text-danger-600 dark:text-danger-400' => ! $isHealthy,
                        ])>
                            {{ $isHealthy ? $check['healthy_text'] : $check['issue_text'] }}
                        </p>
                    </div>

                    <x-filament::button
                        tag="a"
                        :href="$check['url']"
                        color="gray"
                        size="sm"
                        icon="heroicon-m-arrow-right"
                        icon-position="after"
                        class="w-full sm:w-auto"
                    >
                        {{ $check['action'] }}
                    </x-filament::button>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
