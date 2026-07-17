<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Workspace;
use App\Services\DashboardStatsService;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class HoursByProjectChartWidget extends ChartWidget
{
    protected ?string $heading = 'Ore per progetto (mese corrente)';

    protected function getData(): array
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var Workspace $workspace */
        $workspace = Filament::getTenant();

        $hoursByProject = app(DashboardStatsService::class)->hoursByProjectThisMonth($user, $workspace);

        return [
            'datasets' => [
                [
                    'label' => 'Ore',
                    'data' => array_values($hoursByProject),
                ],
            ],
            'labels' => array_keys($hoursByProject),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
