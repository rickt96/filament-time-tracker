<?php

namespace App\Filament\Resources\TimeEntries\Pages;

use App\Actions\TimeEntry\CreateTimeEntryAction;
use App\Filament\Resources\TimeEntries\TimeEntryResource;
use App\Models\Task;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateTimeEntry extends CreateRecord
{
    protected static string $resource = TimeEntryResource::class;

    /**
     * Query string keys honored as form-field presets — see
     * getUrlPrefillData(). Kept as an explicit allow-list rather than
     * passing the whole query string through, so unrelated params (e.g.
     * Livewire's own) can never leak into the form state.
     *
     * @var array<int, string>
     */
    private const array URL_PREFILLABLE_FIELDS = [
        'project_id',
        'work_package_id',
        'task_id',
        'date',
        'started_at',
        'ended_at',
        'description',
    ];

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $this->form->fill($this->getUrlPrefillData());

        $this->callHook('afterFill');
    }

    /**
     * Lets a link elsewhere in the app (e.g. a Task's "create time entry"
     * global search action) preset fields on this form via URL query
     * parameters, such as .../time-entries/create?task_id=123.
     *
     * task_id, when present, dominates: its own work_package_id/project_id
     * always overwrite whatever those keys carried in the query string,
     * rather than merely filling gaps. Those Select fields are hierarchical
     * (each filters/disables based on its parent), so a task_id paired with
     * a mismatched project_id/work_package_id would otherwise leave the task
     * option unselectable or silently inconsistent with what's shown.
     *
     * @return array<string, mixed>
     */
    protected function getUrlPrefillData(): array
    {
        $data = collect(request()->query())
            ->only(self::URL_PREFILLABLE_FIELDS)
            ->filter(fn (mixed $value): bool => filled($value))
            ->all();

        if (filled($data['task_id'] ?? null)) {
            // se è stato impostato il task_id, forzo work_package e project_id
            $task = Task::query()->with('workPackage')->find((int) $data['task_id']);

            if ($task) {
                $data['work_package_id'] = $task->work_package_id;
                $data['project_id'] = $task->workPackage->project_id;
            }
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = Auth::user();

        return app(CreateTimeEntryAction::class)->handle($user, static::normalize($data));
    }

    /**
     * Reduce the 3 manual-entry UI modes (range / duration / minutes) down to
     * the single normalized shape TimeEntryCalculator::resolveTimes() expects.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        $mode = $data['entry_mode'] ?? 'range';

        $data['duration_minutes'] = match ($mode) {
            'duration' => ((int) ($data['duration_hours'] ?? 0) * 60) + (int) ($data['duration_minutes_part'] ?? 0),
            'minutes' => (int) ($data['minutes_only'] ?? 0),
            default => null,
        };

        if ($mode !== 'range') {
            unset($data['started_at'], $data['ended_at']);
        }

        return $data;
    }
}
