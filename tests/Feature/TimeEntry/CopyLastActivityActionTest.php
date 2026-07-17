<?php

use App\Actions\TimeEntry\CopyLastActivityAction;
use App\Actions\TimeEntry\CreateTimeEntryAction;
use App\Models\User;

test('copies the most recent time entry starting now', function () {
    $user = User::factory()->create();
    $project = makeProject();

    app(CreateTimeEntryAction::class)->handle($user, [
        'project_id' => $project->id,
        'date' => '2026-07-10',
        'started_at' => '09:00',
        'ended_at' => '10:00',
        'description' => 'Old task',
    ]);

    $lastEntry = app(CreateTimeEntryAction::class)->handle($user, [
        'project_id' => $project->id,
        'date' => '2026-07-15',
        'started_at' => '09:00',
        'ended_at' => '09:45',
        'description' => 'Most recent task',
    ]);

    $copy = app(CopyLastActivityAction::class)->handle($user);

    expect($copy)->not->toBeNull()
        ->and($copy->description)->toBe('Most recent task')
        ->and($copy->duration_seconds)->toBe($lastEntry->duration_seconds)
        ->and($copy->id)->not->toBe($lastEntry->id);
});

test('returns null when the user has no previous activity', function () {
    $user = User::factory()->create();

    expect(app(CopyLastActivityAction::class)->handle($user))->toBeNull();
});
