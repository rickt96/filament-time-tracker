<?php

namespace App\Filament\Widgets;

use App\Enums\TaskStatus;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Open tasks assigned to the current user with a deadline, grouped into
 * "oggi" / "scaduti" / "prossimi" card lists — modelled on ClickUp's own
 * "Due Today" / "Overdue" home widget (screenshot reference) rather than a
 * plain table. Card click goes straight to the task's edit page: a custom
 * widget view has no built-in row-action mounting the way a Table does, and
 * a plain link is the lowest-risk way to get there without wiring up
 * mountable Actions on a bespoke Widget class.
 */
class TasksDueWidget extends Widget
{
    protected string $view = 'filament.widgets.tasks-due';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, array{label: string, tasks: Collection<int, Task>, isOverdue: bool}>
     */
    public function getGroups(): array
    {
        $tasks = Task::query()
            ->where('assignee_id', Auth::id())
            ->whereNotNull('expire')
            ->whereNotIn('status', [TaskStatus::Done, TaskStatus::Cancelled])
            ->whereHas(
                'workPackage.project',
                fn (Builder $query) => $query->where('workspace_id', Filament::getTenant()?->getKey()),
            )
            ->with('workPackage.project')
            ->orderBy('expire')
            ->get();

        $isToday = fn (Task $task): bool => $task->expire?->isToday() ?? false;

        $groups = [
            'today' => [
                'label' => 'Oggi',
                'tasks' => $tasks->filter($isToday)->values(),
                'isOverdue' => false,
            ],
            'overdue' => [
                'label' => 'Scaduti',
                'tasks' => $tasks->filter(fn (Task $task): bool => ($task->expire?->isPast() ?? false) && ! $isToday($task))->values(),
                'isOverdue' => true,
            ],
            'upcoming' => [
                'label' => 'Prossimi',
                'tasks' => $tasks->filter(fn (Task $task): bool => ($task->expire?->isFuture() ?? false) && ! $isToday($task))->values(),
                'isOverdue' => false,
            ],
        ];

        return array_filter($groups, fn (array $group): bool => $group['tasks']->isNotEmpty());
    }

    public function editUrlFor(Task $task): string
    {
        return TaskResource::getUrl('edit', ['record' => $task]);
    }
}
