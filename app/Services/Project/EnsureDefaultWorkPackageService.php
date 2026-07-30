<?php

namespace App\Services\Project;

use App\Enums\WorkPackageStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\WorkPackage;
use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Every Project is expected to organize its work under at least one Work
 * Package, but some were created (or imported) before that was true and
 * still have none. For each such Project, this creates a single Work
 * Package named after the Project — inheriting its description, budget
 * hours and hourly rate — and folds in whatever was already attached
 * directly to the Project instead of to a Work Package:
 * - Time Entries logged straight against the Project (work_package_id null).
 * - Tasks/Time Entries left behind under a Work Package of this same
 *   Project that was later soft-deleted (a Project only counts as
 *   "without a Work Package" when none of its non-trashed ones exist, so
 *   a trashed one can still be sitting there with real data attached).
 */
class EnsureDefaultWorkPackageService
{
    public function run(bool $dryRun, Closure $onProgress): EnsureDefaultWorkPackageSummary
    {
        $summary = new EnsureDefaultWorkPackageSummary;

        $projects = Project::query()->whereDoesntHave('workPackages')->get();

        if ($projects->isEmpty()) {
            $onProgress('Nessun progetto senza Work Package trovato.');

            return $summary;
        }

        DB::beginTransaction();

        try {
            foreach ($projects as $project) {
                $this->processProject($project, $summary, $onProgress);
            }
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        if ($dryRun) {
            DB::rollBack();
        } else {
            DB::commit();
        }

        return $summary;
    }

    private function processProject(Project $project, EnsureDefaultWorkPackageSummary $summary, Closure $onProgress): void
    {
        $workPackage = WorkPackage::create([
            'project_id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'budget_hours' => $project->budget_hours,
            'hourly_rate' => $project->hourly_rate,
            'status' => WorkPackageStatus::InProgress,
            'sort_order' => 0,
        ]);

        $summary->workPackagesCreated++;

        // withTrashed(): a soft-deleted Time Entry logged directly against
        // the Project should be re-parented too, so a later restore doesn't
        // leave it pointing at no Work Package at all.
        $movedTimeEntries = TimeEntry::withTrashed()
            ->where('project_id', $project->id)
            ->whereNull('work_package_id')
            ->update(['work_package_id' => $workPackage->id]);

        $trashedWorkPackageIds = WorkPackage::onlyTrashed()
            ->where('project_id', $project->id)
            ->pluck('id');

        $movedTasks = 0;

        if ($trashedWorkPackageIds->isNotEmpty()) {
            $movedTasks = Task::withTrashed()
                ->whereIn('work_package_id', $trashedWorkPackageIds)
                ->update(['work_package_id' => $workPackage->id]);

            $movedTimeEntries += TimeEntry::withTrashed()
                ->whereIn('work_package_id', $trashedWorkPackageIds)
                ->update(['work_package_id' => $workPackage->id]);
        }

        $summary->timeEntriesMoved += $movedTimeEntries;
        $summary->tasksMoved += $movedTasks;

        $onProgress("[{$project->id}] {$project->name}: Work Package creato, {$movedTimeEntries} time entry e {$movedTasks} task migrati.");
    }
}
