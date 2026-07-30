<?php

namespace App\Services\Project;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\WorkPackage;
use App\Services\Clockify\ClockifyImportService;
use Closure;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Consolidates a set of "source" Projects — historically used as a stand-in
 * for Work Packages, one Project per year/scope — into Work Packages of a
 * single "master" Project. Each source Project's existing Work Package(s)
 * are re-parented in place (never recreated), so their Tasks stay attached
 * with no change; only project_id moves, on both the Work Package and its
 * Time Entries. The source Project itself is archived, not deleted, so it
 * remains resolvable for anything still pointing at its id.
 */
class ProjectToWorkPackageTransferService
{
    /**
     * @param  array<int, int>  $sourceProjectIds
     */
    public function transfer(int $masterProjectId, array $sourceProjectIds, bool $dryRun, Closure $onProgress): ProjectToWorkPackageTransferSummary
    {
        $sourceProjectIds = array_values(array_unique(array_diff($sourceProjectIds, [$masterProjectId])));

        if ($sourceProjectIds === []) {
            throw new InvalidArgumentException('Nessun progetto sorgente valido da trasferire (lista vuota o coincidente col master).');
        }

        $master = Project::find($masterProjectId);

        if (! $master) {
            throw new InvalidArgumentException("Progetto master [{$masterProjectId}] non trovato.");
        }

        $sourceProjects = Project::whereIn('id', $sourceProjectIds)->get();

        $missingIds = array_diff($sourceProjectIds, $sourceProjects->pluck('id')->all());

        if ($missingIds !== []) {
            throw new InvalidArgumentException('Progetti sorgente non trovati: '.implode(', ', $missingIds));
        }

        // Moving Work Packages/Time Entries across a Workspace boundary would
        // cross tenant scoping used throughout Filament — refuse outright
        // rather than silently doing a much bigger migration than intended.
        $mismatchedWorkspace = $sourceProjects->filter(fn (Project $project): bool => $project->workspace_id !== $master->workspace_id);

        if ($mismatchedWorkspace->isNotEmpty()) {
            $names = $mismatchedWorkspace->map(fn (Project $project): string => "[{$project->id}] {$project->name}")->implode(', ');

            throw new InvalidArgumentException("I seguenti progetti appartengono a un workspace diverso dal master e non possono essere trasferiti: {$names}.");
        }

        $summary = new ProjectToWorkPackageTransferSummary;

        foreach ($sourceProjects as $sourceProject) {
            if ($sourceProject->client_id !== $master->client_id) {
                $summary->warn("Progetto [{$sourceProject->id}] {$sourceProject->name}: cliente diverso dal master (client_id {$sourceProject->client_id} vs {$master->client_id}).");
            }
        }

        $onProgress("Master: [{$master->id}] {$master->name}");
        $onProgress('Progetti sorgente: '.$sourceProjects->pluck('name')->implode(', '));

        DB::beginTransaction();

        try {
            foreach ($sourceProjects as $sourceProject) {
                $this->transferProject($master, $sourceProject, $summary, $onProgress);
            }
        } catch (\Throwable $exception) {
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

    private function transferProject(Project $master, Project $sourceProject, ProjectToWorkPackageTransferSummary $summary, Closure $onProgress): void
    {
        $workPackages = WorkPackage::where('project_id', $sourceProject->id)->get();

        if ($workPackages->count() === 1) {
            // The common case: this source Project was really standing in for
            // a single Work Package. Fold the Project's own name/budget/rate
            // into it, but only where the Work Package hasn't already been
            // customized away from the Clockify-import default.
            $workPackage = $workPackages->first();

            if ($workPackage->name === ClockifyImportService::DEFAULT_WORK_PACKAGE_NAME) {
                $workPackage->name = $sourceProject->name;
            }

            $workPackage->budget_hours ??= $sourceProject->budget_hours;
            $workPackage->hourly_rate ??= $sourceProject->hourly_rate;

            $workPackage->save();
        } elseif ($workPackages->count() > 1) {
            $summary->warn("Progetto [{$sourceProject->id}] {$sourceProject->name}: {$workPackages->count()} work package trovati — nome/budget/tariffa non copiati automaticamente, da rivedere manualmente.");
        } else {
            $summary->warn("Progetto [{$sourceProject->id}] {$sourceProject->name}: nessun work package trovato, nulla da spostare oltre alle time entry.");
        }

        $movedWorkPackages = WorkPackage::where('project_id', $sourceProject->id)->update(['project_id' => $master->id]);

        // withTrashed(): a soft-deleted Time Entry under this source Project
        // should follow it too, so a later restore doesn't leave it pointing
        // at an archived Project no longer reachable from the master.
        $movedTimeEntries = TimeEntry::withTrashed()->where('project_id', $sourceProject->id)->update(['project_id' => $master->id]);

        $sourceProject->update(['status' => ProjectStatus::Archived]);

        $summary->projectsTransferred++;
        $summary->workPackagesMoved += $movedWorkPackages;
        $summary->timeEntriesMoved += $movedTimeEntries;

        $onProgress("[{$sourceProject->id}] {$sourceProject->name}: {$movedWorkPackages} work package, {$movedTimeEntries} time entry spostate.");
    }
}
