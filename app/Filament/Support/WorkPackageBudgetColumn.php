<?php

namespace App\Filament\Support;

use App\Models\WorkPackage;
use App\Services\Budget\BudgetUtilizationService;
use Devletes\FilamentProgressBar\Tables\Columns\ProgressBarColumn;
use Filament\Tables\Columns\TextColumn;

/**
 * The Work Package logged-hours column, budget progress bar and utilization
 * badge, shared between WorkPackagesTable and Project's
 * WorkPackagesRelationManager so both render them identically.
 *
 * The underlying progress bar package always renders a (possibly empty) bar
 * — Filament table columns can't be hidden per row, only per column — so for
 * a Work Package with no budget set, the numeric labels are hidden instead,
 * leaving a flat, textless track rather than a misleading "0%" reading.
 *
 * The bar's own percentage text is always hidden: the vendor package hard-
 * clamps it to 100 (Devletes\FilamentProgressBar\Support\ProgressBarResolver
 * ::clampPercentage(), with no override hook), which would misrepresent a
 * Work Package that ran over budget as merely "100%". The dedicated
 * "budget_percentage" badge below uses BudgetSnapshot::utilizationPercentage
 * instead, which is never clamped and can read e.g. "120%".
 */
class WorkPackageBudgetColumn
{
    /**
     * @return array{0: TextColumn, 1: TextColumn, 2: ProgressBarColumn}
     */
    public static function make(): array
    {
        /** @var array<int, int> $thresholds */
        $thresholds = config('timetracker.budget_thresholds', [80, 90, 100]);
        $warningThreshold = $thresholds[0] ?? 80;
        $dangerThreshold = $thresholds[count($thresholds) - 1] ?? 100;

        return [
            TextColumn::make('logged_hours')
                ->label('Ore registrate')
                ->getStateUsing(fn (WorkPackage $record): float => app(BudgetUtilizationService::class)
                    ->forWorkPackage($record)
                    ->consumedHours)
                ->numeric(decimalPlaces: 2)
                ->suffix('h'),

            ProgressBarColumn::make('budget_progress')
                ->label('Budget')
                ->getStateUsing(fn (WorkPackage $record): float => app(BudgetUtilizationService::class)
                    ->forWorkPackage($record)
                    ->consumedHours)
                ->maxValue(fn (WorkPackage $record): ?float => filled($record->budget_hours) ? (float) $record->budget_hours : null)
                ->warningThreshold($warningThreshold)
                ->dangerThreshold($dangerThreshold)
                ->hidePercentage()
                ->hideProgressValue(fn (WorkPackage $record): bool => blank($record->budget_hours)),

            TextColumn::make('budget_percentage')
                ->label('Utilizzo')
                ->getStateUsing(function (WorkPackage $record): ?string {
                    $percentage = app(BudgetUtilizationService::class)->forWorkPackage($record)->utilizationPercentage;

                    return $percentage !== null ? number_format($percentage, 0).'%' : null;
                })
                ->badge()
                ->color(fn (WorkPackage $record): string => app(BudgetUtilizationService::class)
                    ->forWorkPackage($record)
                    ->statusColor())
                ->placeholder('—'),
        ];
    }
}
