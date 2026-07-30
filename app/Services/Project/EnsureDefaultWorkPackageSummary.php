<?php

namespace App\Services\Project;

class EnsureDefaultWorkPackageSummary
{
    public int $workPackagesCreated = 0;

    public int $timeEntriesMoved = 0;

    public int $tasksMoved = 0;

    /**
     * @var array<int, string>
     */
    public array $warnings = [];

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }
}
