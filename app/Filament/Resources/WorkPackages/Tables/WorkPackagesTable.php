<?php

namespace App\Filament\Resources\WorkPackages\Tables;

use App\Enums\WorkPackageStatus;
use App\Filament\Support\WorkPackageBudgetColumn;
use App\Models\WorkPackage;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class WorkPackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('project.name')
                    ->label('Progetto')
                    ->searchable()
                    ->sortable()
                    ->color(fn($record) => Color::hex($record->project?->color))
                    ->badge(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->sortable(),
                TextColumn::make('hourly_rate')
                    ->label('Tariffa oraria')
                    ->getStateUsing(fn (WorkPackage $record): ?string => $record->effectiveHourlyRate())
                    ->money('EUR')
                    ->sortable(),
                ...WorkPackageBudgetColumn::make(),
                TextColumn::make('tasks_count')
                    ->label('Task')
                    ->counts('tasks')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options(WorkPackageStatus::class),
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
