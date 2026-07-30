<?php

namespace App\Services\Sync;

class ClickUpTaskDetailsSyncSummary
{
    public int $tasksTotal = 0;

    public int $tasksWithoutExternalId = 0;

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
