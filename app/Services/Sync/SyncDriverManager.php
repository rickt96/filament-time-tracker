<?php

namespace App\Services\Sync;

use App\Enums\ClientSyncDriver;
use App\Models\Client;
use App\Services\Sync\Contracts\SyncDriverInterface;
use App\Services\Sync\Drivers\ClickUpDriver;
use App\Services\Sync\Drivers\H2BitDriver;
use App\Services\Sync\Drivers\JiraDriver;
use App\Services\Sync\Drivers\NullDriver;

/**
 * Resolves the correct SyncDriverInterface implementation for a Client's
 * configured sync_driver. This is the only place in the app allowed to
 * know the full list of available drivers — everything else depends only
 * on the SyncDriverInterface contract.
 */
class SyncDriverManager
{
    public function driverFor(Client $client): SyncDriverInterface
    {
        return match ($client->sync_driver) {
            ClientSyncDriver::ClickUp => new ClickUpDriver,
            ClientSyncDriver::Jira => new JiraDriver,
            ClientSyncDriver::H2Bit => new H2BitDriver,
            null => new NullDriver,
        };
    }
}
