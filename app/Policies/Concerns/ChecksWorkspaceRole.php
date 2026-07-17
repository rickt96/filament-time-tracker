<?php

namespace App\Policies\Concerns;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Filament\Facades\Filament;

/**
 * Shared by every Policy whose "create" ability has no record to derive a
 * workspace from yet, and so must fall back to the currently active
 * Filament tenant instead.
 */
trait ChecksWorkspaceRole
{
    private function currentWorkspaceRole(User $user): ?WorkspaceRole
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Workspace ? $user->roleIn($tenant) : null;
    }
}
