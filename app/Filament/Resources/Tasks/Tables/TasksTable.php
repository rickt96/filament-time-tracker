<?php

namespace App\Filament\Resources\Tasks\Tables;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Filament\Support\TaskDetailsAction;
use App\Models\Task;
use App\Support\TagOptions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['workPackage.project.client', 'assignee']))
            // Ordina per priorità di business (Alta > Media > Bassa > Nice-to-have),
            // stesso ranking usato da TasksKanbanBoard::cardOrder(), non l'ordine
            // alfabetico del valore enum sottostante.
            ->defaultSort('priority')
            ->groups([
                Group::make('status')
                    ->label('Stato')
                    ->collapsible(),
                Group::make('priority')
                    ->label('Priorità')
                    ->collapsible(),
                Group::make('workPackage.project.name')
                    ->label('Progetto')
                    ->collapsible(),
            ])
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
                TextColumn::make('workPackage.project.client.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->sortable(),
                TextColumn::make('priority')
                    ->label('Priorità')
                    ->badge()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                        'CASE priority '
                        .collect(TaskPriority::cases())
                            ->map(fn (TaskPriority $case, int $rank): string => "WHEN '{$case->value}' THEN {$rank}")
                            ->implode(' ')
                        .' END '.($direction === 'desc' ? 'desc' : 'asc'),
                    )),
                TextColumn::make('expire')
                    ->label('Scadenza')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->placeholder('—')
                    ->color(fn (Task $record): ?string => $record->expire?->isPast()
                        && ! in_array($record->status, [TaskStatus::Done, TaskStatus::Cancelled], true)
                        ? 'danger'
                        : null),
                TextColumn::make('assignee.name')
                    ->label('Assegnatario')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('tags')
                    ->label('Tag')
                    ->badge()
                    ->toggleable(),
                IconColumn::make('url')
                    ->label('Link')
                    ->icon(fn (?string $state): ?string => filled($state) ? 'heroicon-o-arrow-top-right-on-square' : null)
                    ->color('primary')
                    ->url(fn (Task $record): ?string => $record->url, shouldOpenInNewTab: true)
                    ->toggleable(),
                TextColumn::make('external_id')
                    ->label('ID esterno')
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('description')
                    ->label('Descrizione')
                    ->limit(60)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options(TaskStatus::class),
                SelectFilter::make('priority')
                    ->label('Priorità')
                    ->options(TaskPriority::class),
                SelectFilter::make('project')
                    ->label('Progetto')
                    ->relationship(
                        name: 'workPackage.project',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('workspace_id', Filament::getTenant()?->getKey()),
                    ),
                SelectFilter::make('workPackage')
                    ->label('Work Package')
                    ->relationship(
                        name: 'workPackage',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->whereHas(
                            'project',
                            fn (Builder $query) => $query->where('workspace_id', Filament::getTenant()?->getKey()),
                        ),
                    ),
                SelectFilter::make('assignee')
                    ->label('Assegnatario')
                    ->relationship('assignee', 'name'),
                SelectFilter::make('tags')
                    ->label('Tag')
                    ->multiple()
                    ->options(fn (): array => TagOptions::from(
                        Task::query()->whereHas(
                            'workPackage.project',
                            fn (Builder $query) => $query->where('workspace_id', Filament::getTenant()?->getKey()),
                        ),
                    ))
                    ->query(function (Builder $query, array $data): Builder {
                        $values = $data['values'] ?? [];

                        if (blank($values)) {
                            return $query;
                        }

                        return $query->where(function (Builder $query) use ($values): void {
                            foreach ($values as $value) {
                                $query->orWhereJsonContains('tags', $value);
                            }
                        });
                    }),
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
