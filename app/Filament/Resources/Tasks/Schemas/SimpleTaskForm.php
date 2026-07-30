<?php

namespace App\Filament\Resources\Tasks\Schemas;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class SimpleTaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Select::make('status')
                    ->label('Stato')
                    ->options(TaskStatus::class)
                    ->default(TaskStatus::Todo)
                    ->required(),
                Select::make('priority')
                    ->label('Priorità')
                    ->options(TaskPriority::class)
                    ->default(TaskPriority::Media)
                    ->required(),
                Select::make('assignee_id')
                    ->label('Assegnatario')
                    ->relationship('assignee', 'name')
                    ->default(fn (): int|string|null => Auth::id())
                    ->searchable()
                    ->preload(),
                DateTimePicker::make('expire')
                    ->label('Scadenza'),
            ]);
    }
}
