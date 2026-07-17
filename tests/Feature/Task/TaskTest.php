<?php

use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkPackage;
use App\Models\Workspace;

test('a task belongs to a work package and can have an assignee', function () {
    $workspace = Workspace::factory()->create();
    $client = Client::factory()->for($workspace)->create();
    $project = Project::factory()->for($client)->create();
    $workPackage = WorkPackage::factory()->for($project)->create();
    $assignee = User::factory()->create();

    $task = Task::factory()->for($workPackage)->create(['assignee_id' => $assignee->id]);

    expect($task->workPackage->is($workPackage))->toBeTrue()
        ->and($task->assignee->is($assignee))->toBeTrue()
        ->and($task->status)->toBe(TaskStatus::Todo);
});

test('a task can be linked to an external system via external_id', function () {
    $workspace = Workspace::factory()->create();
    $client = Client::factory()->for($workspace)->create();
    $project = Project::factory()->for($client)->create();
    $workPackage = WorkPackage::factory()->for($project)->create();

    $task = Task::factory()->for($workPackage)->create(['external_id' => null]);
    $linkedTask = Task::factory()->for($workPackage)->withExternalId()->create();

    expect($task->external_id)->toBeNull()
        ->and($linkedTask->external_id)->not->toBeNull();
});
