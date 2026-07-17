<?php

use App\Actions\Workspace\CreateWorkspaceAction;
use App\Enums\WorkspaceRole;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkPackage;

test('workspace owners and admins can update and delete work packages, members cannot', function () {
    $owner = User::factory()->create();
    $workspace = app(CreateWorkspaceAction::class)->handle($owner, ['name' => 'Acme']);

    $member = User::factory()->create();
    $workspace->users()->attach($member, ['role' => WorkspaceRole::Member->value]);

    $client = Client::factory()->for($workspace)->create();
    $project = Project::factory()->for($client)->create();
    $workPackage = WorkPackage::factory()->for($project)->create();

    expect($owner->can('update', $workPackage))->toBeTrue()
        ->and($owner->can('delete', $workPackage))->toBeTrue()
        ->and($member->can('update', $workPackage))->toBeFalse()
        ->and($member->can('delete', $workPackage))->toBeFalse();
});

test('only workspace members can view a work package', function () {
    $owner = User::factory()->create();
    $workspace = app(CreateWorkspaceAction::class)->handle($owner, ['name' => 'Acme']);

    $outsider = User::factory()->create();

    $client = Client::factory()->for($workspace)->create();
    $project = Project::factory()->for($client)->create();
    $workPackage = WorkPackage::factory()->for($project)->create();

    expect($owner->can('view', $workPackage))->toBeTrue()
        ->and($outsider->can('view', $workPackage))->toBeFalse();
});
