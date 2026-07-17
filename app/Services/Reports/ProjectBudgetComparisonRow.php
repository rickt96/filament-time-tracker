<?php

namespace App\Services\Reports;

readonly class ProjectBudgetComparisonRow
{
    public function __construct(
        public int $projectId,
        public string $projectName,
        public ?float $budgetHours,
        public float $consumedHours,
        public ?float $utilizationPercentage,
    ) {}
}
