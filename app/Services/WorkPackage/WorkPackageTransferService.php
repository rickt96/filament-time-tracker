<?php

namespace App\Services\WorkPackage;

use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\WorkPackage;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Moves a single Work Package to a different Project, taking its Time
 * Entries along — mirrors the project_id re-parenting done in bulk by
 * ProjectToWorkPackageTransferService, but for one Work Package at a time
 * rather than a whole source Project (Tasks stay attached automatically,
 * since they belong to the Work Package, not to the Project directly).
 */
class WorkPackageTransferService
{
    public function transfer(WorkPackage $workPackage, Project $targetProject): int
    {
        if ($workPackage->project_id === $targetProject->id) {
            throw new InvalidArgumentException('Il Work Package è già su questo progetto.');
        }

        if ($workPackage->project->workspace_id !== $targetProject->workspace_id) {
            throw new InvalidArgumentException('Impossibile trasferire un Work Package su un progetto di un altro workspace.');
        }

        return DB::transaction(function () use ($workPackage, $targetProject): int {
            $workPackage->update(['project_id' => $targetProject->id]);

            // withTrashed(): a soft-deleted Time Entry under this Work
            // Package should follow it too, so a later restore doesn't leave
            // it pointing at a Project it no longer belongs to.
            return TimeEntry::withTrashed()
                ->where('work_package_id', $workPackage->id)
                ->update(['project_id' => $targetProject->id]);
        });
    }
}
