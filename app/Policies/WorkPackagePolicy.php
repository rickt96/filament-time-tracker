<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkPackage;
use App\Policies\Concerns\ChecksWorkspaceRole;

class WorkPackagePolicy
{
    use ChecksWorkspaceRole;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WorkPackage $workPackage): bool
    {
        return $user->roleIn($workPackage->project->workspace) !== null;
    }

    public function create(User $user): bool
    {
        return $this->currentWorkspaceRole($user)?->canManageWorkspace() ?? false;
    }

    public function update(User $user, WorkPackage $workPackage): bool
    {
        return $user->roleIn($workPackage->project->workspace)?->canManageWorkspace() ?? false;
    }

    public function delete(User $user, WorkPackage $workPackage): bool
    {
        return $user->roleIn($workPackage->project->workspace)?->canManageWorkspace() ?? false;
    }
}
