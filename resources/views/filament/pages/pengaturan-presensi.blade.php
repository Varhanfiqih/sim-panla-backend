<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-8 flex justify-end">
            <x-filament::button type="submit" size="lg" color="primary" icon="heroicon-m-check-circle">
                Simpan Perubahan
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
