<?php

namespace App\Filament\Resources\TimeEntries\Tables;

use App\Actions\Sync\SyncTimeEntryAction;
use App\Actions\TimeEntry\DuplicateTimeEntryAction;
use App\Actions\TimeEntry\UpdateTimeEntryAction;
use App\Enums\TimeEntrySyncStatus;
use App\Models\TimeEntry;
use App\Support\DurationFormatter;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;

class TimeEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('started_at', 'desc')
            // No entry in ->groups(): this makes the grouping mandatory and
            // not user-togglable, since there's nothing else to switch to —
            // getTableGrouping() always falls back to this default.
            ->defaultGroup(
                Group::make('date')
                    ->label('')
                    ->date()
                    // Forced regardless of $direction: the grouping-direction
                    // toggle is meaningless with no alternative groups to pick
                    // from, but its default of 'asc' would otherwise leak in.
                    ->orderQueryUsing(fn (Builder $query): Builder => $query->orderBy('date', 'desc')),
            )
            // 'client' is a computed accessor (derived from project.client), not
            // an Eloquent relation, so Filament's automatic per-column eager
            // loading can't detect it — load it explicitly to avoid an N+1 on
            // every row alongside the other relations shown in this table.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['project.client', 'task', 'user', 'tags']))
            ->columns([
                TextInputColumn::make('description')
                    ->label('')
                    ->placeholder('Aggiungi descrizione')
                    ->tooltip(fn($state) => $state)
                    ->afterStateUpdated(function() {
                        Notification::make()
                            ->success()
                            ->title('Descrizione aggiornata')
                            ->send();
                    }),
                TextColumn::make('project.name')
                    ->label('')
                    ->getStateUsing(fn (TimeEntry $record): string => "{$record->project->name} ({$record->client->name})")
                    ->color(fn($record) => Color::hex($record->project?->color))
                    ->badge(),
                SelectColumn::make('task_id')
                    ->label('')
                    ->optionsRelationship(
                        name: 'task',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query, TimeEntry $record) => $query->whereHas(
                            'workPackage',
                            fn (Builder $query) => $query->where('project_id', $record->project_id),
                        ),
                    )
                    ->placeholder('—'),
                TextColumn::make('started_at')
                    ->label('')
                    ->getStateUsing(fn (TimeEntry $record): string => $record->started_at->format('H:i').' - '.($record->ended_at?->format('H:i') ?? '…')),
                TextColumn::make('duration_seconds')
                    ->label('')
                    ->formatStateUsing(fn (int $state): string => DurationFormatter::hoursMinutesSeconds($state) /* Number::format(($state / 3600), 2) */),
            ])
            ->filters([
                /* Filter::make('date_range')
                    ->label('Intervallo date')
                    ->schema([
                        DatePicker::make('from')->label('Dal'),
                        DatePicker::make('until')->label('Al'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '<=', $date));
                    }),
                SelectFilter::make('project')
                    ->label('Progetto')
                    ->relationship(
                        name: 'project',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('workspace_id', Filament::getTenant()?->getKey()),
                    ),
                SelectFilter::make('client')
                    ->label('Cliente')
                    ->relationship(
                        name: 'project.client',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('workspace_id', Filament::getTenant()?->getKey()),
                    ),
                SelectFilter::make('tags')
                    ->label('Tag')
                    ->relationship('tags', 'name'),
                SelectFilter::make('user')
                    ->label('Utente')
                    ->relationship('user', 'name'),
                SelectFilter::make('task')
                    ->label('Task')
                    ->relationship('task', 'name'),
                SelectFilter::make('sync_status')
                    ->label('Stato sincronizzazione')
                    ->options(TimeEntrySyncStatus::class),
                TrashedFilter::make(), */
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->modal()
                        ->mutateRecordDataUsing(function (array $data, TimeEntry $record): array {
                            $data['started_at'] = $record->started_at->format('H:i');
                            $data['ended_at'] = $record->ended_at?->format('H:i');

                            return $data;
                        })
                        ->using(fn (TimeEntry $record, array $data): TimeEntry => app(UpdateTimeEntryAction::class)
                            ->handle($record, $data)),
                    Action::make('duplicate')
                        ->label('Duplica')
                        ->icon('heroicon-o-document-duplicate')
                        ->action(function (TimeEntry $record) {
                            app(DuplicateTimeEntryAction::class)->handle($record);

                            Notification::make()
                                ->title('Time entry duplicato')
                                ->success()
                                ->send();
                        }),
                    Action::make('sync')
                        ->label('Sincronizza')
                        ->icon('heroicon-o-arrow-path')
                        ->visible(fn (TimeEntry $record): bool => filled($record->task?->import_clickup_id) && $record->project->client->sync_driver !== null)
                        ->action(function (TimeEntry $record) {
                            $synced = app(SyncTimeEntryAction::class)->handle($record);

                            $notification = Notification::make()->title(
                                $synced->sync_status === TimeEntrySyncStatus::Synced
                                    ? 'Time entry sincronizzato'
                                    : "Sincronizzazione fallita: {$synced->sync_error}",
                            );

                            $synced->sync_status === TimeEntrySyncStatus::Synced
                                ? $notification->success()
                                : $notification->danger();

                            $notification->send();
                        }),
                    DeleteAction::make()
                ])
                
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('sync')
                        ->label('Sincronizza selezionati')
                        ->icon('heroicon-o-arrow-path')
                        ->action(function (Collection $records) {
                            [$synced, $failed] = self::syncMany($records);

                            Notification::make()
                                ->title("Sincronizzazione completata: {$synced} riuscite, {$failed} fallite")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->paginated([50, 100, 200])
            ->defaultPaginationPageOption(50)
            ->paginationMode(PaginationMode::Default);
    }

    /**
     * @param  Collection<int, TimeEntry>  $records
     * @return array{0: int, 1: int} [synced count, failed count]
     */
    private static function syncMany(Collection $records): array
    {
        $synced = 0;
        $failed = 0;

        foreach ($records as $record) {
            $result = app(SyncTimeEntryAction::class)->handle($record);

            $result->sync_status === TimeEntrySyncStatus::Synced ? $synced++ : $failed++;
        }

        return [$synced, $failed];
    }
}
