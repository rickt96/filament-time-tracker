<?php

namespace App\Services\Project;

class ProjectToWorkPackageTransferSummary
{
    public int $projectsTransferred = 0;

    public int $workPackagesMoved = 0;

    public int $timeEntriesMoved = 0;

    /**
     * @var array<int, string>
     */
    public array $warnings = [];

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }
}
