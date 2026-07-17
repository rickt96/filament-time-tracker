<?php

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\WorkPackage;
use App\Models\Workspace;

test('a work package belongs to a project and has many tasks', function () {
    $workspace = Workspace::factory()->create();
    $client = Client::factory()->for($workspace)->create();
    $project = Project::factory()->for($client)->create();
    $workPackage = WorkPackage::factory()->for($project)->create();
    $task = Task::factory()->for($workPackage)->create();

    expect($workPackage->project->is($project))->toBeTrue()
        ->and($workPackage->tasks)->toHaveCount(1)
        ->and($workPackage->tasks->first()->is($task))->toBeTrue();
});

test('effectiveHourlyRate falls back to the project rate when not set', function () {
    $workspace = Workspace::factory()->create();
    $client = Client::factory()->for($workspace)->create();
    $project = Project::factory()->for($client)->create(['hourly_rate' => 100]);
    $workPackage = WorkPackage::factory()->for($project)->create(['hourly_rate' => null]);

    expect($workPackage->effectiveHourlyRate())->toBe($project->hourly_rate);
});

test('effectiveHourlyRate uses its own rate when set, ignoring the project rate', function () {
    $workspace = Workspace::factory()->create();
    $client = Client::factory()->for($workspace)->create();
    $project = Project::factory()->for($client)->create(['hourly_rate' => 100]);
    $workPackage = WorkPackage::factory()->for($project)->create(['hourly_rate' => 75]);

    expect((float) $workPackage->effectiveHourlyRate())->toBe(75.0);
});

test('a work package can exist without any tasks', function () {
    $workspace = Workspace::factory()->create();
    $client = Client::factory()->for($workspace)->create();
    $project = Project::factory()->for($client)->create();
    $workPackage = WorkPackage::factory()->for($project)->create();

    expect($workPackage->tasks)->toHaveCount(0);
});
