<?php

namespace App\Services\Sync\Drivers;

use App\Models\TimeEntry;
use App\Services\Sync\Contracts\SyncDriverInterface;
use App\Services\Sync\Exceptions\SyncDriverNotImplementedException;
use App\Services\Sync\SyncResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ClickUp time tracking integration.
 *
 * @see https://developer.clickup.com/reference/createatimeentry
 *
 * Expected sync_configuration keys: token (a personal API token or OAuth
 * access token, sent as-is in the Authorization header — ClickUp does not
 * use a "Bearer " prefix), team (the ClickUp Team/Workspace id used in the
 * endpoint URL).
 */
class ClickUpDriver implements SyncDriverInterface
{
    private const string API_BASE_URL = 'https://api.clickup.com/api/v2';

    /**
     * @param  array<string, mixed>  $configuration
     */
    public function validateConfiguration(array $configuration): bool
    {
        return filled($configuration['api_key'] ?? null)
            && filled($configuration['team'] ?? null);
    }

    public function syncTimeEntry(TimeEntry $timeEntry): SyncResult
    {
        $taskId = $timeEntry->task?->external_id;
        $startedAt = $timeEntry->started_at;
        $endedAt = $timeEntry->ended_at;

        if (blank($taskId) || ! $startedAt || ! $endedAt) {
            return SyncResult::failure('Dati mancanti per la sincronizzazione (task esterno o orari non validi).');
        }

        $configuration = $timeEntry->project->client->sync_configuration ?? [];
        $url = self::API_BASE_URL."/team/{$configuration['team']}/time_entries";

        // ClickUp expects start/stop as unix timestamps in milliseconds.
        // Carbon::getTimestamp() is always the true UTC epoch second
        // regardless of which timezone the instance displays in, so no
        // explicit UTC conversion is needed here.
        $payload = [
            'start' => $startedAt->getTimestamp() * 1000,
            'stop' => $endedAt->getTimestamp() * 1000,
            'description' => (string) $timeEntry->description,
            'tid' => $taskId,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => $configuration['api_key'] ?? '',
                'Content-Type' => 'application/json',
            ])->post($url, $payload);
        } catch (Throwable $exception) {
            $this->logRaw($timeEntry, $url, $payload, error: $exception->getMessage());

            return SyncResult::failure("Errore di rete durante la chiamata a ClickUp: {$exception->getMessage()}");
        }

        $this->logRaw($timeEntry, $url, $payload, $response);

        if ($response->failed()) {
            return SyncResult::failure("ClickUp ha risposto con l'errore HTTP {$response->status()}: {$response->body()}");
        }

        return SyncResult::success();
    }

    public function testConnection(): bool
    {
        // TODO: call a lightweight authenticated ClickUp endpoint (e.g. GET /user)
        // to confirm the configured token is valid.
        throw new SyncDriverNotImplementedException(self::class);
    }

    /**
     * Raw request/response trail for every sync attempt, successful or
     * not — written to its own log channel so it can be audited without
     * digging through the general application log.
     *
     * @param  array<string, mixed>  $payload
     */
    private function logRaw(TimeEntry $timeEntry, string $url, array $payload, ?Response $response = null, ?string $error = null): void
    {
        $context = [
            'provider' => 'clickup',
            'time_entry_id' => $timeEntry->id,
            'clickup_task_id' => $timeEntry->task?->external_id,
            'url' => $url,
            'request' => $payload,
            'response_status' => $response?->status(),
            // The payload ClickUp returned — decoded when it is JSON, the raw
            // body otherwise (error pages, empty responses).
            'response_body' => $response?->json() ?? $response?->body(),
            'error' => $error,
        ];

        Log::channel('sync')->info('ClickUp time entry sync', $context);

        // Same trail, but hanging off the synced Time Entry so it can be
        // audited from the record itself instead of grepping the log file.
        activity('clickup-sync')
            ->on($timeEntry)
            ->withProperties([
                ...$context,
                'successful' => $error === null && $response?->successful() === true,
            ])
            ->log('Sincronizzazione time entry su ClickUp');
    }
}
