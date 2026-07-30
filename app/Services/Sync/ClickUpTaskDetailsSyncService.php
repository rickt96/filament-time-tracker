<?php

namespace App\Services\Sync;

use App\Models\Project;
use App\Models\Task;
use Closure;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Refreshes every Task of a given Project from ClickUp: the old local name is
 * preserved as a note in the description, the Task is renamed from ClickUp's
 * own custom_id + name, and the local url is set to the ClickUp task's url.
 * A one-way pull, never touches ClickUp.
 */
class ClickUpTaskDetailsSyncService
{
    private const string API_BASE_URL = 'https://api.clickup.com/api/v2';

    public function sync(Project $project, bool $dryRun = false, ?Closure $onProgress = null): ClickUpTaskDetailsSyncSummary
    {
        $summary = new ClickUpTaskDetailsSyncSummary;
        $report = fn (string $message) => $onProgress ? $onProgress($message) : null;

        $configuration = $project->client?->sync_configuration ?? [];
        $apiKey = $configuration['api_key'] ?? null;

        if (blank($apiKey)) {
            $summary->warn("Progetto [{$project->name}]: cliente senza api_key ClickUp configurata.");

            return $summary;
        }

        $allTasks = $project->tasks()->get();
        $summary->tasksTotal = $allTasks->count();

        $tasks = $allTasks->filter(fn (Task $task): bool => filled($task->external_id));
        $summary->tasksWithoutExternalId = $summary->tasksTotal - $tasks->count();

        if ($tasks->isEmpty()) {
            $report("Nessun task con id ClickUp (external_id) trovato per il progetto [{$project->name}].");

            return $summary;
        }

        $report("Progetto [{$project->name}]: {$tasks->count()} task da aggiornare (su {$summary->tasksTotal} totali, {$summary->tasksWithoutExternalId} senza external_id).");

        foreach ($tasks as $task) {
            $this->syncTask($task, $apiKey, $dryRun, $summary);
        }

        return $summary;
    }

    private function syncTask(Task $task, string $apiKey, bool $dryRun, ClickUpTaskDetailsSyncSummary $summary): void
    {
        $url = self::API_BASE_URL."/task/{$task->external_id}";

        try {
            $response = Http::withHeaders(['Authorization' => $apiKey])->get($url);
        } catch (Throwable $exception) {
            $this->logRaw($task, $url, error: $exception->getMessage());
            $summary->tasksFailed++;
            $summary->warn("Task #{$task->id} [{$task->external_id}]: errore di rete — {$exception->getMessage()}");

            return;
        }

        $this->logRaw($task, $url, $response);

        if ($response->failed()) {
            $summary->tasksFailed++;
            $summary->warn("Task #{$task->id} [{$task->external_id}]: ClickUp ha risposto con l'errore HTTP {$response->status()}.");

            return;
        }

        $payload = $response->json();
        $clickUpName = $payload['name'] ?? null;

        if (blank($clickUpName)) {
            $summary->tasksSkipped++;
            $summary->warn("Task #{$task->id} [{$task->external_id}]: risposta ClickUp senza campo name, saltato.");

            return;
        }

        // 1. Strip any [xxxx] bracketed text from the current local name.
        $cleanedLocalName = trim((string) preg_replace('/\[[^\]]*\]/', '', $task->name));

        // 2. Preserve that cleaned local name as a note at the top of the
        // description, above whatever was already there.
        $note = "nome originale: {$cleanedLocalName}";
        $task->description = filled($task->description) ? "{$note}\n\n{$task->description}" : $note;

        // 3. Rename from ClickUp's own custom_id + name. custom_id is a
        // paid/optional ClickUp feature and can be null — fall back to the
        // ClickUp task id itself so the format never degrades to "[] Name".
        $customId = $payload['custom_id'] ?? $payload['id'] ?? $task->external_id;
        $task->name = "[{$customId}] {$clickUpName}";

        // 4. Point the local url at the ClickUp task.
        if (filled($payload['url'] ?? null)) {
            $task->url = $payload['url'];
        }

        if (! $dryRun) {
            $task->save();
        }

        $summary->tasksUpdated++;
    }

    private function logRaw(Task $task, string $url, ?Response $response = null, ?string $error = null): void
    {
        Log::channel('sync')->info('ClickUp task details sync', [
            'provider' => 'clickup',
            'task_id' => $task->id,
            'clickup_task_id' => $task->external_id,
            'url' => $url,
            'response_status' => $response?->status(),
            'response_body' => $response?->json() ?? $response?->body(),
            'error' => $error,
        ]);
    }
}
