<?php

namespace App\Filament\Pages\Reports;

use App\Exports\TimeEntriesExport;
use App\Models\Client;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\WorkPackage;
use App\Models\Workspace;
use App\Services\Reports\ProjectBudgetComparisonRow;
use App\Services\Reports\TimeReportService;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class TimeReport extends Page
{
    protected string $view = 'filament.pages.reports.time-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Report';

    protected static ?string $title = 'Report';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $filters = [];

    public function mount(): void
    {
        $this->getSchema('filtersForm')?->fill();
    }

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
                ->action(fn () => Excel::download(new TimeEntriesExport($this->filteredEntries()), 'report-ore.xlsx')),
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
                ])->download('report-ore.pdf')),
        ];
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
     * @return Collection<int, array{work_package_id: int, work_package_name: string, hours: float, amount: string}>
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
     * @return array<string, float>
     */
    public function getTotalsByDay(): array
    {
        return app(TimeReportService::class)->totalsByDay($this->workspace(), $this->filters ?? []);
    }

    /**
     * @return array<string, float>
     */
    public function getTotalsByWeek(): array
    {
        return app(TimeReportService::class)->totalsByWeek($this->workspace(), $this->filters ?? []);
    }

    /**
     * @return array<string, float>
     */
    public function getTotalsByMonth(): array
    {
        return app(TimeReportService::class)->totalsByMonth($this->workspace(), $this->filters ?? []);
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
