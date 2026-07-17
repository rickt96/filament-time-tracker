<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Workspace;
use App\Services\DashboardStatsService;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class TimeStatsOverviewWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var Workspace $workspace */
        $workspace = Filament::getTenant();

        $stats = app(DashboardStatsService::class);

        return [
            Stat::make('Ore oggi', number_format($stats->hoursToday($user, $workspace), 2)),
            Stat::make('Ore questa settimana', number_format($stats->hoursThisWeek($user, $workspace), 2)),
            Stat::make('Ore questo mese', number_format($stats->hoursThisMonth($user, $workspace), 2)),
        ];
    }
}
