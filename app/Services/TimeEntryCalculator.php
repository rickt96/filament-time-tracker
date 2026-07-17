<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class TimeEntryCalculator
{
    /**
     * Resolve the start/end Carbon instances from a normalized time entry
     * input shape, regardless of which manual-entry mode produced it:
     * explicit started_at/ended_at ("intervallo orario"), or date +
     * duration_minutes ("durata" / "solo minuti" — both reduce to the same
     * duration-from-midnight shape, only the UI granularity differs).
     *
     * @param  array<string, mixed>  $data
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolveTimes(array $data): array
    {
        $date = Carbon::parse($data['date']);

        $startedAt = filled($data['started_at'] ?? null)
            ? $this->parseTimeOrDateTime((string) $data['started_at'], $date)
            : $date->copy()->startOfDay();

        $endedAt = filled($data['ended_at'] ?? null)
            ? $this->parseTimeOrDateTime((string) $data['ended_at'], $date)
            : $this->endFromDuration($startedAt, ((int) ($data['duration_minutes'] ?? 0)) * 60);

        return [$startedAt, $endedAt];
    }

    /**
     * A bare time-of-day like "09:00" (from the manual-entry form) is
     * anchored to $date. A value that already carries its own date
     * component (e.g. a full datetime from an external import, possibly a
     * different day than $date for an entry spanning midnight) is parsed
     * as-is instead, since concatenating it with $date again would either
     * double up the date or silently discard the real day it happened on.
     */
    private function parseTimeOrDateTime(string $value, Carbon $date): Carbon
    {
        return preg_match('/\d{4}-\d{2}-\d{2}/', $value)
            ? Carbon::parse($value)
            : Carbon::parse($date->toDateString().' '.$value);
    }

    public function durationInSeconds(Carbon $start, Carbon $end): int
    {
        return max(0, (int) $start->diffInSeconds($end));
    }

    public function endFromDuration(Carbon $start, int $durationSeconds): Carbon
    {
        return $start->copy()->addSeconds(max(0, $durationSeconds));
    }

    public function amount(int $durationSeconds, string|float|null $hourlyRate): ?string
    {
        if ($hourlyRate === null) {
            return null;
        }

        return number_format(($durationSeconds / 3600) * (float) $hourlyRate, 2, '.', '');
    }
}
