<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\TimeEntry;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class TimeEntryEligibilityService
{
    public function assertProjectSelectable(Project $project): void
    {
        if ($project->status === ProjectStatus::Archived) {
            throw ValidationException::withMessages([
                'project_id' => 'Il progetto è archiviato e non può ricevere nuove registrazioni.',
            ]);
        }

        if (! $project->client->is_active) {
            throw ValidationException::withMessages([
                'project_id' => 'Il cliente associato al progetto non è attivo.',
            ]);
        }
    }

    public function assertNoOverlap(int $userId, Carbon $start, Carbon $end, ?int $ignoreId = null): void
    {
        $overlaps = TimeEntry::query()
            ->where('user_id', $userId)
            ->where('started_at', '<', $end)
            ->where('ended_at', '>', $start)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->lockForUpdate()
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'started_at' => 'Esiste già una registrazione che si sovrappone a questo intervallo per questo utente.',
            ]);
        }
    }
}
