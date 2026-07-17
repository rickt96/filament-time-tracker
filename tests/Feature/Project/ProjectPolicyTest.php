<?php

use App\Actions\Workspace\CreateWorkspaceAction;
use App\Enums\WorkspaceRole;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;

test('workspace owners and admins can update and delete projects, members cannot', function () {
    $owner = User::factory()->create();
    $workspace = app(CreateWorkspaceAction::class)->handle($owner, ['name' => 'Acme']);

    $member = User::factory()->create();
    $workspace->users()->attach($member, ['role' => WorkspaceRole::Member->value]);

    $client = Client::factory()->for($workspace)->create();
    $project = Project::factory()->for($client)->create();

    expect($owner->can('update', $project))->toBeTrue()
        ->and($owner->can('delete', $project))->toBeTrue()
        ->and($member->can('update', $project))->toBeFalse()
        ->and($member->can('delete', $project))->toBeFalse();
});

test('only workspace members can view a project', function () {
    $owner = User::factory()->create();
    $workspace = app(CreateWorkspaceAction::class)->handle($owner, ['name' => 'Acme']);

    $outsider = User::factory()->create();

    $client = Client::factory()->for($workspace)->create();
    $project = Project::factory()->for($client)->create();

    expect($owner->can('view', $project))->toBeTrue()
        ->and($outsider->can('view', $project))->toBeFalse();
});
