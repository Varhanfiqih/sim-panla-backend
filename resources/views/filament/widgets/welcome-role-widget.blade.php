<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center gap-x-4">
            <div class="h-12 w-12 rounded-full bg-primary-500/10 flex items-center justify-center text-primary-600 dark:text-primary-400">
                <x-heroicon-o-user class="h-6 w-6" />
            </div>
            <div class="flex-1">
                <h2 class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">
                    Selamat datang, {{ auth()->user()->name }}!
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Anda login dengan hak akses sebagai: 
                    <span class="inline-flex items-center rounded-md bg-primary-50 px-2 py-1 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-700/10 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/30">
                        {{ auth()->user()->role }}
                    </span>
                </p>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
