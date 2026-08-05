<?php

namespace App\Services\Notion;

class NotionTaskImportSummary
{
    public int $pagesFetched = 0;

    public int $pagesArchived = 0;

    public int $tasksCreated = 0;

    public int $tasksUpdated = 0;

    /** Pages whose Notion Project has no internal_id, or none at all. */
    public int $tasksSkippedUnmappedProject = 0;

    /** Pages with an empty title — nothing usable to name a Task with. */
    public int $tasksSkippedWithoutTitle = 0;

    /** Pages whose local Task was soft-deleted: the deletion is respected. */
    public int $tasksSkippedTrashed = 0;

    /** Pages whose body couldn't be read: the description is left untouched. */
    public int $bodiesFailed = 0;

    /**
     * Pages matched to a Task that already existed — imported from Clockify,
     * say — through the DevOps link rather than through a notion_id.
     */
    public int $tasksAdopted = 0;

    public int $workPackagesCreated = 0;

    /**
     * @var array<int, string>
     */
    public array $warnings = [];

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }
}
