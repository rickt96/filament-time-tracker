<?php

namespace App\Services\Sync\Drivers;

use App\Models\TimeEntry;
use App\Services\Sync\Contracts\SyncDriverInterface;
use App\Services\Sync\SyncResult;

/**
 * Used when a Client has no sync_driver configured. Always a no-op so
 * callers never need to special-case "no driver" with conditionals.
 */
class NullDriver implements SyncDriverInterface
{
    /**
     * @param  array<string, mixed>  $configuration
     */
    public function validateConfiguration(array $configuration): bool
    {
        return true;
    }

    public function syncTimeEntry(TimeEntry $timeEntry): SyncResult
    {
        return SyncResult::failure('Il cliente non ha un provider di sincronizzazione configurato.');
    }

    public function testConnection(): bool
    {
        return false;
    }
}
