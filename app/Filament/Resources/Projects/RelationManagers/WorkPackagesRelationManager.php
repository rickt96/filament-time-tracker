<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\WorkPackageStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkPackagesRelationManager extends RelationManager
{
    protected static string $relationship = 'workPackages';

    protected static ?string $title = 'Work Package';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),
                Select::make('status')
                    ->label('Stato')
                    ->options(WorkPackageStatus::class)
                    ->default(WorkPackageStatus::Planned)
                    ->required(),
                TextInput::make('budget_hours')
                    ->label('Budget ore')
                    ->numeric(),
                TextInput::make('hourly_rate')
                    ->label('Tariffa oraria')
                    ->numeric()
                    ->prefix('€')
                    ->helperText('Se lasciata vuota, viene usata la tariffa del progetto.'),
                TextInput::make('sort_order')
                    ->label('Ordinamento')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge(),
                TextColumn::make('budget_hours')
                    ->label('Budget ore')
                    ->numeric(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
