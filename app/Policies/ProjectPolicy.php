<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Policies\Concerns\ChecksWorkspaceRole;

class ProjectPolicy
{
    use ChecksWorkspaceRole;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $user->roleIn($project->workspace) !== null;
    }

    public function create(User $user): bool
    {
        return $this->currentWorkspaceRole($user)?->canManageWorkspace() ?? false;
    }

    public function update(User $user, Project $project): bool
    {
        return $user->roleIn($project->workspace)?->canManageWorkspace() ?? false;
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->roleIn($project->workspace)?->canManageWorkspace() ?? false;
    }
}
