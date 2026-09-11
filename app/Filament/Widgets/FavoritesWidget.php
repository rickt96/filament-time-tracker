<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ResolvesRecordLink;
use App\Models\Favorite;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Auth;

class FavoritesWidget extends TableWidget
{
    use ResolvesRecordLink;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Preferiti')
            ->query(
                Favorite::query()
                    ->where('user_id', Auth::id())
                    ->with('favoritable')
                    ->orderByDesc('created_at')
            )
            ->columns([
                TextColumn::make('favoritable_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (Favorite $record): string => $this->typeLabelFor(
                        $record->favoritable,
                        (string) $record->favoritable_type,
                    )),
                TextColumn::make('display_title')
                    ->label('Nome')
                    ->state(fn (Favorite $record): string => $this->titleFor($record->favoritable)),
                TextColumn::make('created_at')
                    ->label('Aggiunto')
                    ->since(),
            ])
            ->recordUrl(fn (Favorite $record): ?string => $this->linkFor($record->favoritable))
            ->emptyStateHeading('Nessun preferito')
            ->paginated([10, 25]);
    }
}
