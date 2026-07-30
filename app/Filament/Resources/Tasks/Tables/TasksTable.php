<?php

namespace App\Filament\Resources\Tasks\Tables;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Filament\Support\TaskDetailsAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class TasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('workPackage.project'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->action(TaskDetailsAction::make()),
                TextColumn::make('workPackage.name')
                    ->label('Work Package')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->workPackage?->project?->name, 'above'),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->sortable(),
                TextColumn::make('priority')
                    ->label('Priorità')
                    ->badge()
                    ->sortable(),
                TextColumn::make('expire')
                    ->label('Scadenza')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('assignee.name')
                    ->label('Assegnatario')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('external_id')
                    ->label('ID esterno')
                    ->toggleable()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options(TaskStatus::class),
                SelectFilter::make('priority')
                    ->label('Priorità')
                    ->options(TaskPriority::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
