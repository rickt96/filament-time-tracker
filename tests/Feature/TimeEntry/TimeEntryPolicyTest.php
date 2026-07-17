<?php

use App\Actions\TimeEntry\CreateTimeEntryAction;
use App\Actions\Workspace\CreateWorkspaceAction;
use App\Enums\WorkspaceRole;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;

test('a user can view, update and delete their own time entry', function () {
    $owner = User::factory()->create();
    $workspace = app(CreateWorkspaceAction::class)->handle($owner, ['name' => 'Acme']);
    $client = Client::factory()->for($workspace)->create();
    $project = Project::factory()->for($client)->create();

    $timeEntry = app(CreateTimeEntryAction::class)->handle($owner, [
        'project_id' => $project->id,
        'date' => '2026-07-16',
        'duration_minutes' => 30,
    ]);

    expect($owner->can('view', $timeEntry))->toBeTrue()
        ->and($owner->can('update', $timeEntry))->toBeTrue()
        ->and($owner->can('delete', $timeEntry))->toBeTrue();
});

test('a member cannot update or delete another member time entry', function () {
    $owner = User::factory()->create();
    $workspace = app(CreateWorkspaceAction::class)->handle($owner, ['name' => 'Acme']);

    $member = User::factory()->create();
    $workspace->users()->attach($member, ['role' => WorkspaceRole::Member->value]);

    $client = Client::factory()->for($workspace)->create();
    $project = Project::factory()->for($client)->create();

    $timeEntry = app(CreateTimeEntryAction::class)->handle($owner, [
        'project_id' => $project->id,
        'date' => '2026-07-16',
        'duration_minutes' => 30,
    ]);

    expect($member->can('update', $timeEntry))->toBeFalse()
        ->and($member->can('delete', $timeEntry))->toBeFalse();
});

test('an owner or admin can update and delete another member time entry', function () {
    $owner = User::factory()->create();
    $workspace = app(CreateWorkspaceAction::class)->handle($owner, ['name' => 'Acme']);

    $member = User::factory()->create();
    $workspace->users()->attach($member, ['role' => WorkspaceRole::Member->value]);

    $client = Client::factory()->for($workspace)->create();
    $project = Project::factory()->for($client)->create();

    $timeEntry = app(CreateTimeEntryAction::class)->handle($member, [
        'project_id' => $project->id,
        'date' => '2026-07-16',
        'duration_minutes' => 30,
    ]);

    expect($owner->can('update', $timeEntry))->toBeTrue()
        ->and($owner->can('delete', $timeEntry))->toBeTrue();
});
