<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Filtri</x-slot>
        {{ $this->filtersForm }}
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">
            {{ \Illuminate\Support\Carbon::parse($this->filters['from'])->translatedFormat('d M Y') }}
            &mdash;
            {{ \Illuminate\Support\Carbon::parse($this->filters['until'])->translatedFormat('d M Y') }}
        </x-slot>
        <x-slot name="afterHeader">
            <span class="text-lg font-bold">{{ $this->getGrandTotal() }}</span>
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="py-2 text-left font-medium">Progetto</th>
                        @foreach ($this->getWeekDays() as $day)
                            <th class="px-2 py-2 text-right font-medium">{{ $day->translatedFormat('D d/m') }}</th>
                        @endforeach
                        <th class="px-2 py-2 text-right font-medium">Totale</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->getRows() as $row)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-2">
                                {{ $row['project_name'] }}
                                @if ($row['client_name'])
                                    <span class="text-gray-500">- {{ $row['client_name'] }}</span>
                                @endif
                            </td>
                            @foreach ($this->getWeekDays() as $day)
                                <td class="px-2 py-2 text-right">{{ $row['days'][$day->toDateString()] }}</td>
                            @endforeach
                            <td class="px-2 py-2 text-right font-semibold">{{ $row['total'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $this->getWeekDays()->count() + 2 }}" class="py-4 text-center text-gray-500">
                                Nessun dato
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-300 font-semibold dark:border-gray-600">
                        <td class="py-2">Totale</td>
                        @foreach ($this->getDailyTotals() as $total)
                            <td class="px-2 py-2 text-right">{{ $total }}</td>
                        @endforeach
                        <td class="px-2 py-2 text-right">{{ $this->getGrandTotal() }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
