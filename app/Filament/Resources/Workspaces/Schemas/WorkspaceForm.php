<?php

namespace App\Filament\Resources\Workspaces\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class WorkspaceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('Descrizione')
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ]);
    }
}
