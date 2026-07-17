<?php

namespace App\Actions\TimeEntry;

use App\Models\TimeEntry;
use Illuminate\Support\Carbon;

class DuplicateTimeEntryAction
{
    public function __construct(private readonly CreateTimeEntryAction $createTimeEntryAction) {}

    /**
     * Duplicate a Time Entry onto a target date. When $startAt is given, the
     * copy preserves that exact start time (range mode); otherwise it's
     * placed as a plain duration starting at midnight (duration mode).
     */
    public function handle(TimeEntry $source, ?Carbon $date = null, ?Carbon $startAt = null): TimeEntry
    {
        $date ??= Carbon::now();

        $data = [
            'project_id' => $source->project_id,
            'task_id' => $source->task_id,
            'description' => $source->description,
            'date' => $date->toDateString(),
            'tags' => $source->tags->pluck('id')->all(),
        ];

        if ($startAt) {
            $data['started_at'] = $startAt->format('H:i');
            $data['ended_at'] = $startAt->copy()->addSeconds($source->duration_seconds)->format('H:i');
        } else {
            $data['duration_minutes'] = intdiv($source->duration_seconds, 60);
        }

        return $this->createTimeEntryAction->handle($source->user, $data);
    }
}
