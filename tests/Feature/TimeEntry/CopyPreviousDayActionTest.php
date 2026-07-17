<?php

use App\Actions\TimeEntry\CopyPreviousDayAction;
use App\Actions\TimeEntry\CreateTimeEntryAction;
use App\Models\User;
use Illuminate\Support\Carbon;

test('copies all of the previous day entries onto the target date, preserving their time of day', function () {
    $user = User::factory()->create();
    $project = makeProject();

    app(CreateTimeEntryAction::class)->handle($user, [
        'project_id' => $project->id,
        'date' => '2026-07-15',
        'started_at' => '09:00',
        'ended_at' => '10:00',
        'description' => 'Morning task',
    ]);

    app(CreateTimeEntryAction::class)->handle($user, [
        'project_id' => $project->id,
        'date' => '2026-07-15',
        'started_at' => '14:00',
        'ended_at' => '15:30',
        'description' => 'Afternoon task',
    ]);

    $copied = app(CopyPreviousDayAction::class)->handle($user, Carbon::parse('2026-07-16'));

    expect($copied)->toHaveCount(2);

    $dates = $copied->pluck('date')->map(fn ($date) => $date->toDateString());
    expect($dates->unique()->all())->toBe(['2026-07-16']);

    $times = $copied->pluck('started_at')->map(fn ($time) => $time->format('H:i'))->sort()->values();
    expect($times->all())->toBe(['09:00', '14:00']);
});

test('copying an empty previous day returns an empty collection', function () {
    $user = User::factory()->create();

    $copied = app(CopyPreviousDayAction::class)->handle($user, Carbon::parse('2026-07-16'));

    expect($copied)->toHaveCount(0);
});
