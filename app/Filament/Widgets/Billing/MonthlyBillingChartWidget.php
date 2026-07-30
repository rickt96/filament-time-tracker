<?php

namespace App\Filament\Widgets\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Workspace;
use App\Services\Billing\InvoiceReportService;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class MonthlyBillingChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Fatturato per mese';

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $data = app(InvoiceReportService::class)->monthlyInvoicedTotals($this->workspace(), $this->pageFilters ?? []);

        $colors = [
            InvoiceStatus::Draft->value => '#9ca3af',
            InvoiceStatus::Sent->value => '#3b82f6',
            InvoiceStatus::Collected->value => '#22c55e',
        ];

        $labels = [
            InvoiceStatus::Draft->value => InvoiceStatus::Draft->getLabel(),
            InvoiceStatus::Sent->value => InvoiceStatus::Sent->getLabel(),
            InvoiceStatus::Collected->value => InvoiceStatus::Collected->getLabel(),
        ];

        return [
            'datasets' => collect($data['series'])->map(fn (array $values, string $status): array => [
                'label' => $labels[$status],
                'data' => array_values($values),
                'backgroundColor' => $colors[$status],
            ])->values()->all(),
            'labels' => $data['labels'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true],
            ],
        ];
    }

    private function workspace(): Workspace
    {
        /** @var Workspace $workspace */
        $workspace = Filament::getTenant();

        return $workspace;
    }
}
