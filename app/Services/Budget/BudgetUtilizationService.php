<?php

namespace App\Services\Budget;

use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\WorkPackage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BudgetUtilizationService
{
    public function forProject(Project $project): BudgetSnapshot
    {
        return $this->snapshot(
            $project->budget_hours,
            $project->hourly_rate,
            TimeEntry::query()->where('project_id', $project->id),
        );
    }

    /**
     * Batched version of forProject() — one aggregate query covering every
     * given project instead of one query per project, keyed by project id.
     *
     * @param  Collection<int, Project>  $projects
     * @return Collection<int, BudgetSnapshot> keyed by project id
     */
    public function forProjects(Collection $projects): Collection
    {
        $projectIds = $projects->pluck('id');

        $totals = TimeEntry::query()
            ->whereIn('project_id', $projectIds)
            ->toBase()
            ->select('project_id')
            ->selectRaw('SUM(duration_seconds) as total_seconds, SUM(total_amount) as total_amount')
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');

        return $projects->mapWithKeys(function (Project $project) use ($totals) {
            $row = $totals->get($project->id);

            return [
                $project->id => $this->buildSnapshot(
                    $project->budget_hours,
                    $project->hourly_rate,
                    (int) ($row->total_seconds ?? 0),
                    (string) ($row->total_amount ?? '0'),
                ),
            ];
        });
    }

    public function forWorkPackage(WorkPackage $workPackage): BudgetSnapshot
    {
        return $this->snapshot(
            $workPackage->budget_hours,
            $workPackage->effectiveHourlyRate(),
            TimeEntry::query()->whereHas('task', fn (Builder $query) => $query->where('work_package_id', $workPackage->id)),
        );
    }

    /**
     * @param  Builder<TimeEntry>  $query
     */
    private function snapshot(?string $budgetHours, ?string $hourlyRate, Builder $query): BudgetSnapshot
    {
        $totals = $query
            ->toBase()
            ->selectRaw('SUM(duration_seconds) as total_seconds, SUM(total_amount) as total_amount')
            ->first();

        return $this->buildSnapshot(
            $budgetHours,
            $hourlyRate,
            (int) ($totals->total_seconds ?? 0),
            (string) ($totals->total_amount ?? '0'),
        );
    }

    private function buildSnapshot(?string $budgetHours, ?string $hourlyRate, int $consumedSeconds, string $rawTotalAmount): BudgetSnapshot
    {
        $consumedHours = round($consumedSeconds / 3600, 2);
        $totalAmount = number_format((float) $rawTotalAmount, 2, '.', '');

        $budgetHoursFloat = $budgetHours !== null ? (float) $budgetHours : null;

        $remainingHours = $budgetHoursFloat !== null
            ? round($budgetHoursFloat - $consumedHours, 2)
            : null;

        $utilization = ($budgetHoursFloat !== null && $budgetHoursFloat > 0)
            ? round(($consumedHours / $budgetHoursFloat) * 100, 2)
            : null;

        $averageRate = $consumedHours > 0
            ? number_format(((float) $totalAmount) / $consumedHours, 2, '.', '')
            : null;

        $economicBudget = ($budgetHoursFloat !== null && $hourlyRate !== null)
            ? number_format($budgetHoursFloat * (float) $hourlyRate, 2, '.', '')
            : null;

        $economicBudgetRemaining = $economicBudget !== null
            ? number_format(((float) $economicBudget) - ((float) $totalAmount), 2, '.', '')
            : null;

        return new BudgetSnapshot(
            budgetHours: $budgetHoursFloat,
            consumedHours: $consumedHours,
            remainingHours: $remainingHours,
            utilizationPercentage: $utilization,
            hourlyRate: $hourlyRate,
            totalCost: $totalAmount,
            totalRevenue: $totalAmount,
            averageRate: $averageRate,
            economicBudget: $economicBudget,
            economicBudgetRemaining: $economicBudgetRemaining,
        );
    }
}
