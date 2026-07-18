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

    protected function getType(): string
    {
        return 'doughnut';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $rows = app(TimeReportService::class)->totalsByProjectAndDay($this->workspace(), $this->pageFilters ?? []);

        return [
            'datasets' => [[
                'data' => $rows->map(fn (array $row): float => round($row['total_seconds'] / 3600, 2))->all(),
                'backgroundColor' => $rows->pluck('color')->all(),
            ]],
            'labels' => $rows->pluck('project_name')->all(),
        ];
    }

    private function workspace(): Workspace
    {
        /** @var Workspace $workspace */
        $workspace = Filament::getTenant();

        return $workspace;
    }
}
