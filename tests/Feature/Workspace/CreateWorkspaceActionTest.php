<?php

use App\Actions\Workspace\CreateWorkspaceAction;
use App\Enums\WorkspaceRole;
use App\Models\User;

test('creating a workspace attaches the creator as owner', function () {
    $user = User::factory()->create();

    $workspace = app(CreateWorkspaceAction::class)->handle($user, [
        'name' => 'Acme Inc',
        'description' => 'Test workspace',
    ]);

    expect($workspace->owner_id)->toBe($user->id)
        ->and($workspace->name)->toBe('Acme Inc');

    $user->refresh();

    expect($user->roleIn($workspace))->toBe(WorkspaceRole::Owner)
        ->and($user->canAccessTenant($workspace))->toBeTrue()
        ->and($user->workspaces)->toHaveCount(1);
});

test('users outside a workspace have no role and no tenant access', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();

    $workspace = app(CreateWorkspaceAction::class)->handle($owner, [
        'name' => 'Acme Inc',
    ]);

    expect($outsider->roleIn($workspace))->toBeNull()
        ->and($outsider->canAccessTenant($workspace))->toBeFalse();
});
