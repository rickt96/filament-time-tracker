<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Enums\ClientSyncDriver;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dati cliente')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                        ColorPicker::make('color')
                            ->label('Colore'),
                        TextInput::make('contact_name')
                            ->label('Referente')
                            ->maxLength(255),
                        TextInput::make('contact_email')
                            ->label('Email di contatto')
                            ->email()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Descrizione')
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Attivo')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),
                Section::make('Sincronizzazione')
                    ->description('Configurazione del provider esterno (ClickUp, Jira). La logica di sincronizzazione sarà attivata in una fase successiva.')
                    ->columns(2)
                    ->components([
                        Select::make('sync_driver')
                            ->label('Driver')
                            ->options(ClientSyncDriver::class)
                            ->native(false),
                        KeyValue::make('sync_configuration')
                            ->label('Configurazione')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
