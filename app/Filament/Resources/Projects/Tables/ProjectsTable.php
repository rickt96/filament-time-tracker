<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkPackage;
use App\Services\TimeEntry\AssignOrphanTimeEntriesService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['timeEntries', 'workPackages', 'invoices']))
            ->columns([
                /* ColorColumn::make('color')
                    ->label(''), */
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->color ? Color::hex($record->color) : 'gray'),
                TextColumn::make('client.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->sortable(),
                /* TextColumn::make('budget_hours')
                    ->label('Budget ore')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('hourly_rate')
                    ->label('Tariffa')
                    ->money('EUR')
                    ->sortable(), */
                TextColumn::make('work_packages_count')
                    ->label('Pacchetti')
                    ->sortable(),
                TextColumn::make('time_entries_count')
                    ->label('Time entries')
                    ->sortable(),
                TextColumn::make('invoices_count')
                    ->label('Invoices')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options(ProjectStatus::class)
                    ->default(ProjectStatus::Active),
                Filter::make('favorites_only')
                    ->label('Solo preferiti')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereHas(
                        'favoritedBy',
                        fn (Builder $query) => $query->whereKey(Auth::id()),
                    )),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('toggleFavorite')
                    ->label(fn (Project $record): string => static::isFavorite($record) ? 'Rimuovi dai preferiti' : 'Aggiungi ai preferiti')
                    ->hiddenLabel()
                    ->icon(fn (Project $record): string => static::isFavorite($record) ? 'heroicon-s-star' : 'heroicon-o-star')
                    ->color('warning')
                    ->action(function (Project $record): void {
                        /** @var User $user */
                        $user = Auth::user();

                        static::isFavorite($record)
                            ? $user->favoriteProjects()->detach($record)
                            : $user->favoriteProjects()->attach($record);
                    })
                    ->successNotificationTitle(
                        fn ($record) => static::isFavorite($record)
                                            ? "Progetto {$record->name} aggiunto ai preferiti"
                                            : "Progetto {$record->name} rimosso dai preferiti"
                    ),
                ActionGroup::make([
                    EditAction::make()
                        ->hiddenLabel(),
                    Action::make('assignOrphanTimeEntries')
                        ->label('Assegna time entry orfane')
                        ->icon('heroicon-o-link')
                        ->visible(fn (Project $record): bool => TimeEntry::query()
                            ->where('project_id', $record->id)
                            ->whereNull('work_package_id')
                            ->exists())
                        ->schema(fn (Project $record): array => [
                            Select::make('work_package_id')
                                ->label('Work Package di destinazione')
                                ->helperText('Per emergenza è possibile selezionare anche un Work Package di un altro progetto.')
                                ->options(fn (): array => WorkPackage::query()
                                    ->whereHas('project', fn (Builder $query) => $query->where('workspace_id', Filament::getTenant()?->getKey()))
                                    ->with('project')
                                    ->get()
                                    ->sortBy([['project.name', 'asc'], ['sort_order', 'asc']])
                                    ->mapWithKeys(fn (WorkPackage $workPackage): array => [
                                        $workPackage->id => "{$workPackage->project->name} — {$workPackage->name}",
                                    ])
                                    ->all())
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Project $record, array $data): void {
                            $workPackage = WorkPackage::with('project')->findOrFail((int) $data['work_package_id']);

                            $moved = app(AssignOrphanTimeEntriesService::class)->assign($record, $workPackage);

                            Notification::make()
                                ->title("{$moved} time entry assegnate a \"{$workPackage->name}\"")
                                ->success()
                                ->send();
                        }),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->persistFiltersInSession();
    }

    protected static function isFavorite(Project $record): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->favoriteProjects()->whereKey($record->id)->exists();
    }
}
