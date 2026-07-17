<?php

namespace App\Actions\TimeEntry;

use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Carbon;

class CopyLastActivityAction
{
    public function __construct(private readonly DuplicateTimeEntryAction $duplicateTimeEntryAction) {}

    public function handle(User $user): ?TimeEntry
    {
        $lastEntry = TimeEntry::query()
            ->where('user_id', $user->id)
            ->latest('started_at')
            ->first();

        if (! $lastEntry) {
            return null;
        }

        $now = Carbon::now();

        return $this->duplicateTimeEntryAction->handle($lastEntry, $now, $now);
    }
}
