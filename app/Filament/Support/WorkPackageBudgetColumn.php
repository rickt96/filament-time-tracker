<?php

namespace App\Filament\Support;

use App\Models\WorkPackage;
use App\Services\Budget\BudgetUtilizationService;
use Devletes\FilamentProgressBar\Tables\Columns\ProgressBarColumn;

/**
 * The Work Package budget progress bar, shared between WorkPackagesTable and
 * Project's WorkPackagesRelationManager so both render it identically.
 *
 * The underlying package always renders a (possibly empty) bar — Filament
 * table columns can't be hidden per row, only per column — so for a Work
 * Package with no budget set, the numeric labels are hidden instead, leaving
 * a flat, textless track rather than a misleading "0%" reading.
 */
class WorkPackageBudgetColumn
{
    /**
     * @return array{0: ProgressBarColumn}
     */
    public static function make(): array
    {
        /** @var array<int, int> $thresholds */
        $thresholds = config('timetracker.budget_thresholds', [80, 90, 100]);
        $warningThreshold = $thresholds[0] ?? 80;
        $dangerThreshold = $thresholds[count($thresholds) - 1] ?? 100;

        return [
            ProgressBarColumn::make('budget_progress')
                ->label('Budget')
                ->getStateUsing(fn (WorkPackage $record): float => app(BudgetUtilizationService::class)
                    ->forWorkPackage($record)
                    ->consumedHours)
                ->maxValue(fn (WorkPackage $record): ?float => filled($record->budget_hours) ? (float) $record->budget_hours : null)
                ->warningThreshold($warningThreshold)
                ->dangerThreshold($dangerThreshold)
                ->hidePercentage(fn (WorkPackage $record): bool => blank($record->budget_hours))
                ->hideProgressValue(fn (WorkPackage $record): bool => blank($record->budget_hours)),
        ];
    }
}
