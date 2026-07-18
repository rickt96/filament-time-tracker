<?php

namespace App\Actions\Sync;

use App\Enums\TimeEntrySyncStatus;
use App\Models\TimeEntry;
use App\Services\Sync\SyncDriverManager;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * The single entry point for pushing a Time Entry to whatever external
 * provider its Client is configured for. Never called automatically —
 * only from explicit Filament actions (single / bulk / "all filtered").
 *
 * Resolves the chain TimeEntry -> Task -> WorkPackage -> Project -> Client,
 * rejecting up front if there's no Task with an import_clickup_id or no
 * driver configured, before ever touching the driver itself.
 */
class SyncTimeEntryAction
{
    public function __construct(private readonly SyncDriverManager $driverManager) {}

    public function handle(TimeEntry $timeEntry): TimeEntry
    {
        $task = $timeEntry->task;
        
        if (! $task || blank($task->external_id)) {
            return $this->markFailed($timeEntry, 'Il time entry non è collegato a un task con un ID esterno.');
        }

        $client = $timeEntry->project->client;

        if ($client->sync_driver === null) {
            return $this->markFailed($timeEntry, 'Il cliente non ha un driver di sincronizzazione configurato.');
        }

        $driver = $this->driverManager->driverFor($client);

        if (! $driver->validateConfiguration($client->sync_configuration ?? [])) {
            return $this->markFailed($timeEntry, 'La configurazione di sincronizzazione del cliente non è valida.');
        }

        try {
            $result = $driver->syncTimeEntry($timeEntry);
        } catch (Throwable $exception) {
            return $this->markFailed($timeEntry, $exception->getMessage());
        }

        return $result->successful
            ? $this->markSynced($timeEntry)
            : $this->markFailed($timeEntry, $result->errorMessage ?? 'Sincronizzazione fallita.');
    }

    private function markSynced(TimeEntry $timeEntry): TimeEntry
    {
        $timeEntry->update([
            'synced_at' => Carbon::now(),
            'sync_status' => TimeEntrySyncStatus::Synced,
            'sync_error' => null,
        ]);

        return $timeEntry->refresh();
    }

    private function markFailed(TimeEntry $timeEntry, string $message): TimeEntry
    {
        $timeEntry->update([
            'sync_status' => TimeEntrySyncStatus::Failed,
            'sync_error' => $message,
        ]);

        return $timeEntry->refresh();
    }
}
