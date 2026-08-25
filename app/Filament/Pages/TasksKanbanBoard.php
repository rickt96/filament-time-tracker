<?php

namespace App\Filament\Pages;

use App\Enums\ClientSyncDriver;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\WorkPackageStatus;
use App\Filament\Forms\Components\RichEditor\TaskMentionProvider;
use App\Filament\Resources\Tasks\Schemas\TaskForm;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Models\WorkPackage;
use App\Models\Workspace;
use App\Services\Sync\Drivers\ClickUpDriver;
use App\Services\Sync\Exceptions\ClickUpImportException;
use App\Support\TagOptions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Mokhosh\FilamentKanban\Pages\KanbanBoard;
use Override;

/**
 * Drag-and-drop board over the Task model, one column per TaskStatus case.
 *
 * It is a second view onto the same records as TaskResource rather than a
 * separate feature, so it deliberately reuses TaskResource::getEloquentQuery()
 * — that is where the "a Task belongs to the tenant through
 * workPackage.project.workspace_id" rule lives, and it must not be duplicated.
 */
class TasksKanbanBoard extends KanbanBoard
{
    protected static string $model = Task::class;

    protected static string $statusEnum = TaskStatus::class;

    protected static string $recordTitleAttribute = 'name';

    protected static string $recordStatusAttribute = 'status';

    protected Width|string|null $maxContentWidth = 'full';

    protected static string $recordView = 'filament.kanban.tasks.record';

    protected static string $statusView = 'filament.kanban.tasks.status';

    /** Required for the filter form: the package board view has no slot for it. */
    protected string $view = 'filament.kanban.tasks.board';

    /* protected static string $headerView = 'filament.kanban.status-header'; */

    /** How many cards a column shows before the "load more" button appears. */
    public int $columnLimit = 30;

    /**
     * Extra cards revealed per column, keyed by status id, as the user clicks
     * "load more". Reset whenever the filters change, since the columns are
     * repopulated from scratch.
     *
     * @var array<string, int>
     */
    public array $revealed = [];

    protected static ?int $navigationSort = 21;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static ?string $navigationLabel = 'Task board';

    protected static ?string $title = 'Tasks';

    protected string $editModalTitle = 'Modifica task';

    protected string $editModalSaveButtonLabel = 'Salva';

    protected string $editModalCancelButtonLabel = 'Annulla';

    protected bool $editModalSlideOver = false;

    protected string $editModalWidth = '4xl';

    /**
     * State of the filter bar above the board.
     *
     * @var array<string, mixed>
     */
    public array $filters = [
        'search' => null,
        'project_id' => null,
        'work_package_id' => null,
        'assignee_id' => null,
        'priority' => null,
    ];

    /* -----------------------------------------------------------------
     | Records
     | ----------------------------------------------------------------- */

    /**
     * @return Builder<Task>
     */
    #[Override]
    protected function getEloquentQuery(): Builder
    {
        return TaskResource::getEloquentQuery();
    }

    /**
     * @return Collection<int, Task>
     */
    #[Override]
    protected function records(): Collection
    {
        /** @var array{search: ?string, project_id: ?int, work_package_id: ?int, assignee_id: ?int, priority: ?string} $filters */
        $filters = $this->filters;

        return $this->getEloquentQuery()
            ->with(['workPackage.project', 'assignee'])
            // solo i work package attivi finiscono in board
            // ->whereHas('workPackage', fn ($q) => $q->whereIn('status', [WorkPackageStatus::InProgress]))
            ->when(
                filled($filters['search'] ?? null),
                fn (Builder $query) => $query->where('name', 'like', '%'.$filters['search'].'%'),
            )
            ->when(
                filled($filters['project_id'] ?? null),
                fn (Builder $query) => $query->whereHas(
                    'workPackage',
                    fn (Builder $workPackage) => $workPackage->where('project_id', $filters['project_id']),
                ),
            )
            ->when(
                filled($filters['work_package_id'] ?? null),
                fn (Builder $query) => $query->where('work_package_id', $filters['work_package_id']),
            )
            ->when(
                filled($filters['assignee_id'] ?? null),
                fn (Builder $query) => $query->where('assignee_id', $filters['assignee_id']),
            )
            ->when(
                filled($filters['priority'] ?? null),
                fn (Builder $query) => $query->where('priority', $filters['priority']),
            )
            ->get()
            // The whole result set is already in memory — the board splits it
            // per column itself — so ordering here avoids a dialect-specific
            // CASE expression for the priority ranking.
            ->sortBy($this->cardOrder(...))
            ->values();
    }

    /**
     * Sort key for a card: highest priority first, then nearest deadline, with
     * undated tasks last. The priority ranking is derived from the enum so a
     * new TaskPriority case cannot silently sink to the bottom of every column.
     *
     * @return array{int, int, int|string}
     */
    protected function cardOrder(Task $task): array
    {
        /** @var array<string, int> $priorityRank */
        $priorityRank = array_flip(array_column(TaskPriority::cases(), 'value'));

        return [
            $priorityRank[$task->priority->value] ?? PHP_INT_MAX,
            $task->expire?->getTimestamp() ?? PHP_INT_MAX,
            $task->getKey(),
        ];
    }

    /* -----------------------------------------------------------------
     | Per-column paging
     | ----------------------------------------------------------------- */

    /**
     * Same as the parent, but each column is capped at `$columnLimit` cards
     * (plus whatever "load more" has revealed) and carries its full count, so
     * the status view can render the remainder as a button.
     *
     * The cap is applied to the already-fetched collection rather than to the
     * query: the point is to keep the rendered DOM small — a board of several
     * hundred cards is heavy to render and to drag on — not to spare the
     * database a single indexed read.
     *
     * @return array<string, mixed>
     */
    #[Override]
    protected function getViewData(): array
    {
        $records = $this->records();

        $statuses = $this->statuses()->map(function (array $status) use ($records): array {
            $all = $this->filterRecordsByStatus($records, $status);
            $limit = $this->columnLimit + ($this->revealed[$status['id']] ?? 0);

            $status['total'] = count($all);
            $status['records'] = array_slice($all, 0, $limit);
            $status['hiddenCount'] = $status['total'] - count($status['records']);

            return $status;
        });

        return ['statuses' => $statuses];
    }

    public function loadMore(string $statusId): void
    {
        $this->revealed[$statusId] = ($this->revealed[$statusId] ?? 0) + $this->columnLimit;
    }

    /** New filters mean new columns, so the revealed counts no longer apply. */
    public function updatedFilters(): void
    {
        $this->revealed = [];
    }

    /* -----------------------------------------------------------------
     | Drag & drop
     | ----------------------------------------------------------------- */

    /**
     * @param  array<int, string>  $fromOrderedIds
     * @param  array<int, string>  $toOrderedIds
     */
    #[Override]
    public function onStatusChanged(int|string $recordId, string $status, array $fromOrderedIds, array $toOrderedIds): void
    {
        /** @var Task|null $task */
        $task = $this->getEloquentQuery()->find($recordId);

        // A card can only be dropped if it was rendered, and only tenant
        // records are rendered — so a miss here means a stale board.
        if ($task === null) {
            Notification::make()
                ->danger()
                ->title('Task non trovato')
                ->body('Ricarica la pagina e riprova.')
                ->send();

            return;
        }

        $newStatus = TaskStatus::tryFrom($status);

        if ($newStatus === null) {
            return;
        }

        $task->update(['status' => $newStatus]);

        Notification::make()
            ->success()
            ->title('Stato aggiornato')
            ->body("« {$task->name} » → {$newStatus->getLabel()}")
            ->send();
    }

    /* -----------------------------------------------------------------
     | Edit modal
     | ----------------------------------------------------------------- */

    /**
     * @return array<int, Component>
     */
    #[Override]
    protected function getEditModalFormSchema(null|int|string $recordId): array
    {
        return [
            TextInput::make('name')
                ->label('Nome')
                ->hiddenLabel()
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Grid::make(1)
                ->inlineLabel()
                ->schema([
                    ToggleButtons::make('status')
                        ->label('Stato')
                        ->options(TaskStatus::class)
                        ->required()
                        ->inline(),
                    /* ToggleButtons::make('priority')
                        ->label('Priorità')
                        ->options(TaskPriority::class)
                        ->required()
                        ->inline(), */
                    /* Select::make('assignee_id')
                        ->label('Assegnatario')
                        ->options(fn (): array => $this->assigneeOptions())
                        ->searchable(), */
                    DateTimePicker::make('expire')
                        ->label('Scadenza'),
                    TextInput::make('url')
                        ->label('URL')
                        ->url()
                        ->live()
                        ->suffixAction(
                            Action::make('task_url_goto')
                                ->visible(fn ($get) => filter_var($get('url'), FILTER_VALIDATE_URL))
                                ->icon(Heroicon::ArrowTopRightOnSquare)
                                ->url(fn ($get) => $get('url'), true),
                            true
                        ),
                ]),
            TagsInput::make('tags')
                ->inlineLabel()
                ->label('Tag')
                ->columnSpanFull()
                ->suggestions(fn (): array => array_values(TagOptions::from($this->getEloquentQuery()))),
            RichEditor::make('description')
                ->label('Descrizione')
                ->hiddenLabel()
                ->fileAttachmentsDirectory('tasks')
                ->mentions([
                    TaskMentionProvider::make(),
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $state
     */
    #[Override]
    protected function editRecord(int|string $recordId, array $data, array $state): void
    {
        /** @var Task|null $task */
        $task = $this->getEloquentQuery()->find($recordId);

        if ($task === null) {
            return;
        }

        $task->update($data);

        Notification::make()
            ->success()
            ->title('Task aggiornato')
            ->send();
    }

    /* -----------------------------------------------------------------
     | Filters
     | ----------------------------------------------------------------- */

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('filters')
            ->components([
                Grid::make(['default' => 1, 'sm' => 2, 'xl' => 5])
                    ->schema([
                        TextInput::make('search')
                            ->label('Cerca')
                            ->placeholder('Nome del task…')
                            ->prefixIcon(Heroicon::MagnifyingGlass)
                            ->live(debounce: 400),
                        Select::make('project_id')
                            ->label('Progetto')
                            ->options(function () {
                                return Project::query()
                                    ->where('workspace_id', Filament::getTenant()?->getKey())
                                    ->where('status', ProjectStatus::Active)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->searchable()
                            ->placeholder('Tutti')
                            ->live()
                            // A work package belongs to one project, so keeping
                            // both filters would produce an empty board.
                            ->afterStateUpdated(fn (Set $set) => $set('work_package_id', null)),
                        /* Select::make('work_package_id')
                            ->label('Work Package')
                            ->options(fn (Get $get): array => $this->workPackageOptions($get('project_id')))
                            ->searchable()
                            ->placeholder('Tutti')
                            ->live(),
                        Select::make('assignee_id')
                            ->label('Assegnatario')
                            ->options(fn (): array => $this->assigneeOptions())
                            ->searchable()
                            ->placeholder('Tutti')
                            ->live(),
                        Select::make('priority')
                            ->label('Priorità')
                            ->options(TaskPriority::class)
                            ->placeholder('Tutte')
                            ->live(), */
                    ]),
            ]);
    }

    public function hasActiveFilters(): bool
    {
        return collect($this->filters)->filter(fn ($value): bool => filled($value))->isNotEmpty();
    }

    public function resetFilters(): void
    {
        $this->filters = array_fill_keys(array_keys($this->filters), null);

        // Assigning the property directly does not go through updatedFilters().
        $this->revealed = [];
    }

    /**
     * @return array<int, string>
     */
    protected function projectOptions(): array
    {
        return Project::query()
            ->where('workspace_id', Filament::getTenant()?->getKey())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function workPackageOptions(int|string|null $projectId): array
    {
        return WorkPackage::query()
            ->whereHas(
                'project',
                fn (Builder $query) => $query->where('workspace_id', Filament::getTenant()?->getKey()),
            )
            ->when(filled($projectId), fn (Builder $query) => $query->where('project_id', $projectId))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function assigneeOptions(): array
    {
        $tenant = Filament::getTenant();

        if ($tenant === null) {
            return [];
        }

        /** @var Workspace $tenant */
        return $tenant->users()
            ->orderBy('name')
            ->pluck('name', 'users.id')
            ->all();
    }

    /* -----------------------------------------------------------------
     | Chrome
     | ----------------------------------------------------------------- */

    /**
     * @return array<int, Action|ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('createTask')
                ->label('Nuovo task')
                ->icon(Heroicon::OutlinedPlus)
                ->modalWidth('4xl')
                ->schema(TaskForm::components())
                ->action(function (array $data): void {
                    Task::create($data);

                    Notification::make()
                        ->success()
                        ->title('Task creato')
                        ->send();
                }),

            ActionGroup::make([
                $this->importTaskAction(),
                Action::make('tableView')
                    ->label('Tabella')
                    ->icon(Heroicon::OutlinedTableCells)
                    ->color('gray')
                    ->url(TaskResource::getUrl('index')),
            ]),
        ];
    }

    /**
     * Only ClickUp is supported for now — the project Select is scoped to
     * clients configured for that driver, so picking a project already
     * guarantees the import can be attempted.
     */
    protected function importTaskAction(): Action
    {
        return Action::make('importClickUpTask')
            ->label('Importa da ClickUp')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->modalHeading('Importa task da ClickUp')
            ->modalSubmitActionLabel('Importa')
            ->schema([
                Select::make('project_id')
                    ->label('Progetto')
                    ->options(fn (): array => Project::query()
                        ->where('workspace_id', Filament::getTenant()?->getKey())
                        ->whereHas('client', fn (Builder $query) => $query->where('sync_driver', ClientSyncDriver::ClickUp))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->required(),
                TextInput::make('external_task_id')
                    ->label('ID task ClickUp')
                    ->required(),
            ])
            ->action(function (array $data): void {
                $project = Project::findOrFail((int) $data['project_id']);

                try {
                    $task = app(ClickUpDriver::class)->importTask($project, $data['external_task_id']);
                } catch (ClickUpImportException $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Import fallito')
                        ->body($exception->getMessage())
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Task importato')
                    ->body("« {$task->name} »")
                    ->actions([
                        Action::make('delete')
                            ->label('Annulla')
                            ->action(fn () => $task->delete()),
                        Action::make('edit')
                            ->label('Modifica')
                            ->url(TaskResource::getUrl('edit', ['record' => $task])),
                    ])
                    ->send();
            });
    }
}
