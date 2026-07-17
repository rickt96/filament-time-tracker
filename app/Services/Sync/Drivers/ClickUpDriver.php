<?php

namespace App\Services\Sync\Drivers;

use App\Models\TimeEntry;
use App\Services\Sync\Contracts\SyncDriverInterface;
use App\Services\Sync\Exceptions\SyncDriverNotImplementedException;
use App\Services\Sync\SyncResult;

/**
 * Scaffolding only — the actual ClickUp API integration (HTTP client,
 * authentication, request/response mapping) is implemented separately.
 *
 * Expected sync_configuration keys (subject to change once the real
 * integration is built): api_token, list_id.
 */
class ClickUpDriver implements SyncDriverInterface
{
    /**
     * @param  array<string, mixed>  $configuration
     */
    public function validateConfiguration(array $configuration): bool
    {
        return filled($configuration['api_token'] ?? null)
            && filled($configuration['list_id'] ?? null);
    }

    public function syncTimeEntry(TimeEntry $timeEntry): SyncResult
    {
        // TODO: call the ClickUp "track time" API for $timeEntry->task->import_clickup_id,
        // authenticating with the Client's sync_configuration['api_token'].
        throw new SyncDriverNotImplementedException(self::class);
    }

    public function testConnection(): bool
    {
        // TODO: call a lightweight authenticated ClickUp endpoint (e.g. GET /user)
        // to confirm the configured token is valid.
        throw new SyncDriverNotImplementedException(self::class);
    }
}
