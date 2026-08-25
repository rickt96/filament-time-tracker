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
 * H2Bit ("Presenzialista API v1") time tracking integration.
 *
 * Unlike ClickUp, H2Bit is self-hosted per customer, so the API host itself
 * is part of the Client's sync_configuration rather than a fixed constant.
 *
 * Expected sync_configuration keys: api_key (a Sanctum personal access
 * token, sent as a Bearer token), organization (the organization id used in
 * every endpoint path), base_url (the instance's host, no trailing slash —
 * e.g. "https://manager.test").
 */
class H2BitDriver implements SyncDriverInterface
{
    /**
     * @param  array<string, mixed>  $configuration
     */
    public function validateConfiguration(array $configuration): bool
    {
        return filled($configuration['api_key'] ?? null)
            && filled($configuration['organization'] ?? null)
            && filled($configuration['base_url'] ?? null);
    }

    public function syncTimeEntry(TimeEntry $timeEntry): SyncResult
    {
        $taskId = $timeEntry->task?->external_id;
        $endedAt = $timeEntry->ended_at;

        if (blank($taskId) || ! $endedAt) {
            return SyncResult::failure('Dati mancanti per la sincronizzazione (task esterno o orario di fine non valido).');
        }

        $configuration = $timeEntry->project->client->sync_configuration ?? [];

        if (! $this->validateConfiguration($configuration)) {
            return SyncResult::failure('Configurazione H2Bit incompleta (api_key/organization/base_url).');
        }

        $url = $this->buildUrl($configuration, "/organizations/{$configuration['organization']}/task-clocks");

        // Passing both started_at and ended_at records the interval
        // directly as already-concluded, without starting a live timer on
        // H2Bit's side (see "Registra un intervallo concluso" in the API
        // collection). paused_seconds is omitted: this app doesn't track
        // pause time on a Time Entry.
        $payload = [
            'project_task_id' => $taskId,
            'description' => (string) $timeEntry->description,
            'started_at' => $timeEntry->started_at->toIso8601String(),
            'ended_at' => $endedAt->toIso8601String(),
        ];

        try {
            $response = Http::withToken($configuration['api_key'])
                ->acceptJson()
                ->post($url, $payload);
        } catch (Throwable $exception) {
            $this->logRaw($timeEntry, $url, $payload, error: $exception->getMessage());

            return SyncResult::failure("Errore di rete durante la chiamata a H2Bit: {$exception->getMessage()}");
        }

        $this->logRaw($timeEntry, $url, $payload, $response);

        if ($response->failed()) {
            return SyncResult::failure("H2Bit ha risposto con l'errore HTTP {$response->status()}: {$response->body()}");
        }

        return SyncResult::success();
    }

    public function testConnection(): bool
    {
        // TODO: this interface method receives no configuration to call
        // with — same limitation as every other driver here. GET /user is
        // the documented health-check endpoint (401 = bad/revoked key) once
        // a configuration can be threaded through.
        throw new SyncDriverNotImplementedException(self::class);
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function buildUrl(array $configuration, string $path): string
    {
        return rtrim((string) $configuration['base_url'], '/')."/api/v1{$path}";
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
            'provider' => 'h2bit',
            'time_entry_id' => $timeEntry->id,
            'h2bit_task_id' => $timeEntry->task?->external_id,
            'url' => $url,
            'request' => $payload,
            'response_status' => $response?->status(),
            // The payload H2Bit returned — decoded when it is JSON, the raw
            // body otherwise (error pages, empty responses).
            'response_body' => $response?->json() ?? $response?->body(),
            'error' => $error,
        ];

        Log::channel('sync')->info('H2Bit time entry sync', $context);

        // Same trail, but hanging off the synced Time Entry so it can be
        // audited from the record itself instead of grepping the log file.
        activity('h2bit-sync')
            ->on($timeEntry)
            ->withProperties([
                ...$context,
                'successful' => $error === null && $response?->successful() === true,
            ])
            ->log('Sincronizzazione time entry su H2Bit');
    }
}
