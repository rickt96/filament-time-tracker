<?php

namespace App\Filament\Widgets\Billing;

use App\Models\Workspace;
use App\Services\Billing\InvoiceReportService;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class BillingByClientChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Fatturato per cliente';

    protected function getType(): string
    {
        return 'doughnut';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $rows = app(InvoiceReportService::class)
            ->totalsByClient($this->workspace(), $this->pageFilters ?? [])
            ->filter(fn (array $row): bool => (float) $row['invoiced'] > 0);

        return [
            'datasets' => [[
                'data' => $rows->map(fn (array $row): float => (float) $row['invoiced'])->all(),
            ]],
            'labels' => $rows->pluck('client_name')->all(),
        ];
    }

    private function workspace(): Workspace
    {
        /** @var Workspace $workspace */
        $workspace = Filament::getTenant();

        return $workspace;
    }
}
