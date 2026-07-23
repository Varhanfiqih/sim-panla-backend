<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            <div class="h-12 w-12 rounded-full bg-primary-500/10 flex items-center justify-center text-primary-600 dark:text-primary-400">
                <x-heroicon-o-user class="h-6 w-6" />
            </div>
            <div class="min-w-0 flex-1">
                <h2 class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">
                    Selamat datang, {{ auth()->user()->name }}!
                </h2>
                <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    Anda login dengan hak akses sebagai: 
                    <span class="inline-flex items-center rounded-md bg-primary-50 px-2 py-1 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-700/10 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/30">
                        {{ auth()->user()->role }}
                    </span>
                </p>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
