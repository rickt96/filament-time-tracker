<?php

namespace App\Services\Sync;

use App\Enums\ClientSyncDriver;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\WorkPackageStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\WorkPackage;
use App\Services\Sync\Exceptions\ClickUpImportException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Imports a single Task from ClickUp into a Project, given the ClickUp
 * task's own id — the "pull a task I already track remotely, but never
 * imported locally" counterpart to ClickUpTaskDetailsSyncService (which
 * only refreshes tasks that are already local). Filed under a single
 * "Import ClickUp" Work Package created per Project on demand, mirroring
 * ClockifyImportService's fallback-Work-Package convention.
 */
class ClickUpTaskImportService
{
    private const string API_BASE_URL = 'https://api.clickup.com/api/v2';

    public const string DEFAULT_WORK_PACKAGE_NAME = 'Import ClickUp';

    public function import(Project $project, string $externalTaskId): Task
    {
        $client = $project->client;

        if ($client?->sync_driver !== ClientSyncDriver::ClickUp) {
            throw new ClickUpImportException("Il progetto [{$project->name}] non ha un cliente configurato per la sincronizzazione ClickUp.");
        }

        $apiKey = $client->sync_configuration['api_key'] ?? null;

        if (blank($apiKey)) {
            throw new ClickUpImportException("Il cliente [{$client->name}] non ha una api_key ClickUp configurata.");
        }

        $existing = Task::query()
            ->whereHas('workPackage', fn ($query) => $query->where('project_id', $project->id))
            ->where('external_id', $externalTaskId)
            ->first();

        if ($existing) {
            throw new ClickUpImportException("Il task ClickUp [{$externalTaskId}] è già stato importato in questo progetto (« {$existing->name} »).");
        }

        $url = self::API_BASE_URL."/task/{$externalTaskId}";

        try {
            $response = Http::withHeaders(['Authorization' => $apiKey])->get($url);
        } catch (Throwable $exception) {
            $this->logRaw($project, $externalTaskId, $url, error: $exception->getMessage());

            throw new ClickUpImportException("Errore di rete durante la chiamata a ClickUp: {$exception->getMessage()}");
        }

        $this->logRaw($project, $externalTaskId, $url, $response);

        if ($response->failed()) {
            throw new ClickUpImportException("ClickUp ha risposto con l'errore HTTP {$response->status()} per il task [{$externalTaskId}].");
        }

        $payload = $response->json();
        $clickUpName = $payload['name'] ?? null;

        if (blank($clickUpName)) {
            throw new ClickUpImportException('La risposta di ClickUp non contiene un name valido.');
        }

        $workPackage = WorkPackage::query()->firstOrCreate(
            ['project_id' => $project->id, 'name' => self::DEFAULT_WORK_PACKAGE_NAME],
            ['status' => WorkPackageStatus::InProgress, 'sort_order' => 0],
        );

        // custom_id is a paid/optional ClickUp feature and can be null —
        // fall back to the ClickUp task id itself so the format never
        // degrades to "[] Name", same convention as ClickUpTaskDetailsSyncService.
        $customId = $payload['custom_id'] ?? $payload['id'] ?? $externalTaskId;

        $task = Task::create([
            'work_package_id' => $workPackage->id,
            'name' => "[{$customId}] {$clickUpName}",
            'description' => $payload['text_content'] ?? null,
            'status' => ($payload['status']['status'] ?? null) === 'complete' ? TaskStatus::Done : TaskStatus::Todo,
            'priority' => TaskPriority::Media,
            'external_id' => (string) ($payload['id'] ?? $externalTaskId),
            'url' => $payload['url'] ?? null,
        ]);

        activity('clickup-sync')
            ->on($task)
            ->withProperties([
                'provider' => 'clickup',
                'clickup_task_id' => $externalTaskId,
            ])
            ->log('Task importato da ClickUp');

        return $task;
    }

    private function logRaw(Project $project, string $externalTaskId, string $url, ?Response $response = null, ?string $error = null): void
    {
        Log::channel('sync')->info('ClickUp task import', [
            'provider' => 'clickup',
            'project_id' => $project->id,
            'clickup_task_id' => $externalTaskId,
            'url' => $url,
            'response_status' => $response?->status(),
            // The payload ClickUp returned — decoded when it is JSON, the raw
            // body otherwise (error pages, empty responses).
            'response_body' => $response?->json() ?? $response?->body(),
            'error' => $error,
        ]);
    }
}
