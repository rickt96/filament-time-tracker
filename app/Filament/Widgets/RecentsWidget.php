<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ResolvesRecordLink;
use App\Models\PageVisit;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Auth;

class RecentsWidget extends TableWidget
{
    use ResolvesRecordLink;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recenti')
            ->query(
                PageVisit::query()
                    ->where('user_id', Auth::id())
                    ->with('visitable')
                    ->orderByDesc('last_opened_at')
            )
            ->columns([
                TextColumn::make('visitable_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (PageVisit $record): string => $this->typeLabelFor(
                        $record->visitable,
                        (string) $record->visitable_type,
                    )),
                TextColumn::make('display_title')
                    ->label('Nome')
                    ->state(fn (PageVisit $record): string => $this->titleFor($record->visitable)),
                TextColumn::make('last_opened_at')
                    ->label('Aperto')
                    ->since(),
            ])
            ->recordUrl(fn (PageVisit $record): ?string => $this->linkFor($record->visitable))
            ->emptyStateHeading('Nessuna risorsa aperta di recente')
            ->paginated([10, 25]);
    }
}
