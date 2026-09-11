<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Widgets\Reports\HoursByProjectChartWidget as ReportsHoursByProjectChartWidget;
use App\Filament\Widgets\Reports\HoursByProjectTableWidget;
use App\Filament\Widgets\Reports\ProjectHoursDoughnutWidget;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\WorkPackage;
use App\Models\Workspace;
use App\Support\TagOptions;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Ex-dashboard principale: la stessa panoramica filtrabile per ore/progetto,
 * spostata sotto "Report" quando la home del pannello è passata a un
 * riepilogo più personale (task in scadenza, aperti di recente).
 */
class Overview extends BaseDashboard
{
    use HasFiltersAction {
        mountHasFilters as mountHasFiltersFromSession;
    }

    // Filament\Pages\Dashboard hardcodes $routePath = '/' so the *base*
    // class always lands on the panel root — every subclass inherits that
    // unless it overrides the property, which collided with the real
    // App\Filament\Pages\Dashboard (also a Dashboard subclass) both trying
    // to register a route at '/'. Route name is unaffected (still slug-based
    // -> 'overview'), only the path needed to move.
    protected static string $routePath = 'reports/overview';

    protected Width|string|null $maxContentWidth = 'full';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Report';

    protected static ?int $navigationSort = 29;

    protected static ?string $navigationLabel = 'Panoramica';

    protected static ?string $title = 'Report — Panoramica';

    /**
     * HasFilters::mountHasFilters() restores filters from the session (or
     * the query string) if present, leaving $this->filters null otherwise.
     * Only once that's settled do we fall back to "current week" — so a
     * returning user's explicit choice (including an intentionally cleared
     * date range) is never overwritten, and only a genuinely first-ever
     * visit gets the default.
     */
    public function mountHasFilters(): void
    {
        $this->mountHasFiltersFromSession();

        if (blank($this->filters['from'] ?? null) && blank($this->filters['until'] ?? null)) {
            $this->filters = [
                ...($this->filters ?? []),
                'from' => now()->startOfMonth()->toDateString(),
                'until' => now()->endOfMonth()->toDateString(),
            ];
        }
    }

    protected function getHeaderActions(): array
    {
        $workspace = $this->workspace();

        return [
            FilterAction::make()
                ->schema([
                    DatePicker::make('from')
                        ->label('Dal')
                        ->live(),
                    DatePicker::make('until')
                        ->label('Al')
                        ->live(),
                    Select::make('project_id')
                        ->label('Progetto')
                        ->options(fn () => Project::query()->where('workspace_id', $workspace->id)->pluck('name', 'id'))
                        ->searchable()
                        ->live(),
                    Select::make('client_id')
                        ->label('Cliente')
                        ->options(fn () => Client::query()->where('workspace_id', $workspace->id)->pluck('name', 'id'))
                        ->searchable()
                        ->live(),
                    Select::make('tag')
                        ->label('Tag')
                        ->options(fn (): array => TagOptions::from(
                            TimeEntry::query()->whereHas('project', fn (Builder $query) => $query->where('workspace_id', $workspace->id)),
                        ))
                        ->searchable()
                        ->live(),
                    Select::make('user_id')
                        ->label('Utente')
                        ->options(fn () => $workspace->users()->pluck('users.name', 'users.id'))
                        ->searchable()
                        ->live(),
                    Select::make('task_id')
                        ->label('Task')
                        ->options(fn () => Task::query()
                            ->whereHas('workPackage.project', fn (Builder $query) => $query->where('workspace_id', $workspace->id))
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->live(),
                    Select::make('work_package_id')
                        ->label('Work Package')
                        ->options(fn () => WorkPackage::query()
                            ->whereHas('project', fn (Builder $query) => $query->where('workspace_id', $workspace->id))
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->live(),
                ]),
        ];
    }

    public function getColumns(): int|array
    {
        return 3;
    }

    public function getWidgets(): array
    {
        return [
            ReportsHoursByProjectChartWidget::class,
            HoursByProjectTableWidget::class,
            ProjectHoursDoughnutWidget::class,
        ];
    }

    private function workspace(): Workspace
    {
        /** @var Workspace $workspace */
        $workspace = Filament::getTenant();

        return $workspace;
    }
}
