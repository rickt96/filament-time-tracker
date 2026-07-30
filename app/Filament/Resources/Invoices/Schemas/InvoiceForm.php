<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Enums\InvoiceStatus;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(3)
                    ->columnSpanFull()
                    ->components([
                        Select::make('client_id')
                            ->label('Cliente')
                            ->relationship(
                                name: 'client',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query
                                    ->where('workspace_id', Filament::getTenant()?->getKey())
                                    ->where('is_active', true),
                            )
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('year')
                            ->label('Anno')
                            ->numeric()
                            ->default(now()->year)
                            ->required(),
                        TextInput::make('number')
                            ->label('Numero anno')
                            ->numeric()
                            ->required(),
                        TextInput::make('amount')
                            ->label('Importo')
                            ->numeric()
                            ->prefix('€')
                            ->required(),
                        DatePicker::make('sent_at')
                            ->label('Data invio'),
                        DatePicker::make('collected_at')
                            ->label('Data incasso'),
                        ToggleButtons::make('status')
                            ->label('Stato')
                            ->options(InvoiceStatus::class)
                            ->default(InvoiceStatus::Draft)
                            ->inline()
                            ->required(),
                        Select::make('projects')
                            ->label('Progetti collegati')
                            ->relationship(
                                name: 'projects',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query
                                    ->where('workspace_id', Filament::getTenant()?->getKey()),
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                    ]),

                Section::make('Note')
                    ->columnSpanFull()
                    ->schema([
                        MarkdownEditor::make('note')
                            ->hiddenLabel()
                            ->maxLength(1000)
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('invoices')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
