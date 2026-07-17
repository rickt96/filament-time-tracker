<?php

namespace App\Services\Sync\Contracts;

use App\Models\TimeEntry;
use App\Services\Sync\SyncResult;

/**
 * Strategy contract for a single external time-tracking provider
 * (ClickUp, Jira, ...). All provider-specific logic — request shape,
 * authentication, endpoint URLs — must live entirely inside the
 * concrete driver. Nothing outside a driver should branch on
 * "which provider is this" (see App\Services\Sync\SyncDriverManager).
 */
interface SyncDriverInterface
{
    /**
     * Validate the Client's sync_configuration is structurally usable by
     * this driver (required keys present, etc.) before attempting a sync.
     *
     * @param  array<string, mixed>  $configuration
     */
    public function validateConfiguration(array $configuration): bool;

    /**
     * Push a single Time Entry to the external provider. The Time Entry's
     * Task must already carry the import_clickup_id this provider understands —
     * this app never creates remote tasks, only maps to existing ones.
     */
    public function syncTimeEntry(TimeEntry $timeEntry): SyncResult;

    /**
     * A lightweight connectivity/credentials check against the provider,
     * independent of any specific Time Entry.
     */
    public function testConnection(): bool;
}
