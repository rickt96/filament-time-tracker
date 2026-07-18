<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Filtri</x-slot>
        {{ $this->filtersForm }}
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Totale</x-slot>
        <p class="text-3xl font-bold">{{ $this->getTotalDuration() }}</p>
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
