<?php

use App\Actions\Workspace\CreateWorkspaceAction;
use App\Enums\WorkspaceRole;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkPackage;

test('workspace owners and admins can update and delete tasks, members cannot', function () {
    $owner = User::factory()->create();
    $workspace = app(CreateWorkspaceAction::class)->handle($owner, ['name' => 'Acme']);

    $member = User::factory()->create();
    $workspace->users()->attach($member, ['role' => WorkspaceRole::Member->value]);

    $client = Client::factory()->for($workspace)->create();
    $project = Project::factory()->for($client)->create();
    $workPackage = WorkPackage::factory()->for($project)->create();
    $task = Task::factory()->for($workPackage)->create();

    expect($owner->can('update', $task))->toBeTrue()
        ->and($owner->can('delete', $task))->toBeTrue()
        ->and($member->can('update', $task))->toBeFalse()
        ->and($member->can('delete', $task))->toBeFalse();
});

test('only workspace members can view a task', function () {
    $owner = User::factory()->create();
    $workspace = app(CreateWorkspaceAction::class)->handle($owner, ['name' => 'Acme']);

    $outsider = User::factory()->create();

    $client = Client::factory()->for($workspace)->create();
    $project = Project::factory()->for($client)->create();
    $workPackage = WorkPackage::factory()->for($project)->create();
    $task = Task::factory()->for($workPackage)->create();

    expect($owner->can('view', $task))->toBeTrue()
        ->and($outsider->can('view', $task))->toBeFalse();
});
