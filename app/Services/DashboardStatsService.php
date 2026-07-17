<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class DashboardStatsService
{
    public function hoursToday(User $user, Workspace $workspace): float
    {
        $today = Carbon::today();

        return $this->hoursForPeriod($user, $workspace, $today, $today);
    }

    public function hoursThisWeek(User $user, Workspace $workspace): float
    {
        return $this->hoursForPeriod($user, $workspace, Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek());
    }

    public function hoursThisMonth(User $user, Workspace $workspace): float
    {
        return $this->hoursForPeriod($user, $workspace, Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth());
    }

    public function hoursForPeriod(User $user, Workspace $workspace, Carbon $start, Carbon $end): float
    {
        $seconds = $this->scopedTimeEntries($user, $workspace)
            ->whereBetween('time_entries.date', [$start->toDateString(), $end->toDateString()])
            ->sum('time_entries.duration_seconds');

        return round($seconds / 3600, 2);
    }

    public function mostUsedProject(User $user, Workspace $workspace): ?Project
    {
        $projectId = $this->scopedTimeEntries($user, $workspace)
            ->select('time_entries.project_id')
            ->groupBy('time_entries.project_id')
            ->orderByRaw('SUM(time_entries.duration_seconds) DESC')
            ->value('time_entries.project_id');

        return $projectId ? Project::find((int) $projectId) : null;
    }

    public function mostUsedClient(User $user, Workspace $workspace): ?Client
    {
        $clientId = $this->scopedTimeEntries($user, $workspace)
            ->select('projects.client_id')
            ->groupBy('projects.client_id')
            ->orderByRaw('SUM(time_entries.duration_seconds) DESC')
            ->value('projects.client_id');

        return $clientId ? Client::find((int) $clientId) : null;
    }

    /**
     * @return Collection<int, TimeEntry>
     */
    public function latestEntries(User $user, Workspace $workspace, int $limit = 5): Collection
    {
        return $this->scopedTimeEntries($user, $workspace)
            ->select('time_entries.*')
            ->latest('time_entries.started_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<string, float> project name => hours, for the current month
     */
    public function hoursByProjectThisMonth(User $user, Workspace $workspace): array
    {
        return $this->scopedTimeEntries($user, $workspace)
            ->whereBetween('time_entries.date', [
                Carbon::now()->startOfMonth()->toDateString(),
                Carbon::now()->endOfMonth()->toDateString(),
            ])
            ->selectRaw('projects.name as project_name, SUM(time_entries.duration_seconds) as total_seconds')
            ->groupBy('projects.name')
            ->orderByDesc('total_seconds')
            ->pluck('total_seconds', 'project_name')
            ->map(fn ($seconds): float => round($seconds / 3600, 2))
            ->all();
    }

    /**
     * @return array<string, float> client name => hours, for the current month
     */
    public function hoursByClientThisMonth(User $user, Workspace $workspace): array
    {
        return $this->scopedTimeEntries($user, $workspace)
            ->join('clients', 'clients.id', '=', 'projects.client_id')
            ->whereBetween('time_entries.date', [
                Carbon::now()->startOfMonth()->toDateString(),
                Carbon::now()->endOfMonth()->toDateString(),
            ])
            ->selectRaw('clients.name as client_name, SUM(time_entries.duration_seconds) as total_seconds')
            ->groupBy('clients.name')
            ->orderByDesc('total_seconds')
            ->pluck('total_seconds', 'client_name')
            ->map(fn ($seconds): float => round($seconds / 3600, 2))
            ->all();
    }

    /**
     * @return Builder<TimeEntry>
     */
    private function scopedTimeEntries(User $user, Workspace $workspace): Builder
    {
        return TimeEntry::query()
            ->join('projects', 'projects.id', '=', 'time_entries.project_id')
            ->where('time_entries.user_id', $user->id)
            ->where('projects.workspace_id', $workspace->id);
    }
}
