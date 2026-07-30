<?php

namespace App\Filament\Resources\WorkPackages\RelationManagers;

use App\Filament\Resources\Tasks\Schemas\SimpleTaskForm;
use App\Filament\Support\TaskDetailsAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    public function form(Schema $schema): Schema
    {
        return SimpleTaskForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->action(TaskDetailsAction::make()),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge(),
                TextColumn::make('priority')
                    ->label('Priorità')
                    ->badge(),
                TextColumn::make('expire')
                    ->label('Scadenza')
                    ->dateTime()
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('assignee.name')
                    ->label('Assegnatario'),
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
