<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Filtri</x-slot>
        {{ $this->filtersForm }}
    </x-filament::section>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <x-filament::section>
            <x-slot name="heading">Totale</x-slot>
            <p class="text-3xl font-bold">{{ $this->getTotalDuration() }}</p>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Tariffa media applicata</x-slot>
            <p class="text-3xl font-bold">
                @if ($this->getAverageRate() !== null)
                    € {{ $this->getAverageRate() }}/h
                @else
                    —
                @endif
            </p>
        </x-filament::section>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        @livewire(\App\Filament\Widgets\Reports\HoursByProjectChartWidget::class, ['pageFilters' => $this->filters], 'summary-hours-by-project-chart')
        @livewire(\App\Filament\Widgets\Reports\ProjectHoursDoughnutWidget::class, ['pageFilters' => $this->filters], 'summary-project-hours-doughnut')
    </div>

    <x-filament::section>
        <x-slot name="heading">Per progetto</x-slot>
        <ul class="space-y-2 text-sm">
            @forelse ($this->getProjectBreakdown() as $row)
                <li class="flex items-center justify-between gap-4">
                    <span class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $row['color'] ?? '#94a3b8' }}"></span>
                        {{ $row['project_name'] }}
                        @if ($row['client_name'])
                            <span class="text-gray-500">- {{ $row['client_name'] }}</span>
                        @endif
                    </span>
                    <span class="font-medium">{{ $row['duration'] }}</span>
                </li>
            @empty
                <li class="text-gray-500">Nessun dato</li>
            @endforelse
        </ul>
    </x-filament::section>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        <x-filament::section>
            <x-slot name="heading">Per cliente</x-slot>
            <ul class="space-y-1 text-sm">
                @forelse ($this->getTotalsByClient() as $row)
                    <li class="flex justify-between gap-4">
                        <span>{{ $row['client_name'] }}</span>
                        <span class="font-medium">{{ number_format($row['hours'], 2) }} h · € {{ $row['amount'] }}</span>
                    </li>
                @empty
                    <li class="text-gray-500">Nessun dato</li>
                @endforelse
            </ul>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Per utente</x-slot>
            <ul class="space-y-1 text-sm">
                @forelse ($this->getTotalsByUser() as $row)
                    <li class="flex justify-between gap-4">
                        <span>{{ $row['user_name'] }}</span>
                        <span class="font-medium">{{ number_format($row['hours'], 2) }} h · € {{ $row['amount'] }}</span>
                    </li>
                @empty
                    <li class="text-gray-500">Nessun dato</li>
                @endforelse
            </ul>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Per Work Package</x-slot>
            <ul class="space-y-1 text-sm">
                @forelse ($this->getTotalsByWorkPackage() as $row)
                    <li class="flex justify-between gap-4">
                        <span>{{ $row['work_package_name'] }}</span>
                        <span class="font-medium">{{ number_format($row['hours'], 2) }} h · € {{ $row['amount'] }}</span>
                    </li>
                @empty
                    <li class="text-gray-500">Nessun dato</li>
                @endforelse
            </ul>
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Budget previsto vs consumato</x-slot>
        <ul class="space-y-1 text-sm">
            @forelse ($this->getBudgetComparison() as $row)
                <li class="flex justify-between gap-4">
                    <span>{{ $row->projectName }}</span>
                    <span class="font-medium">
                        {{ number_format($row->consumedHours, 2) }}
                        @if ($row->budgetHours !== null)
                            / {{ number_format($row->budgetHours, 2) }} h
                            ({{ number_format($row->utilizationPercentage, 2) }}%)
                        @else
                            h (nessun budget)
                        @endif
                    </span>
                </li>
            @empty
                <li class="text-gray-500">Nessun dato</li>
            @endforelse
        </ul>
    </x-filament::section>
</x-filament-panels::page>
