<?php

use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;

test('a project belongs to a client and inherits its workspace', function () {
    $workspace = Workspace::factory()->create();
    $client = Client::factory()->for($workspace)->create();
    $project = Project::factory()->for($client)->create();

    expect($project->client->is($client))->toBeTrue()
        ->and($project->workspace_id)->toBe($workspace->id);
});

test('the selectable scope only returns non-archived projects', function () {
    $workspace = Workspace::factory()->create();
    $client = Client::factory()->for($workspace)->create();

    $active = Project::factory()->for($client)->create();
    $archived = Project::factory()->for($client)->archived()->create();

    expect($active->status)->toBe(ProjectStatus::Active)
        ->and($archived->status)->toBe(ProjectStatus::Archived);

    $selectableIds = Project::query()->selectable()->pluck('id');

    expect($selectableIds)->toContain($active->id)
        ->and($selectableIds)->not->toContain($archived->id);
});

test('a project can have members assigned', function () {
    $workspace = Workspace::factory()->create();
    $client = Client::factory()->for($workspace)->create();
    $project = Project::factory()->for($client)->create();
    $user = User::factory()->create();

    $project->members()->attach($user);

    expect($project->members->pluck('id'))->toContain($user->id)
        ->and($user->projects->pluck('id'))->toContain($project->id);
});
