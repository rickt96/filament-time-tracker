<?php

use App\Actions\TimeEntry\CreateTimeEntryAction;
use App\Actions\TimeEntry\DuplicateTimeEntryAction;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Carbon;

test('duplicates a time entry onto a target date preserving project, task, description, tags and duration', function () {
    $user = User::factory()->create();
    $project = makeProject();
    $tag = Tag::factory()->for($project->workspace)->create();

    $source = app(CreateTimeEntryAction::class)->handle($user, [
        'project_id' => $project->id,
        'date' => '2026-07-16',
        'started_at' => '09:00',
        'ended_at' => '10:30',
        'description' => 'Original work',
        'tags' => [$tag->id],
    ]);

    $duplicate = app(DuplicateTimeEntryAction::class)->handle($source, Carbon::parse('2026-07-20'));

    expect($duplicate->id)->not->toBe($source->id)
        ->and($duplicate->project_id)->toBe($source->project_id)
        ->and($duplicate->description)->toBe('Original work')
        ->and($duplicate->duration_seconds)->toBe($source->duration_seconds)
        ->and($duplicate->date->toDateString())->toBe('2026-07-20')
        ->and($duplicate->tags->pluck('id'))->toContain($tag->id);
});

test('duplicates a time entry preserving an explicit start time when given', function () {
    $user = User::factory()->create();
    $project = makeProject();

    $source = app(CreateTimeEntryAction::class)->handle($user, [
        'project_id' => $project->id,
        'date' => '2026-07-16',
        'duration_minutes' => 30,
    ]);

    $startAt = Carbon::parse('2026-07-20 14:00');

    $duplicate = app(DuplicateTimeEntryAction::class)->handle($source, Carbon::parse('2026-07-20'), $startAt);

    expect($duplicate->started_at->format('Y-m-d H:i'))->toBe('2026-07-20 14:00')
        ->and($duplicate->duration_seconds)->toBe(30 * 60);
});
