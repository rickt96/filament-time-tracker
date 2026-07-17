<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
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
            ->columns([
                /* ColorColumn::make('color')
                    ->label(''), */
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn($record) => Color::hex($record->color)),
                TextColumn::make('client.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->sortable(),
                TextColumn::make('budget_hours')
                    ->label('Budget ore')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('hourly_rate')
                    ->label('Tariffa')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('members_count')
                    ->label('Membri')
                    ->counts('members')
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
                    ->icon(fn (Project $record): string => static::isFavorite($record) ? 'heroicon-s-star' : 'heroicon-o-star')
                    ->color('warning')
                    ->action(function (Project $record): void {
                        /** @var User $user */
                        $user = Auth::user();

                        static::isFavorite($record)
                            ? $user->favoriteProjects()->detach($record)
                            : $user->favoriteProjects()->attach($record);
                    }),
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

    protected static function isFavorite(Project $record): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->favoriteProjects()->whereKey($record->id)->exists();
    }
}
