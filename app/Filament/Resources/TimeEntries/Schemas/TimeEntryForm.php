<?php

namespace App\Filament\Resources\TimeEntries\Schemas;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
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
                Select::make('project_id')
                    ->label('Progetto')
                    ->relationship(
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
                    )
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required()
                    ->afterStateUpdated(fn ($set) => $set('task_id', null)),
                Placeholder::make('client')
                    ->label('Cliente')
                    ->content(fn (Get $get): string => Project::find((int) ($get('project_id') ?? 0))?->client->name ?? '—'),
                Select::make('task_id')
                    ->label('Task (opzionale)')
                    ->relationship(
                        name: 'task',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query, Get $get) => $query->whereHas(
                            'workPackage',
                            fn (Builder $query) => $query->where('project_id', $get('project_id')),
                        ),
                    )
                    ->searchable()
                    ->preload(),
                Select::make('tags')
                    ->label('Tag')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
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
                Textarea::make('description')
                    ->label('Descrizione')
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ]);
    }
}
