<?php

namespace App\Filament\Widgets;

use App\Models\TimeEntry;
use App\Models\Workspace;
use App\Support\DurationFormatter;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * "Quanto ho segnato" a colpo d'occhio, per l'utente corrente. La card
 * "Ore oggi" fa doppio uso da prompt visivo: se a zero, passa a stile
 * warning con un messaggio invece di una card/banner separata.
 */
class TimeStatsOverviewWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $userId = Auth::id();
        $workspace = $this->workspace();

        $todaySeconds = (int) $this->query($userId, $workspace)
            ->whereDate('date', today())
            ->sum('duration_seconds');

        $weekSeconds = (int) $this->query($userId, $workspace)
            ->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()])
            ->sum('duration_seconds');

        $hasLoggedToday = $todaySeconds > 0;

        return [
            Stat::make('Ore oggi', DurationFormatter::hoursMinutesSeconds($todaySeconds))
                ->description($hasLoggedToday ? null : 'Non hai ancora segnato ore oggi')
                ->descriptionIcon($hasLoggedToday ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                ->color($hasLoggedToday ? 'success' : 'warning'),
            Stat::make('Ore questa settimana', DurationFormatter::hoursMinutesSeconds($weekSeconds))
                ->color('gray'),
        ];
    }

    /**
     * @return Builder<TimeEntry>
     */
    private function query(int|string|null $userId, Workspace $workspace): Builder
    {
        return TimeEntry::query()
            ->where('user_id', $userId)
            ->whereHas('project', fn (Builder $query) => $query->where('workspace_id', $workspace->id));
    }

    private function workspace(): Workspace
    {
        /** @var Workspace $workspace */
        $workspace = Filament::getTenant();

        return $workspace;
    }
}
