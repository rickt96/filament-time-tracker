<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\HoursByProjectChartWidget;
use App\Models\WorkPackage;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use App\Exports\TimeEntriesExport;
use App\Filament\Widgets\Reports\HoursByProjectChartWidget as ReportsHoursByProjectChartWidget;
use App\Filament\Widgets\Reports\ProjectHoursDoughnutWidget;
use App\Livewire\HoursByProjectTableWidget;
use App\Models\Client;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\Workspace;
use App\Services\Reports\ProjectBudgetComparisonRow;
use App\Services\Reports\TimeReportService;
use App\Support\DurationFormatter;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Override;
use UnitEnum;

class Dashboard extends BaseDashboard
{
    // ...

    use HasFiltersAction;
    
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

    public function getColumns(): int | array
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