<?php

namespace App\Actions\Workspace;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CreateWorkspaceAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $owner, array $data): Workspace
    {
        return DB::transaction(function () use ($owner, $data) {
            $workspace = Workspace::create([
                ...$data,
                'owner_id' => $owner->id,
            ]);

            $workspace->users()->attach($owner, ['role' => WorkspaceRole::Owner->value]);

            return $workspace;
        });
    }
}
