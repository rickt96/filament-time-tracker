<?php

namespace App\Services\Sync;

use App\Enums\ClientSyncDriver;
use App\Models\Project;
use App\Models\Task;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Refreshes local Task names from ClickUp: for every Project whose Client is
 * configured with the ClickUp sync driver, every Task carrying a ClickUp
 * external_id is re-fetched and its name overwritten with whatever ClickUp
 * currently has — a one-way pull, never touches ClickUp.
 */
class ClickUpTaskNameSyncService
{
    private const string API_BASE_URL = 'https://api.clickup.com/api/v2';

    public function sync(bool $dryRun = false, ?Closure $onProgress = null): ClickUpTaskNameSyncSummary
    {
        $summary = new ClickUpTaskNameSyncSummary;
        $report = fn (string $message) => $onProgress ? $onProgress($message) : null;

        $projects = Project::query()
            ->whereHas('client', fn (Builder $query) => $query->where('sync_driver', ClientSyncDriver::ClickUp))
            ->with('client')
            ->whereHas('client', fn ($q) => $q->where('id', 3)) // TEMP solo il cliente 3
            ->get();

        foreach ($projects as $project) {
            $summary->projectsScanned++;

            $configuration = $project->client->sync_configuration ?? [];
            $apiKey = $configuration['api_key'] ?? null;

            if (blank($apiKey)) {
                $summary->warn("Progetto [{$project->name}]: cliente senza api_key ClickUp configurata, saltato.");

                continue;
            }

            $tasks = $project->tasks()
                ->whereNotNull('external_id')
                ->where('external_id', '!=', '')
                ->get();

            if ($tasks->isEmpty()) {
                continue;
            }

            $report("Progetto [{$project->name}]: {$tasks->count()} task da aggiornare...");

            foreach ($tasks as $task) {
                $this->syncTask($task, $apiKey, $dryRun, $summary);
            }
        }

        return $summary;
    }

    private function syncTask(Task $task, string $apiKey, bool $dryRun, ClickUpTaskNameSyncSummary $summary): void
    {
        $url = self::API_BASE_URL."/task/{$task->external_id}";

        try {
            $response = Http::withHeaders(['Authorization' => $apiKey])->get($url);
        } catch (Throwable $exception) {
            $this->logRaw($task, $url, error: $exception->getMessage(), dryRun: $dryRun);
            $summary->tasksFailed++;
            $summary->warn("Task #{$task->id} [{$task->external_id}]: errore di rete — {$exception->getMessage()}");

            return;
        }

        $this->logRaw($task, $url, $response, dryRun: $dryRun);

        if ($response->failed()) {
            $summary->tasksFailed++;
            $summary->warn("Task #{$task->id} [{$task->external_id}]: ClickUp ha risposto con HTTP {$response->status()}.");

            return;
        }

        $name = $response->json('name');

        if (blank($name)) {
            $summary->tasksSkipped++;
            $summary->warn("Task #{$task->id} [{$task->external_id}]: risposta ClickUp senza campo name, saltato.");

            return;
        }

        if (! $dryRun) {
            $task->update(['name' => $name]);
        }

        $summary->tasksUpdated++;
    }

    private function logRaw(Task $task, string $url, ?Response $response = null, ?string $error = null, bool $dryRun = false): void
    {
        $context = [
            'provider' => 'clickup',
            'task_id' => $task->id,
            'clickup_task_id' => $task->external_id,
            'url' => $url,
            'response_status' => $response?->status(),
            // The payload ClickUp returned — decoded when it is JSON, the raw
            // body otherwise (error pages, empty responses).
            'response_body' => $response?->json() ?? $response?->body(),
            'error' => $error,
        ];

        Log::channel('sync')->info('ClickUp task name sync', $context);

        // Same trail, but hanging off the synced Task so it can be audited
        // from the record itself instead of grepping the log file.
        activity('clickup-sync')
            ->on($task)
            ->withProperties([
                ...$context,
                'successful' => $error === null && $response?->successful() === true,
                'dry_run' => $dryRun,
            ])
            ->log('Sincronizzazione nome task da ClickUp');
    }
}
