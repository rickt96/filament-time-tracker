<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Workspace;
use App\Services\Reports\TimeReportService;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Override;

class HoursByProjectChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    //protected ?string $heading = 'Ore per giorno';

    /* #[Override]
    public function getHeading(): string|Htmlable|null
    {
        return "Totale ore: -";
    } */

    protected function getType(): string
    {
        return 'bar';
    }

    protected int | string | array $columnSpan = 'full';

    protected ?string $maxHeight = "400px";

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $rows = app(TimeReportService::class)->totalsByProjectAndDay($this->workspace(), $this->pageFilters ?? []);

        $this->heading = "Totale ore: " . ($rows->sum("total_seconds") / 3600);

        $days = $rows
            ->flatMap(fn (array $row): array => array_keys($row['days']))
            ->unique()
            ->sort()
            ->values();

        return [
            'datasets' => $rows->map(fn (array $row): array => [
                'label' => $row['project_name'],
                'data' => $days->map(fn (string $day): float => round(($row['days'][$day] ?? 0) / 3600, 2))->all(),
                'backgroundColor' => $row['color'],
            ])->all(),
            'labels' => $days->map(fn (string $day): string => Carbon::parse($day)->translatedFormat('D d/m'))->all(),
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
