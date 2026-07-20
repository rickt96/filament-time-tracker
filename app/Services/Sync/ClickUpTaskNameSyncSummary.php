<?php

namespace App\Services\Sync;

class ClickUpTaskNameSyncSummary
{
    public int $projectsScanned = 0;

    public int $tasksUpdated = 0;

    public int $tasksSkipped = 0;

    public int $tasksFailed = 0;

    /**
     * @var array<int, string>
     */
    public array $warnings = [];

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }
}
