<?php

use App\Actions\TimeEntry\CreateTimeEntryAction;
use App\Models\User;

test('the client is derived from the project and never stored directly', function () {
    $user = User::factory()->create();
    $project = makeProject();

    $timeEntry = app(CreateTimeEntryAction::class)->handle($user, [
        'project_id' => $project->id,
        'date' => '2026-07-16',
        'duration_minutes' => 30,
    ]);

    expect($timeEntry->client->is($project->client))->toBeTrue()
        ->and($timeEntry->getAttributes())->not->toHaveKey('client_id');
});
