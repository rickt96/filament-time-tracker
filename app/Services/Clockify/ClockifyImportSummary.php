<?php

namespace App\Services\Clockify;

class ClockifyImportSummary
{
    public bool $userCreated = false;

    public int $clientsImported = 0;

    public int $projectsImported = 0;

    public int $tasksImported = 0;

    public int $timeEntriesImported = 0;

    public int $timeEntriesSkipped = 0;

    /**
     * @var array<int, string>
     */
    public array $warnings = [];

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }
}
