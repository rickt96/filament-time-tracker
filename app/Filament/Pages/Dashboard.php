<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Reports\HoursByProjectChartWidget as ReportsHoursByProjectChartWidget;
use App\Filament\Widgets\Reports\HoursByProjectTableWidget;
use App\Filament\Widgets\Reports\ProjectHoursDoughnutWidget;
use App\Models\Client;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\WorkPackage;
use App\Models\Workspace;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class Dashboard extends BaseDashboard
{
    use HasFiltersAction {
        mountHasFilters as mountHasFiltersFromSession;
    }

    protected Width|string|null $maxContentWidth = 'full';

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return Heroicon::OutlinedSquares2x2;
    }

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
                    Select::make('tag_id')
                        ->label('Tag')
                        ->options(fn () => Tag::query()->where('workspace_id', $workspace->id)->pluck('name', 'id'))
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
            // new WidgetConfiguration(ProjectHoursDoughnutWidget::class, ['requireDateRangeFilter' => true]),
        ];
    }

    private function workspace(): Workspace
    {
        /** @var Workspace $workspace */
        $workspace = Filament::getTenant();

        return $workspace;
    }
}
