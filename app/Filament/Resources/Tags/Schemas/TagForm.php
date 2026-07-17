<?php

namespace App\Filament\Resources\Tags\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),
                ColorPicker::make('color')
                    ->label('Colore'),
                Textarea::make('description')
                    ->label('Descrizione')
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ]);
    }
}
