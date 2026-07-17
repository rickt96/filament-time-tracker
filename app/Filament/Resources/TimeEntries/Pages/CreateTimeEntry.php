<?php

namespace App\Filament\Resources\TimeEntries\Pages;

use App\Actions\TimeEntry\CreateTimeEntryAction;
use App\Filament\Resources\TimeEntries\TimeEntryResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateTimeEntry extends CreateRecord
{
    protected static string $resource = TimeEntryResource::class;

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
