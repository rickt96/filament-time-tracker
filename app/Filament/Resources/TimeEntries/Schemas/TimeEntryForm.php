<?php

namespace App\Filament\Resources\TimeEntries\Schemas;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\WorkPackage;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TimeEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...self::projectComponents(),
                DatePicker::make('date')
                    ->label('Data')
                    ->required()
                    ->default(now()),
                Radio::make('entry_mode')
                    ->label('Modalità inserimento')
                    ->options([
                        'range' => 'Intervallo orario',
                        'duration' => 'Durata (ore e minuti)',
                        'minutes' => 'Solo minuti',
                    ])
                    ->default('range')
                    ->live()
                    ->inline()
                    ->visible(fn (string $operation): bool => $operation === 'create'),
                TimePicker::make('started_at')
                    ->label('Inizio')
                    ->seconds(false)
                    ->visible(fn (string $operation, Get $get): bool => $operation === 'edit' || $get('entry_mode') === 'range')
                    ->required(fn (string $operation, Get $get): bool => $operation === 'edit' || $get('entry_mode') === 'range'),
                TimePicker::make('ended_at')
                    ->label('Fine')
                    ->seconds(false)
                    ->visible(fn (string $operation, Get $get): bool => $operation === 'edit' || $get('entry_mode') === 'range')
                    ->required(fn (string $operation, Get $get): bool => $operation === 'edit' || $get('entry_mode') === 'range'),
                TextInput::make('duration_hours')
                    ->label('Ore')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->visible(fn (string $operation, Get $get): bool => $operation === 'create' && $get('entry_mode') === 'duration'),
                TextInput::make('duration_minutes_part')
                    ->label('Minuti')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->maxValue(59)
                    ->visible(fn (string $operation, Get $get): bool => $operation === 'create' && $get('entry_mode') === 'duration'),
                TextInput::make('minutes_only')
                    ->label('Minuti')
                    ->numeric()
                    ->minValue(1)
                    ->visible(fn (string $operation, Get $get): bool => $operation === 'create' && $get('entry_mode') === 'minutes')
                    ->required(fn (string $operation, Get $get): bool => $operation === 'create' && $get('entry_mode') === 'minutes'),
                self::descriptionComponent(),
            ]);
    }

    /**
     * Used by the Calendar widget instead of `configure()`: there, entries
     * are always created/edited by dragging out or editing a time range
     * directly on the grid, so start/end must always be present — the
     * duration/minutes entry modes only make sense in the standalone
     * Resource form, where there's no visual timeline to drag on.
     */
    public static function configureRangeOnly(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...self::projectComponents(),
                DatePicker::make('date')
                    ->inlineLabel()
                    ->label('Data')
                    ->required()
                    ->default(now()),
                TimePicker::make('started_at')
                    ->inlineLabel()
                    ->label('Inizio')
                    ->seconds(false)
                    /* ->minutesStep(5)
                    ->native(false) */
                    ->default(fn (): string => self::defaultRoundedTime(now()))
                    ->required(),
                TimePicker::make('ended_at')
                    ->inlineLabel()
                    ->label('Fine')
                    ->seconds(false)
                    ->default(fn (): string => self::defaultRoundedTime(now()->addHour()))
                    ->required(),
                self::descriptionComponent(),
            ]);
    }

    /**
     * @return array<int, Component>
     */
    private static function projectComponents(): array
    {
        return [
            Select::make('project_id')
                ->inlineLabel()
                ->label('Progetto')
                /* ->relationship(
                    name: 'project',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn (Builder $query) => $query
                        ->where('workspace_id', Filament::getTenant()?->getKey())
                        ->where('status', ProjectStatus::Active)
                        // Favorited projects surface first, to speed up selection during time logging.
                        ->orderByRaw(
                            '(select count(*) from favorites where favorites.favoritable_type = ? and favorites.favoritable_id = projects.id and favorites.user_id = ?) desc',
                            [Project::class, Auth::id()],
                        )
                        ->orderBy('name'),
                ) */
                ->options(function(){
                    return Project::query()
                            ->with('client')
                            ->where('workspace_id', Filament::getTenant()?->getKey())
                            ->where('status', ProjectStatus::Active)
                            ->orderByRaw(
                                '(select count(*) from favorites where favorites.favoritable_type = ? and favorites.favoritable_id = projects.id and favorites.user_id = ?) desc',
                                [Project::class, Auth::id()],
                            )
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(function (Project $project) {
                                return [
                                    $project->id => "{$project->name} - {$project->client?->name}",
                                ];
                            });
                })
                ->searchable()
                ->preload()
                ->live()
                ->required()
                ->afterStateUpdated(function ($set) {
                    $set('work_package_id', null);
                    $set('task_id', null);
                }),
            // includo il cliente nel progetto
            /* Placeholder::make('client')
                ->label('Cliente')
                ->content(fn (Get $get): string => Project::find((int) ($get('project_id') ?? 0))?->client->name ?? '—'), */
            Select::make('work_package_id')
                ->inlineLabel()
                ->label('Work Package')
                ->relationship(
                    name: 'workPackage',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('project_id', $get('project_id')),
                )
                ->searchable()
                ->preload()
                ->live()
                ->disabled(fn (Get $get): bool => blank($get('project_id')) || filled($get('task_id')))
                ->dehydrated()
                ->afterStateUpdated(fn ($set) => $set('task_id', null)),
            Select::make('task_id')
                ->inlineLabel()
                ->label('Task')
                ->relationship(
                    name: 'task',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('work_package_id', $get('work_package_id')),
                )
                ->searchable()
                ->preload()
                ->live()
                ->disabled(fn (Get $get): bool => blank($get('work_package_id')))
                ->dehydrated()
                ->afterStateUpdated(function ($state, $set) {
                    // Only derive the Work Package when a Task is picked — clearing
                    // the Task should merely unlock the field again (its disabled()
                    // condition depends on task_id), not wipe out the Work Package
                    // the entry is still logically under.
                    if (filled($state)) {
                        $set('work_package_id', Task::find((int) $state)?->work_package_id);
                    }
                })
                ->createOptionForm(fn (): array => [
                    Select::make('work_package_id')
                        ->label('Work Package')
                        // Not scoped to the outer form's project_id: this modal's
                        // schema isn't nested inside the main form's component
                        // tree (it belongs to the mounted create-option Action),
                        // so relative Get paths like '../project_id' don't reach
                        // it — that syntax only works for Repeater/Builder items.
                        // Listing every work package in the tenant, labelled by
                        // project, keeps this correct regardless of which page
                        // hosts the form (Create/Edit page, table modal, Calendar).
                        ->options(fn (): array => WorkPackage::query()
                            ->with('project')
                            ->whereHas('project', fn (Builder $query) => $query->where('workspace_id', Filament::getTenant()?->getKey()))
                            ->get()
                            ->mapWithKeys(fn (WorkPackage $workPackage): array => [
                                $workPackage->id => "{$workPackage->project->name} — {$workPackage->name}",
                            ])
                            ->all())
                        ->required()
                        ->searchable(),

                    TextInput::make('name')
                        ->label('Nome')
                        ->required()
                        ->maxLength(255),
                    
                    TextInput::make('external_id')
                        ->label('ID Esterno')
                        ->maxLength(255),

                    Hidden::make('assignee_id')
                        ->default(fn (): int|string|null => Auth::id()),
                ])
                ->createOptionAction(fn (Action $action) => $action->modalHeading('Nuovo task')),
            /* Select::make('tags')
                ->inlineLabel()
                ->label('Tag')
                ->relationship('tags', 'name')
                ->multiple()
                ->searchable()
                ->preload(), */
        ];
    }

    private static function descriptionComponent(): Component
    {
        return Textarea::make('description')
            ->inlineLabel()
            ->label('Descrizione')
            ->maxLength(1000)
            ->columnSpanFull();
    }

    /**
     * Current time rounded to the nearest quarter-hour (e.g. 14:37 -> 14:30,
     * 14:38 -> 14:45), so a freshly opened "start now" defaults to a clean
     * value instead of the exact second the form happened to be opened.
     */
    private static function defaultRoundedTime(Carbon|CarbonImmutable $date): string
    {
        return $date->copy()
            ->startOfHour()
            ->addMinutes((int) round($date->minute / 15) * 15)
            ->format('H:i');
    }
}
