<?php

namespace App\Filament\Resources\Invoices\RelationManagers;

use App\Models\TimeEntry;
use App\Support\DurationFormatter;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only view of the Time Entries billed on this Invoice — attaching
 * happens the other way round, from TimeEntriesManageTable's "Aggiungi a
 * fattura" bulk action, which also handles picking/creating the Invoice.
 * Detach is kept here to undo an accidental attachment.
 */
class TimeEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'timeEntries';

    protected static ?string $title = 'Time entries';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->defaultSort('date', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['project', 'task']))
            ->columns([
                TextColumn::make('date')
                    ->label('Data')
                    ->date()
                    ->sortable(),
                TextColumn::make('project.name')
                    ->label('Progetto')
                    ->color(fn (TimeEntry $record) => $record->project?->color ? Color::hex($record->project->color) : 'gray')
                    ->badge(),
                TextColumn::make('task.name')
                    ->label('Task')
                    ->placeholder('—'),
                TextColumn::make('description')
                    ->label('Descrizione')
                    ->limit(60)
                    ->placeholder('—'),
                TextColumn::make('duration_seconds')
                    ->label('Durata')
                    ->formatStateUsing(fn (int $state): string => DurationFormatter::hoursMinutesSeconds($state))
                    ->summarize(
                        Sum::make()
                            ->hiddenLabel()
                            ->formatStateUsing(fn (int $state): string => DurationFormatter::hoursMinutesSeconds($state)),
                    ),
                TextColumn::make('total_amount')
                    ->label('Importo')
                    ->money('EUR')
                    ->summarize(
                        Sum::make()
                            ->money('EUR')
                            ->hiddenLabel(),
                    ),
            ])
            ->recordActions([
                DetachAction::make(),
            ])
            ->toolbarActions([
                DetachBulkAction::make(),
            ]);
    }
}
