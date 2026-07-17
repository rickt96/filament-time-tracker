<?php

namespace App\Services\Sync\Exceptions;

use RuntimeException;

/**
 * Thrown by driver stubs whose real API integration hasn't been built yet.
 * SyncTimeEntryAction catches this like any other sync failure and records
 * it on the Time Entry as a normal "failed" sync with this message, so the
 * rest of the sync pipeline (status tracking, bulk actions, retries) is
 * fully exercised even before a driver's HTTP calls are implemented.
 */
class SyncDriverNotImplementedException extends RuntimeException
{
    public function __construct(string $driver)
    {
        parent::__construct("Il driver di sincronizzazione [{$driver}] non è ancora implementato.");
    }
}
