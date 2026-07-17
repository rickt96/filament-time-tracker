<?php

namespace App\Services\Budget;

readonly class BudgetSnapshot
{
    public function __construct(
        public ?float $budgetHours,
        public float $consumedHours,
        public ?float $remainingHours,
        public ?float $utilizationPercentage,
        public ?string $hourlyRate,
        public string $totalCost,
        public string $totalRevenue,
        public ?string $averageRate,
        public ?string $economicBudget,
        public ?string $economicBudgetRemaining,
    ) {}

    /**
     * Which configured threshold (app.timetracker.budget_thresholds) the
     * current utilization has reached, or null if under the first one.
     */
    public function reachedThreshold(): ?int
    {
        if ($this->utilizationPercentage === null) {
            return null;
        }

        $reached = null;

        /** @var array<int, int> $thresholds */
        $thresholds = config('timetracker.budget_thresholds', [80, 90, 100]);

        foreach ($thresholds as $threshold) {
            if ($this->utilizationPercentage >= $threshold) {
                $reached = $threshold;
            }
        }

        return $reached;
    }

    /**
     * A Filament color name driven by the reached threshold, for badges/indicators.
     */
    public function statusColor(): string
    {
        $reached = $this->reachedThreshold();

        if ($reached === null) {
            return 'success';
        }

        /** @var array<int, int> $thresholds */
        $thresholds = config('timetracker.budget_thresholds', [80, 90, 100]);

        $highestThreshold = empty($thresholds) ? 100 : max($thresholds);

        return $reached >= $highestThreshold ? 'danger' : 'warning';
    }
}
