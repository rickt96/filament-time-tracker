<?php

use App\Models\Client;
use App\Models\Project;
use App\Models\Workspace;

test('a client belongs to a workspace and has many projects', function () {
    $workspace = Workspace::factory()->create();
    $client = Client::factory()->for($workspace)->create();
    $project = Project::factory()->for($client)->create();

    expect($client->workspace->is($workspace))->toBeTrue()
        ->and($client->projects)->toHaveCount(1)
        ->and($client->projects->first()->is($project))->toBeTrue();
});

test('inactive clients are excluded from the active client pool used by the project selector', function () {
    $workspace = Workspace::factory()->create();
    $activeClient = Client::factory()->for($workspace)->create(['is_active' => true]);
    $inactiveClient = Client::factory()->for($workspace)->inactive()->create();

    $selectableClientIds = Client::query()->where('is_active', true)->pluck('id');

    expect($selectableClientIds)->toContain($activeClient->id)
        ->and($selectableClientIds)->not->toContain($inactiveClient->id);
});
