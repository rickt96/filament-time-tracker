<?php

namespace App\Services\Sync\Drivers;

use App\Models\TimeEntry;
use App\Services\Sync\Contracts\SyncDriverInterface;
use App\Services\Sync\Exceptions\SyncDriverNotImplementedException;
use App\Services\Sync\SyncResult;

/**
 * Scaffolding only — the actual Jira API integration (HTTP client,
 * authentication, request/response mapping) is implemented separately.
 *
 * Expected sync_configuration keys (subject to change once the real
 * integration is built): base_url, email, api_token.
 */
class JiraDriver implements SyncDriverInterface
{
    /**
     * @param  array<string, mixed>  $configuration
     */
    public function validateConfiguration(array $configuration): bool
    {
        return filled($configuration['base_url'] ?? null)
            && filled($configuration['email'] ?? null)
            && filled($configuration['api_token'] ?? null);
    }

    public function syncTimeEntry(TimeEntry $timeEntry): SyncResult
    {
        // TODO: call the Jira "worklog" API for $timeEntry->task->import_clickup_id,
        // authenticating with the Client's sync_configuration (email + api_token).
        throw new SyncDriverNotImplementedException(self::class);
    }

    public function testConnection(): bool
    {
        // TODO: call a lightweight authenticated Jira endpoint (e.g. GET /myself)
        // to confirm the configured credentials are valid.
        throw new SyncDriverNotImplementedException(self::class);
    }
}
