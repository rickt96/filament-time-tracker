<?php

namespace App\Actions\TimeEntry;

use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CopyPreviousDayAction
{
    public function __construct(private readonly DuplicateTimeEntryAction $duplicateTimeEntryAction) {}

    /**
     * @return Collection<int, TimeEntry>
     */
    public function handle(User $user, Carbon $date): Collection
    {
        $previousDay = $date->copy()->subDay()->toDateString();

        $entries = TimeEntry::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $previousDay)
            ->get();

        return DB::transaction(function () use ($entries, $date) {
            return $entries->map(function (TimeEntry $entry) use ($date) {
                // Preserve each entry's original time of day on the target date —
                // duration mode (always starting at midnight) would make every
                // copied entry collide with the others as soon as there is more than one.
                $startAt = $date->copy()->setTime(
                    $entry->started_at->hour,
                    $entry->started_at->minute,
                    $entry->started_at->second,
                );

                return $this->duplicateTimeEntryAction->handle($entry, $date, $startAt);
            });
        });
    }
}
