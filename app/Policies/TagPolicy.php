<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;
use App\Policies\Concerns\ChecksWorkspaceRole;

class TagPolicy
{
    use ChecksWorkspaceRole;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Tag $tag): bool
    {
        return $user->roleIn($tag->workspace) !== null;
    }

    public function create(User $user): bool
    {
        return $this->currentWorkspaceRole($user)?->canManageWorkspace() ?? false;
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->roleIn($tag->workspace)?->canManageWorkspace() ?? false;
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->roleIn($tag->workspace)?->canManageWorkspace() ?? false;
    }
}
