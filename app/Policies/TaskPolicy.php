<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Policies\Concerns\ChecksWorkspaceRole;

class TaskPolicy
{
    use ChecksWorkspaceRole;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Task $task): bool
    {
        return $user->roleIn($task->workPackage->project->workspace) !== null;
    }

    public function create(User $user): bool
    {
        return $this->currentWorkspaceRole($user)?->canManageWorkspace() ?? false;
    }

    public function update(User $user, Task $task): bool
    {
        return $user->roleIn($task->workPackage->project->workspace)?->canManageWorkspace() ?? false;
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->roleIn($task->workPackage->project->workspace)?->canManageWorkspace() ?? false;
    }
}
