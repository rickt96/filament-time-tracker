<?php

namespace App\Filament\Widgets\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Workspace;
use App\Services\Billing\InvoiceReportService;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InvoiceStatsOverviewWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected function getStats(): array
    {
        $service = app(InvoiceReportService::class);
        $workspace = $this->workspace();
        $filters = $this->pageFilters ?? [];

        $produced = (float) $service->totalProduced($workspace, $filters);
        $invoiced = (float) $service->totalInvoiced($workspace, $filters);
        $gap = $produced - $invoiced;
        $collectionRate = $service->collectionRate($workspace, $filters);

        return [
            Stat::make('Valore prodotto', '€ '.number_format($produced, 2))
                ->description('Da ore registrate')
                ->color('gray'),
            Stat::make('Fatturato', '€ '.number_format($invoiced, 2))
                ->description('Inviate + incassate')
                ->color('info'),
            Stat::make('Da fatturare', '€ '.number_format(max(0, $gap), 2))
                ->description('Prodotto non ancora fatturato')
                ->color($gap > 0 ? 'warning' : 'success'),
            Stat::make('Incassato', '€ '.number_format((float) $service->totalByStatus($workspace, $filters, InvoiceStatus::Collected), 2))
                ->color('success'),
            Stat::make('In attesa di incasso', '€ '.number_format((float) $service->totalByStatus($workspace, $filters, InvoiceStatus::Sent), 2))
                ->description('Fatture inviate non ancora incassate')
                ->color('warning'),
            Stat::make('Tasso di incasso', $collectionRate !== null ? "{$collectionRate}%" : '—')
                ->description('Incassato / (Inviate + incassate)')
                ->color(match (true) {
                    $collectionRate === null => 'gray',
                    $collectionRate >= 90 => 'success',
                    $collectionRate >= 60 => 'warning',
                    default => 'danger',
                }),
        ];
    }

    private function workspace(): Workspace
    {
        /** @var Workspace $workspace */
        $workspace = Filament::getTenant();

        return $workspace;
    }
}
