<?php

namespace App\Services\TimeEntry;

use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\WorkPackage;

/**
 * Assigns a Project's "orphan" Time Entries — logged directly against it
 * with no Work Package — to a Work Package. The target Work Package's own
 * Project is always the caller's responsibility to resolve and pass in
 * explicitly (not re-derived in here), so the Work Package/Project pairing
 * used to persist the Time Entries is never ambiguous: as an emergency
 * escape hatch that Work Package can belong to a Project other than the
 * source one, and the Time Entries' project_id is moved along with it,
 * kept consistent with the Work Package's actual Project.
 */
class AssignOrphanTimeEntriesService
{
    public function assign(Project $sourceProject, WorkPackage $workPackage): int
    {
        // withTrashed(): a soft-deleted orphan Time Entry should be assigned
        // too, so a later restore doesn't leave it with no Work Package.
        return TimeEntry::withTrashed()
            ->where('project_id', $sourceProject->id)
            ->whereNull('work_package_id')
            ->update([
                'work_package_id' => $workPackage->id,
                'project_id' => $workPackage->project_id,
            ]);
    }
}
