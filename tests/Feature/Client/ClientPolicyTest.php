<?php

use App\Actions\Workspace\CreateWorkspaceAction;
use App\Enums\WorkspaceRole;
use App\Models\Client;
use App\Models\User;

test('workspace owners and admins can update and delete clients, members cannot', function () {
    $owner = User::factory()->create();
    $workspace = app(CreateWorkspaceAction::class)->handle($owner, ['name' => 'Acme']);

    $member = User::factory()->create();
    $workspace->users()->attach($member, ['role' => WorkspaceRole::Member->value]);

    $client = Client::factory()->for($workspace)->create();

    expect($owner->can('update', $client))->toBeTrue()
        ->and($owner->can('delete', $client))->toBeTrue()
        ->and($member->can('update', $client))->toBeFalse()
        ->and($member->can('delete', $client))->toBeFalse();
});

test('only workspace members can view a client', function () {
    $owner = User::factory()->create();
    $workspace = app(CreateWorkspaceAction::class)->handle($owner, ['name' => 'Acme']);

    $outsider = User::factory()->create();

    $client = Client::factory()->for($workspace)->create();

    expect($owner->can('view', $client))->toBeTrue()
        ->and($outsider->can('view', $client))->toBeFalse();
});
