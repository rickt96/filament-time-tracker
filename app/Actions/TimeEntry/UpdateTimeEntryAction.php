<?php

namespace App\Actions\TimeEntry;

use App\Models\Project;
use App\Models\TimeEntry;
use App\Services\TimeEntryCalculator;
use App\Services\TimeEntryEligibilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateTimeEntryAction
{
    public function __construct(
        private readonly TimeEntryCalculator $calculator,
        private readonly TimeEntryEligibilityService $eligibility,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(TimeEntry $timeEntry, array $data): TimeEntry
    {
        $project = Project::findOrFail((int) ($data['project_id'] ?? $timeEntry->project_id));

        $this->eligibility->assertProjectSelectable($project);

        $data['date'] ??= $timeEntry->date->toDateString();
        [$startedAt, $endedAt] = $this->calculator->resolveTimes($data);

        if ($endedAt->lessThanOrEqualTo($startedAt)) {
            throw ValidationException::withMessages([
                'ended_at' => "L'ora di fine deve essere successiva all'ora di inizio.",
            ]);
        }

        return DB::transaction(function () use ($timeEntry, $data, $project, $startedAt, $endedAt) {
            $this->eligibility->assertNoOverlap($timeEntry->user_id, $startedAt, $endedAt, ignoreId: $timeEntry->id);

            $durationSeconds = $this->calculator->durationInSeconds($startedAt, $endedAt);

            // hourly_rate is intentionally never re-derived here: it was copied
            // from the Project/WorkPackage once at creation time and stays fixed,
            // so historical costs never shift when rates change later.
            $timeEntry->update([
                'project_id' => $project->id,
                'task_id' => array_key_exists('task_id', $data) ? $data['task_id'] : $timeEntry->task_id,
                'description' => array_key_exists('description', $data) ? $data['description'] : $timeEntry->description,
                'date' => $startedAt->toDateString(),
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'duration_seconds' => $durationSeconds,
                'total_amount' => $this->calculator->amount($durationSeconds, $timeEntry->hourly_rate),
            ]);

            if (array_key_exists('tags', $data)) {
                $timeEntry->tags()->sync($data['tags']);
            }

            return $timeEntry->refresh();
        });
    }
}
