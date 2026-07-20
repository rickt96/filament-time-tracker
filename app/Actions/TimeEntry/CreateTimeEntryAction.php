<?php

namespace App\Actions\TimeEntry;

use App\Enums\TimeEntryStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkPackage;
use App\Services\TimeEntryCalculator;
use App\Services\TimeEntryEligibilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateTimeEntryAction
{
    public function __construct(
        private readonly TimeEntryCalculator $calculator,
        private readonly TimeEntryEligibilityService $eligibility,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data): TimeEntry
    {
        $project = Project::findOrFail((int) $data['project_id']);

        // TEMP IMPORT
        //$this->eligibility->assertProjectSelectable($project);

        [$startedAt, $endedAt] = $this->calculator->resolveTimes($data);

        if ($endedAt->lessThanOrEqualTo($startedAt)) {
            throw ValidationException::withMessages([
                'ended_at' => "L'ora di fine deve essere successiva all'ora di inizio.",
            ]);
        }

        $timeEntry = DB::transaction(function () use ($user, $data, $project, $startedAt, $endedAt) {

            // TEMP IMPORT
            //$this->eligibility->assertNoOverlap($user->id, $startedAt, $endedAt);

            $task = filled($data['task_id'] ?? null)
                        ? Task::find((int) $data['task_id'])
                        : null;

            // A Task always pins down its own Work Package; only fall back to
            // an explicitly submitted work_package_id when there's no Task.
            $workPackage = $task?->workPackage
                ?? (filled($data['work_package_id'] ?? null) ? WorkPackage::find((int) $data['work_package_id']) : null);

            $hourlyRate = $workPackage?->effectiveHourlyRate() ?? $project->hourly_rate;
            $durationSeconds = $this->calculator->durationInSeconds($startedAt, $endedAt);

            $timeEntry = TimeEntry::create([
                'user_id' => $user->id,
                'project_id' => $project->id,
                'task_id' => $task?->id,
                'work_package_id' => $workPackage?->id,
                'description' => $data['description'] ?? null,
                'date' => $startedAt->toDateString(),
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'duration_seconds' => $durationSeconds,
                'status' => TimeEntryStatus::Completed,
                'hourly_rate' => $hourlyRate,
                'total_amount' => $this->calculator->amount($durationSeconds, $hourlyRate),
            ]);

            if (filled($data['tags'] ?? null)) {
                $timeEntry->tags()->sync($data['tags']);
            }

            return $timeEntry;
        });

        return $timeEntry;
    }
}
