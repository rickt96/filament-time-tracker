<?php

use App\Actions\Workspace\CreateWorkspaceAction;
use App\Filament\Resources\Workspaces\WorkspaceResource;
use App\Models\User;

test('a workspace member can access the workspace edit page', function () {
    $owner = User::factory()->create();

    $workspace = app(CreateWorkspaceAction::class)->handle($owner, [
        'name' => 'Acme Inc',
    ]);

    $this->actingAs($owner)
        ->get(WorkspaceResource::getUrl('edit', ['record' => $workspace], tenant: $workspace))
        ->assertSuccessful();
});

test('a user outside the workspace is denied access to it', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();

    $workspace = app(CreateWorkspaceAction::class)->handle($owner, [
        'name' => 'Acme Inc',
    ]);

    $this->actingAs($outsider)
        ->get(WorkspaceResource::getUrl('edit', ['record' => $workspace], tenant: $workspace))
        ->assertNotFound();
});
