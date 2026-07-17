<?php

namespace App\Services\Reports;

use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\Workspace;
use App\Services\Budget\BudgetUtilizationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TimeReportService
{
    public function __construct(private readonly BudgetUtilizationService $budgetUtilization) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<TimeEntry>
     */
    public function query(Workspace $workspace, array $filters = []): Builder
    {
        return TimeEntry::query()
            ->join('projects', 'projects.id', '=', 'time_entries.project_id')
            ->where('projects.workspace_id', $workspace->id)
            ->when($filters['from'] ?? null, fn (Builder $query, $value) => $query->whereDate('time_entries.date', '>=', $value))
            ->when($filters['until'] ?? null, fn (Builder $query, $value) => $query->whereDate('time_entries.date', '<=', $value))
            ->when($filters['project_id'] ?? null, fn (Builder $query, $value) => $query->where('time_entries.project_id', $value))
            ->when($filters['client_id'] ?? null, fn (Builder $query, $value) => $query->where('projects.client_id', $value))
            ->when($filters['user_id'] ?? null, fn (Builder $query, $value) => $query->where('time_entries.user_id', $value))
            ->when($filters['task_id'] ?? null, fn (Builder $query, $value) => $query->where('time_entries.task_id', $value))
            ->when(
                $filters['tag_id'] ?? null,
                fn (Builder $query, $value) => $query->whereHas('tags', fn (Builder $query) => $query->where('tags.id', $value)),
            )
            ->when(
                $filters['work_package_id'] ?? null,
                fn (Builder $query, $value) => $query->whereHas('task', fn (Builder $query) => $query->where('work_package_id', $value)),
            );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function totalHours(Workspace $workspace, array $filters = []): float
    {
        $seconds = $this->query($workspace, $filters)->sum('time_entries.duration_seconds');

        return round($seconds / 3600, 2);
    }

    /**
     * The average hourly rate actually applied across the filtered entries
     * (total billed amount divided by total hours) — not a project/workspace
     * default, but what was really charged.
     *
     * @param  array<string, mixed>  $filters
     */
    public function averageRate(Workspace $workspace, array $filters = []): ?string
    {
        $totals = $this->query($workspace, $filters)
            ->toBase()
            ->selectRaw('SUM(time_entries.duration_seconds) as total_seconds, SUM(time_entries.total_amount) as total_amount')
            ->first();

        $seconds = (int) ($totals->total_seconds ?? 0);

        if ($seconds === 0) {
            return null;
        }

        $hours = $seconds / 3600;
        $amount = (float) ($totals->total_amount ?? 0);

        return number_format($amount / $hours, 2, '.', '');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{project_id: int, project_name: string, hours: float, amount: string}>
     */
    public function totalsByProject(Workspace $workspace, array $filters = []): Collection
    {
        return $this->query($workspace, $filters)
            ->selectRaw('projects.id as project_id, projects.name as project_name, SUM(time_entries.duration_seconds) as total_seconds, SUM(time_entries.total_amount) as total_amount')
            ->groupBy('projects.id', 'projects.name')
            ->orderByDesc('total_seconds')
            ->toBase()
            ->get()
            ->map(fn ($row) => [
                'project_id' => (int) $row->project_id,
                'project_name' => (string) $row->project_name,
                'hours' => round(((int) $row->total_seconds) / 3600, 2),
                'amount' => number_format((float) ($row->total_amount ?? 0), 2, '.', ''),
            ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{client_id: int, client_name: string, hours: float, amount: string}>
     */
    public function totalsByClient(Workspace $workspace, array $filters = []): Collection
    {
        return $this->query($workspace, $filters)
            ->join('clients', 'clients.id', '=', 'projects.client_id')
            ->selectRaw('clients.id as client_id, clients.name as client_name, SUM(time_entries.duration_seconds) as total_seconds, SUM(time_entries.total_amount) as total_amount')
            ->groupBy('clients.id', 'clients.name')
            ->orderByDesc('total_seconds')
            ->toBase()
            ->get()
            ->map(fn ($row) => [
                'client_id' => (int) $row->client_id,
                'client_name' => (string) $row->client_name,
                'hours' => round(((int) $row->total_seconds) / 3600, 2),
                'amount' => number_format((float) ($row->total_amount ?? 0), 2, '.', ''),
            ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{user_id: int, user_name: string, hours: float, amount: string}>
     */
    public function totalsByUser(Workspace $workspace, array $filters = []): Collection
    {
        return $this->query($workspace, $filters)
            ->join('users', 'users.id', '=', 'time_entries.user_id')
            ->selectRaw('users.id as user_id, users.name as user_name, SUM(time_entries.duration_seconds) as total_seconds, SUM(time_entries.total_amount) as total_amount')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_seconds')
            ->toBase()
            ->get()
            ->map(fn ($row) => [
                'user_id' => (int) $row->user_id,
                'user_name' => (string) $row->user_name,
                'hours' => round(((int) $row->total_seconds) / 3600, 2),
                'amount' => number_format((float) ($row->total_amount ?? 0), 2, '.', ''),
            ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{work_package_id: int, work_package_name: string, hours: float, amount: string}>
     */
    public function totalsByWorkPackage(Workspace $workspace, array $filters = []): Collection
    {
        return $this->query($workspace, $filters)
            ->join('tasks', 'tasks.id', '=', 'time_entries.task_id')
            ->join('work_packages', 'work_packages.id', '=', 'tasks.work_package_id')
            ->selectRaw('work_packages.id as work_package_id, work_packages.name as work_package_name, SUM(time_entries.duration_seconds) as total_seconds, SUM(time_entries.total_amount) as total_amount')
            ->groupBy('work_packages.id', 'work_packages.name')
            ->orderByDesc('total_seconds')
            ->toBase()
            ->get()
            ->map(fn ($row) => [
                'work_package_id' => (int) $row->work_package_id,
                'work_package_name' => (string) $row->work_package_name,
                'hours' => round(((int) $row->total_seconds) / 3600, 2),
                'amount' => number_format((float) ($row->total_amount ?? 0), 2, '.', ''),
            ]);
    }

    /**
     * Budget-planned-vs-consumed comparison for every project appearing in
     * the filtered results, reusing the same budget math as the Project
     * budget dashboard.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, ProjectBudgetComparisonRow>
     */
    public function budgetComparisonByProject(Workspace $workspace, array $filters = []): Collection
    {
        $projectIds = $this->query($workspace, $filters)
            ->toBase()
            ->select('time_entries.project_id')
            ->distinct()
            ->pluck('project_id');

        $projects = Project::query()->whereIn('id', $projectIds)->get();

        // One aggregate query for every project's all-time totals instead of
        // one query per project (BudgetUtilizationService::forProjects() batches it).
        $snapshots = $this->budgetUtilization->forProjects($projects);

        $rows = [];

        foreach ($projects as $project) {
            // forProjects() builds one snapshot per project in $projects, so
            // every id looked up here is guaranteed to be present.
            $snapshot = $snapshots->get($project->id);

            $rows[] = new ProjectBudgetComparisonRow(
                projectId: $project->id,
                projectName: $project->name,
                budgetHours: $snapshot->budgetHours,
                consumedHours: $snapshot->consumedHours,
                utilizationPercentage: $snapshot->utilizationPercentage,
            );
        }

        return collect($rows);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, float> date (Y-m-d) => hours
     */
    public function totalsByDay(Workspace $workspace, array $filters = []): array
    {
        return $this->query($workspace, $filters)
            ->selectRaw('time_entries.date as entry_date, SUM(time_entries.duration_seconds) as total_seconds')
            ->groupBy('time_entries.date')
            ->orderBy('time_entries.date')
            ->toBase()
            ->get()
            ->mapWithKeys(fn ($row) => [
                Carbon::parse((string) $row->entry_date)->toDateString() => round(((int) $row->total_seconds) / 3600, 2),
            ])
            ->all();
    }

    /**
     * Buckets the per-day totals into weeks in PHP rather than via a DB-specific
     * date-truncation function, to stay portable across SQLite/MySQL/Postgres.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, float> week start date (Y-m-d) => hours
     */
    public function totalsByWeek(Workspace $workspace, array $filters = []): array
    {
        return $this->bucketDailyTotals(
            $this->totalsByDay($workspace, $filters),
            fn (Carbon $date): string => $date->startOfWeek()->toDateString(),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, float> month (Y-m) => hours
     */
    public function totalsByMonth(Workspace $workspace, array $filters = []): array
    {
        return $this->bucketDailyTotals(
            $this->totalsByDay($workspace, $filters),
            fn (Carbon $date): string => $date->format('Y-m'),
        );
    }

    /**
     * @param  array<string, float>  $dailyTotals
     * @return array<string, float>
     */
    private function bucketDailyTotals(array $dailyTotals, \Closure $bucketKeyResolver): array
    {
        $buckets = [];

        foreach ($dailyTotals as $date => $hours) {
            $key = $bucketKeyResolver(Carbon::parse($date));
            $buckets[$key] = ($buckets[$key] ?? 0) + $hours;
        }

        ksort($buckets);

        return $buckets;
    }
}
