<?php

namespace App\Filament\Pages\Reports;

use App\Exports\TimeEntriesExport;
use App\Filament\Widgets\HoursByProjectChartWidget;
use App\Models\Client;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\WorkPackage;
use App\Models\Workspace;
use App\Services\Reports\ProjectBudgetComparisonRow;
use App\Services\Reports\TimeReportService;
use App\Support\DurationFormatter;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Override;
use UnitEnum;

class Summary extends Page
{
    // vista custom
    protected string $view = 'filament.pages.reports.summary';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Report';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Riepilogo';

    protected static ?string $title = 'Report — Riepilogo';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $filters = [];

    public function mount(): void
    {
        $this->getSchema('filtersForm')?->fill();
    }

    /* #[Override]
    public function getColumns(): int|array
    {
        return [
            'md' => 4,
            'xl' => 6,
        ];
    } */

    public function filtersForm(Schema $schema): Schema
    {
        $workspace = $this->workspace();

        return $schema
            ->statePath('filters')
            ->columns(4)
            ->components([
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
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportSpreadsheet')
                ->label('Esporta CSV/Excel')
                ->icon('heroicon-o-table-cells')
                ->action(fn () => Excel::download(new TimeEntriesExport($this->filteredEntries()), 'report-riepilogo.xlsx')),
            Action::make('exportPdf')
                ->label('Esporta PDF')
                ->icon('heroicon-o-document-text')
                ->action(fn () => Pdf::loadView('reports.time-report-pdf', [
                    'totalHours' => $this->getTotalHours(),
                    'averageRate' => $this->getAverageRate(),
                    'byProject' => $this->getTotalsByProject(),
                    'byClient' => $this->getTotalsByClient(),
                    'byUser' => $this->getTotalsByUser(),
                    'byWorkPackage' => $this->getTotalsByWorkPackage(),
                    'budgetComparison' => $this->getBudgetComparison(),
                ])->download('report-riepilogo.pdf')),
        ];
    }


    public function getWidgets(): array
    {
        return [
            HoursByProjectChartWidget::class,
        ];
    }


    public function getTotalDuration(): string
    {
        return DurationFormatter::hoursMinutesSeconds(
            app(TimeReportService::class)->totalSeconds($this->workspace(), $this->filters ?? []),
        );
    }

    public function getTotalHours(): float
    {
        return app(TimeReportService::class)->totalHours($this->workspace(), $this->filters ?? []);
    }

    public function getAverageRate(): ?string
    {
        return app(TimeReportService::class)->averageRate($this->workspace(), $this->filters ?? []);
    }

    /**
     * @return Collection<int, array{project_id: int, project_name: string, hours: float, amount: string}>
     */
    public function getTotalsByProject(): Collection
    {
        return app(TimeReportService::class)->totalsByProject($this->workspace(), $this->filters ?? []);
    }

    /**
     * @return Collection<int, array{client_id: int, client_name: string, hours: float, amount: string}>
     */
    public function getTotalsByClient(): Collection
    {
        return app(TimeReportService::class)->totalsByClient($this->workspace(), $this->filters ?? []);
    }

    /**
     * @return Collection<int, array{user_id: int, user_name: string, hours: float, amount: string}>
     */
    public function getTotalsByUser(): Collection
    {
        return app(TimeReportService::class)->totalsByUser($this->workspace(), $this->filters ?? []);
    }

    /**
     * @return Collection<int, array{work_package_id: int|null, work_package_name: string, hours: float, amount: string}>
     */
    public function getTotalsByWorkPackage(): Collection
    {
        return app(TimeReportService::class)->totalsByWorkPackage($this->workspace(), $this->filters ?? []);
    }

    /**
     * @return Collection<int, ProjectBudgetComparisonRow>
     */
    public function getBudgetComparison(): Collection
    {
        return app(TimeReportService::class)->budgetComparisonByProject($this->workspace(), $this->filters ?? []);
    }

    /**
     * Per-project breakdown with duration already formatted as H:MM:SS,
     * for the "Group by: Progetto" list next to the doughnut chart.
     *
     * @return Collection<int, array{project_id: int, project_name: string, client_name: string, color: ?string, duration: string}>
     */
    public function getProjectBreakdown(): Collection
    {
        return app(TimeReportService::class)
            ->totalsByProjectAndDay($this->workspace(), $this->filters ?? [])
            ->map(fn (array $row): array => [
                'project_id' => $row['project_id'],
                'project_name' => $row['project_name'],
                'client_name' => $row['client_name'],
                'color' => $row['color'],
                'duration' => DurationFormatter::hoursMinutesSeconds($row['total_seconds']),
            ]);
    }

    /**
     * @return Collection<int, TimeEntry>
     */
    private function filteredEntries(): Collection
    {
        return app(TimeReportService::class)
            ->query($this->workspace(), $this->filters ?? [])
            ->select('time_entries.*')
            ->with(['project.client', 'user', 'task'])
            ->get();
    }

    private function workspace(): Workspace
    {
        /** @var Workspace $workspace */
        $workspace = Filament::getTenant();

        return $workspace;
    }
}
