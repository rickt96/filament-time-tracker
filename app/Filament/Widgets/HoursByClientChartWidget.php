<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Workspace;
use App\Services\DashboardStatsService;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class HoursByClientChartWidget extends ChartWidget
{
    protected ?string $heading = 'Ore per cliente (mese corrente)';

    protected function getData(): array
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var Workspace $workspace */
        $workspace = Filament::getTenant();

        $hoursByClient = app(DashboardStatsService::class)->hoursByClientThisMonth($user, $workspace);

        return [
            'datasets' => [
                [
                    'label' => 'Ore',
                    'data' => array_values($hoursByClient),
                ],
            ],
            'labels' => array_keys($hoursByClient),
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
