<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\TimeEntryStatus;
use App\Models\TimeEntry;
use App\Support\DurationFormatter;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only view of every Time Entry logged against this Project, across all
 * its Work Packages/Tasks. Full time entry management (creation, editing,
 * duplication, sync) stays on TimeEntryResource, which already owns the
 * duration-entry-mode logic this would otherwise have to duplicate.
 */
class TimeEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'timeEntries';

    protected static ?string $title = 'Time entries';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->defaultSort('started_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['workPackage', 'task', 'user']))
            ->columns([
                TextColumn::make('date')
                    ->label('Data')
                    ->date()
                    ->sortable(),
                TextColumn::make('workPackage.name')
                    ->label('Work Package')
                    ->placeholder('—'),
                TextColumn::make('task.name')
                    ->label('Task')
                    ->placeholder('—'),
                TextColumn::make('description')
                    ->label('Descrizione')
                    ->limit(60)
                    ->placeholder('—'),
                TextColumn::make('started_at')
                    ->label('Orario')
                    ->getStateUsing(fn (TimeEntry $record): string => $record->started_at->format('H:i').' - '.($record->ended_at?->format('H:i') ?? '…')),
                TextColumn::make('duration_seconds')
                    ->label('Durata')
                    ->formatStateUsing(fn (int $state): string => DurationFormatter::hoursMinutesSeconds($state))
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Utente'),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge(),
                TextColumn::make('total_amount')
                    ->label('Importo')
                    ->money('EUR')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options(TimeEntryStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->paginationPageOptions([50, 100, 250, 'all']);
    }
}
