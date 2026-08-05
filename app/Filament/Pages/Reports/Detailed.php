<?php

namespace App\Filament\Pages\Reports;

use App\Exports\TimeEntriesExport;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\WorkPackage;
use App\Models\Workspace;
use App\Services\Reports\TimeReportService;
use App\Support\DurationFormatter;
use App\Support\TagOptions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;

class Detailed extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.reports.detailed';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|UnitEnum|null $navigationGroup = 'Report';

    protected static ?int $navigationSort = 31;

    protected static ?string $navigationLabel = 'Dettagliato';

    protected static ?string $title = 'Report — Dettagliato';

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
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => app(TimeReportService::class)
                ->query($this->workspace(), $this->filters ?? [])
                ->select('time_entries.*'))
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label('Data')
                    ->date()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Descrizione')
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('project.name')
                    ->label('Progetto')
                    ->getStateUsing(fn (TimeEntry $record): string => "{$record->project->name} ({$record->client->name})")
                    ->searchable(),
                TextColumn::make('task.name')
                    ->label('Task')
                    ->placeholder('—'),
                TextColumn::make('user.name')
                    ->label('Utente')
                    ->searchable(),
                TextColumn::make('started_at')
                    ->label('Orario')
                    ->getStateUsing(fn (TimeEntry $record): string => $record->started_at->format('H:i').' - '.($record->ended_at?->format('H:i') ?? '…')),
                TextColumn::make('duration_seconds')
                    ->label('Durata')
                    ->formatStateUsing(fn (int $state): string => DurationFormatter::hoursMinutesSeconds($state))
                    ->sortable(),
            ])
            ->paginated([25, 50, 100]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportSpreadsheet')
                ->label('Esporta CSV/Excel')
                ->icon('heroicon-o-table-cells')
                ->action(fn () => Excel::download(new TimeEntriesExport($this->filteredEntries()), 'report-dettagliato.xlsx')),
        ];
    }

    public function getTotalDuration(): string
    {
        return DurationFormatter::hoursMinutesSeconds(
            app(TimeReportService::class)->totalSeconds($this->workspace(), $this->filters ?? []),
        );
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
