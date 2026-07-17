<?php

use App\Actions\TimeEntry\CreateTimeEntryAction;
use App\Actions\TimeEntry\UpdateTimeEntryAction;
use App\Models\User;
use Illuminate\Validation\ValidationException;

test('updating the time range recomputes duration and total_amount using the original hourly rate', function () {
    $user = User::factory()->create();
    $project = makeProject(['hourly_rate' => 50]);

    $timeEntry = app(CreateTimeEntryAction::class)->handle($user, [
        'project_id' => $project->id,
        'date' => '2026-07-16',
        'started_at' => '09:00',
        'ended_at' => '10:00',
    ]);

    $project->update(['hourly_rate' => 200]);

    $updated = app(UpdateTimeEntryAction::class)->handle($timeEntry, [
        'date' => '2026-07-16',
        'started_at' => '09:00',
        'ended_at' => '11:00',
    ]);

    expect($updated->duration_seconds)->toBe(2 * 3600)
        ->and((float) $updated->hourly_rate)->toBe(50.0)
        ->and((float) $updated->total_amount)->toBe(100.0);
});

test('changing a project rate never alters previously saved time entries', function () {
    $user = User::factory()->create();
    $project = makeProject(['hourly_rate' => 50]);

    $timeEntry = app(CreateTimeEntryAction::class)->handle($user, [
        'project_id' => $project->id,
        'date' => '2026-07-16',
        'started_at' => '09:00',
        'ended_at' => '10:00',
    ]);

    $originalAmount = $timeEntry->total_amount;

    $project->update(['hourly_rate' => 999]);

    expect((float) $timeEntry->fresh()->hourly_rate)->toBe(50.0)
        ->and($timeEntry->fresh()->total_amount)->toEqual($originalAmount);
});

test('rejects an update where the end time is not after the start time', function () {
    $user = User::factory()->create();
    $project = makeProject();

    $timeEntry = app(CreateTimeEntryAction::class)->handle($user, [
        'project_id' => $project->id,
        'date' => '2026-07-16',
        'started_at' => '09:00',
        'ended_at' => '10:00',
    ]);

    app(UpdateTimeEntryAction::class)->handle($timeEntry, [
        'date' => '2026-07-16',
        'started_at' => '10:00',
        'ended_at' => '09:00',
    ]);
})->throws(ValidationException::class);

test('rejects an update that overlaps another entry of the same user', function () {
    $user = User::factory()->create();
    $project = makeProject();

    app(CreateTimeEntryAction::class)->handle($user, [
        'project_id' => $project->id,
        'date' => '2026-07-16',
        'started_at' => '09:00',
        'ended_at' => '10:00',
    ]);

    $second = app(CreateTimeEntryAction::class)->handle($user, [
        'project_id' => $project->id,
        'date' => '2026-07-16',
        'started_at' => '11:00',
        'ended_at' => '12:00',
    ]);

    app(UpdateTimeEntryAction::class)->handle($second, [
        'date' => '2026-07-16',
        'started_at' => '09:30',
        'ended_at' => '10:30',
    ]);
})->throws(ValidationException::class);

test('allows an update that keeps the same time range (no false overlap against itself)', function () {
    $user = User::factory()->create();
    $project = makeProject();

    $timeEntry = app(CreateTimeEntryAction::class)->handle($user, [
        'project_id' => $project->id,
        'date' => '2026-07-16',
        'started_at' => '09:00',
        'ended_at' => '10:00',
        'description' => 'original',
    ]);

    $updated = app(UpdateTimeEntryAction::class)->handle($timeEntry, [
        'date' => '2026-07-16',
        'started_at' => '09:00',
        'ended_at' => '10:00',
        'description' => 'updated',
    ]);

    expect($updated->description)->toBe('updated');
});
