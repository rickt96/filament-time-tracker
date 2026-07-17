<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;
use App\Policies\Concerns\ChecksWorkspaceRole;

class ClientPolicy
{
    use ChecksWorkspaceRole;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Client $client): bool
    {
        return $user->roleIn($client->workspace) !== null;
    }

    public function create(User $user): bool
    {
        return $this->currentWorkspaceRole($user)?->canManageWorkspace() ?? false;
    }

    public function update(User $user, Client $client): bool
    {
        return $user->roleIn($client->workspace)?->canManageWorkspace() ?? false;
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->roleIn($client->workspace)?->canManageWorkspace() ?? false;
    }
}
