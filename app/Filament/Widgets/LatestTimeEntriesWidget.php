<?php

namespace App\Filament\Widgets;

use App\Models\TimeEntry;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class LatestTimeEntriesWidget extends TableWidget
{
    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = Auth::user();

        return $table
            ->query(fn (): Builder => TimeEntry::query()
                ->where('user_id', $user->id)
                ->whereHas('project', fn (Builder $query) => $query->where('workspace_id', Filament::getTenant()?->getKey()))
                ->latest('started_at')
                ->limit(5))
            ->paginated(false)
            ->columns([
                TextColumn::make('date')
                    ->label('Data')
                    ->date(),
                TextColumn::make('project.name')
                    ->label('Progetto'),
                TextColumn::make('description')
                    ->label('Descrizione')
                    ->limit(40)
                    ->placeholder('—'),
                TextColumn::make('duration_seconds')
                    ->label('Durata')
                    ->formatStateUsing(fn (int $state): string => sprintf('%d:%02d', intdiv($state, 3600), intdiv($state % 3600, 60))),
            ]);
    }
}
