<?php

use App\Actions\TimeEntry\CreateTimeEntryAction;
use App\Enums\ProjectStatus;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkPackage;
use Illuminate\Validation\ValidationException;

test('creates a time entry using an explicit time range and computes duration and amount', function () {
    $user = User::factory()->create();
    $project = makeProject(['hourly_rate' => 50]);

    $timeEntry = app(CreateTimeEntryAction::class)->handle($user, [
        'project_id' => $project->id,
        'date' => '2026-07-16',
        'started_at' => '09:00',
        'ended_at' => '11:00',
        'description' => 'Test entry',
    ]);

    expect($timeEntry->duration_seconds)->toBe(2 * 3600)
        ->and((float) $timeEntry->hourly_rate)->toBe(50.0)
        ->and((float) $timeEntry->total_amount)->toBe(100.0)
        ->and($timeEntry->date->toDateString())->toBe('2026-07-16');
});

test('creates a time entry using duration_minutes only', function () {
    $user = User::factory()->create();
    $project = makeProject();

    $timeEntry = app(CreateTimeEntryAction::class)->handle($user, [
        'project_id' => $project->id,
        'date' => '2026-07-16',
        'duration_minutes' => 45,
    ]);

    expect($timeEntry->duration_seconds)->toBe(45 * 60)
        ->and($timeEntry->started_at->format('H:i'))->toBe('00:00')
        ->and($timeEntry->ended_at->format('H:i'))->toBe('00:45');
});

test('copies the effective hourly rate from the task work package, falling back to the project', function () {
    $user = User::factory()->create();
    $project = makeProject(['hourly_rate' => 40]);
    $workPackageWithRate = WorkPackage::factory()->for($project)->create(['hourly_rate' => 90]);
    $taskWithRate = Task::factory()->for($workPackageWithRate)->create();

    $entryWithTaskRate = app(CreateTimeEntryAction::class)->handle($user, [
        'project_id' => $project->id,
        'task_id' => $taskWithRate->id,
        'date' => '2026-07-16',
        'duration_minutes' => 60,
    ]);

    $workPackageWithoutRate = WorkPackage::factory()->for($project)->create(['hourly_rate' => null]);
    $taskWithoutRate = Task::factory()->for($workPackageWithoutRate)->create();

    $entryFallingBackToProjectRate = app(CreateTimeEntryAction::class)->handle($user, [
        'project_id' => $project->id,
        'task_id' => $taskWithoutRate->id,
        'date' => '2026-07-17',
        'duration_minutes' => 60,
    ]);

    expect((float) $entryWithTaskRate->hourly_rate)->toBe(90.0)
        ->and((float) $entryFallingBackToProjectRate->hourly_rate)->toBe(40.0);
});

test('rejects entries for archived projects', function () {
    $user = User::factory()->create();
    $project = makeProject(['status' => ProjectStatus::Archived]);

    app(CreateTimeEntryAction::class)->handle($user, [
        'project_id' => $project->id,
        'date' => '2026-07-16',
        'duration_minutes' => 30,
    ]);
})->throws(ValidationException::class);

test('rejects entries for projects whose client is inactive', function () {
    $user = User::factory()->create();
    $project = makeProject(clientAttributes: ['is_active' => false]);

    app(CreateTimeEntryAction::class)->handle($user, [
        'project_id' => $project->id,
        'date' => '2026-07-16',
        'duration_minutes' => 30,
    ]);
})->throws(ValidationException::class);

test('rejects entries where the end time is not after the start time', function () {
    $user = User::factory()->create();
    $project = makeProject();

    app(CreateTimeEntryAction::class)->handle($user, [
        'project_id' => $project->id,
        'date' => '2026-07-16',
        'started_at' => '11:00',
        'ended_at' => '09:00',
    ]);
})->throws(ValidationException::class);

test('rejects overlapping time entries for the same user', function () {
    $user = User::factory()->create();
    $project = makeProject();

    app(CreateTimeEntryAction::class)->handle($user, [
        'project_id' => $project->id,
        'date' => '2026-07-16',
        'started_at' => '09:00',
        'ended_at' => '11:00',
    ]);

    app(CreateTimeEntryAction::class)->handle($user, [
        'project_id' => $project->id,
        'date' => '2026-07-16',
        'started_at' => '10:00',
        'ended_at' => '12:00',
    ]);
})->throws(ValidationException::class);

test('allows back-to-back entries that touch but do not overlap', function () {
    $user = User::factory()->create();
    $project = makeProject();

    app(CreateTimeEntryAction::class)->handle($user, [
        'project_id' => $project->id,
        'date' => '2026-07-16',
        'started_at' => '09:00',
        'ended_at' => '11:00',
    ]);

    $second = app(CreateTimeEntryAction::class)->handle($user, [
        'project_id' => $project->id,
        'date' => '2026-07-16',
        'started_at' => '11:00',
        'ended_at' => '12:00',
    ]);

    expect($second->exists)->toBeTrue();
});

test('syncs tags on creation', function () {
    $user = User::factory()->create();
    $project = makeProject();
    $tag = Tag::factory()->for($project->workspace)->create();

    $timeEntry = app(CreateTimeEntryAction::class)->handle($user, [
        'project_id' => $project->id,
        'date' => '2026-07-16',
        'duration_minutes' => 30,
        'tags' => [$tag->id],
    ]);

    expect($timeEntry->tags->pluck('id'))->toContain($tag->id);
});
