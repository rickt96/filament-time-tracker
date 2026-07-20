<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Workspace;
use App\Services\Reports\TimeReportService;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class ProjectHoursDoughnutWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected int | string | array $columnSpan = 1;

    protected ?string $heading = null;

    /**
     * When true (set via WidgetConfiguration on the hosting page — the
     * Dashboard does this, the Summary report does not), the chart renders
     * empty until both 'from' and 'until' are explicitly present in the
     * page filters, instead of silently falling back to an all-time query.
     */
    public bool $requireDateRangeFilter = false;

    protected function getType(): string
    {
        return 'doughnut';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        if ($this->isDateRangeFilterMissing()) {
            return ['datasets' => [], 'labels' => []];
        }

        $rows = app(TimeReportService::class)->totalsByProjectAndDay($this->workspace(), $this->pageFilters ?? []);

        return [
            'datasets' => [[
                'data' => $rows->map(fn (array $row): float => round($row['total_seconds'] / 3600, 2))->all(),
                'backgroundColor' => $rows->pluck('color')->all(),
            ]],
            'labels' => $rows->pluck('project_name')->all(),
        ];
    }

    private function isDateRangeFilterMissing(): bool
    {
        return blank($this->pageFilters['from'] ?? null) || blank($this->pageFilters['until'] ?? null);
    }

    private function workspace(): Workspace
    {
        /** @var Workspace $workspace */
        $workspace = Filament::getTenant();

        return $workspace;
    }
}
