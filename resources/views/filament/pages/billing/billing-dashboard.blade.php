<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Filtri</x-slot>
        {{ $this->filtersForm }}
    </x-filament::section>

    @livewire(\App\Filament\Widgets\Billing\InvoiceStatsOverviewWidget::class, ['pageFilters' => $this->filters], 'billing-stats-overview')

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        @livewire(\App\Filament\Widgets\Billing\MonthlyBillingChartWidget::class, ['pageFilters' => $this->filters], 'billing-monthly-chart')
        @livewire(\App\Filament\Widgets\Billing\BillingByClientChartWidget::class, ['pageFilters' => $this->filters], 'billing-by-client-chart')
    </div>

    <x-filament::section>
        <x-slot name="heading">Per cliente: prodotto vs fatturato vs incassato</x-slot>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500">
                        <th class="py-1 pr-4">Cliente</th>
                        <th class="py-1 pr-4 text-right">Prodotto</th>
                        <th class="py-1 pr-4 text-right">Fatturato</th>
                        <th class="py-1 pr-4 text-right">Incassato</th>
                        <th class="py-1 text-right">Da fatturare</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->getClientBreakdown() as $row)
                        <tr class="border-t border-gray-100 dark:border-white/5">
                            <td class="py-1 pr-4">{{ $row['client_name'] }}</td>
                            <td class="py-1 pr-4 text-right">€ {{ $row['produced'] }}</td>
                            <td class="py-1 pr-4 text-right">€ {{ $row['invoiced'] }}</td>
                            <td class="py-1 pr-4 text-right">€ {{ $row['collected'] }}</td>
                            <td class="py-1 text-right font-medium {{ (float) $row['gap'] > 0 ? 'text-warning-600' : '' }}">
                                € {{ $row['gap'] }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-2 text-gray-500">Nessun dato</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
