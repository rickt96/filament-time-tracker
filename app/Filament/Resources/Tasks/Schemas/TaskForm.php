<?php

namespace App\Filament\Resources\Tasks\Schemas;

use App\Enums\TaskStatus;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('work_package_id')
                    ->label('Work Package')
                    ->relationship(
                        name: 'workPackage',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->whereHas(
                            'project',
                            fn (Builder $query) => $query->where('workspace_id', Filament::getTenant()?->getKey()),
                        ),
                    )
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),
                Select::make('status')
                    ->label('Stato')
                    ->options(TaskStatus::class)
                    ->default(TaskStatus::Todo)
                    ->required(),
                Select::make('assignee_id')
                    ->label('Assegnatario')
                    ->relationship('assignee', 'name')
                    ->default(fn (): int|string|null => Auth::id())
                    ->searchable()
                    ->preload(),
                TextInput::make('external_id')
                    ->label('ID esterno')
                    ->helperText('Identificativo del task nel sistema esterno (ClickUp, Jira). L\'app non crea task remoti: valorizzare questo campo associa manualmente il task locale a quello remoto.')
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('Descrizione')
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ]);
    }
}
