<?php

namespace App\Policies;

use App\Models\TimeEntry;
use App\Models\User;
use App\Policies\Concerns\ChecksWorkspaceRole;

class TimeEntryPolicy
{
    use ChecksWorkspaceRole;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TimeEntry $timeEntry): bool
    {
        return $user->id === $timeEntry->user_id || $this->canManage($user, $timeEntry);
    }

    public function create(User $user): bool
    {
        return $this->currentWorkspaceRole($user) !== null;
    }

    public function update(User $user, TimeEntry $timeEntry): bool
    {
        return $user->id === $timeEntry->user_id || $this->canManage($user, $timeEntry);
    }

    public function delete(User $user, TimeEntry $timeEntry): bool
    {
        return $user->id === $timeEntry->user_id || $this->canManage($user, $timeEntry);
    }

    private function canManage(User $user, TimeEntry $timeEntry): bool
    {
        return $user->roleIn($timeEntry->project->workspace)?->canManageWorkspace() ?? false;
    }
}
