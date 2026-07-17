<?php

namespace App\Filament\Resources\Clients\Tables;

use App\Models\Client;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ColorColumn::make('color')
                    ->label(''),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contact_name')
                    ->label('Referente')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('projects_count')
                    ->label('Progetti')
                    ->counts('projects')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Attivo'),
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
                    ->label(fn (Client $record): string => static::isFavorite($record) ? 'Rimuovi dai preferiti' : 'Aggiungi ai preferiti')
                    ->icon(fn (Client $record): string => static::isFavorite($record) ? 'heroicon-s-star' : 'heroicon-o-star')
                    ->color('warning')
                    ->action(function (Client $record): void {
                        /** @var User $user */
                        $user = Auth::user();

                        static::isFavorite($record)
                            ? $user->favoriteClients()->detach($record)
                            : $user->favoriteClients()->attach($record);
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

    protected static function isFavorite(Client $record): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->favoriteClients()->whereKey($record->id)->exists();
    }
}
