<?php

namespace App\Services\Task;

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\WorkPackage;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Moves a set of Tasks to a different Work Package of the same Project,
 * cascading the change onto each Task's own Time Entries so their
 * work_package_id (and project_id, kept in sync with the target Work
 * Package's own Project) never drifts out of step with the Task they're
 * logged against.
 */
class TaskTransferService
{
    /**
     * @param  Collection<int, Task>  $tasks
     * @return array{tasks: int, timeEntries: int}
     */
    public function transfer(Collection $tasks, WorkPackage $targetWorkPackage): array
    {
        $mismatched = $tasks->filter(
            fn (Task $task): bool => $task->workPackage->project_id !== $targetWorkPackage->project_id,
        );

        if ($mismatched->isNotEmpty()) {
            $names = $mismatched->map(fn (Task $task): string => $task->name)->implode(', ');

            throw new InvalidArgumentException("I seguenti task appartengono a un progetto diverso dal Work Package di destinazione: {$names}.");
        }

        $taskIds = $tasks->pluck('id');

        $movedTasks = Task::withTrashed()
            ->whereIn('id', $taskIds)
            ->update(['work_package_id' => $targetWorkPackage->id]);

        // withTrashed(): a soft-deleted Time Entry logged against one of
        // these Tasks should follow it too, so a later restore doesn't leave
        // it pointing at the Task's old Work Package.
        $movedTimeEntries = TimeEntry::withTrashed()
            ->whereIn('task_id', $taskIds)
            ->update([
                'work_package_id' => $targetWorkPackage->id,
                'project_id' => $targetWorkPackage->project_id,
            ]);

        return ['tasks' => $movedTasks, 'timeEntries' => $movedTimeEntries];
    }
}
