<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-8 grid grid-cols-1 sm:flex sm:justify-end">
            <x-filament::button type="submit" size="lg" color="primary" icon="heroicon-m-check-circle" class="w-full sm:w-auto">
                Simpan Perubahan
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
