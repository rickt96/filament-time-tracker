<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\Sync\ClickUpTaskDetailsSyncService;
use Illuminate\Console\Command;

class SyncClickUpTaskDetailsCommand extends Command
{
    protected $signature = 'clickup:sync-task-details
        {project : ID of the local Project whose Tasks should be refreshed from ClickUp}
        {--dry-run : Preview what would change without writing anything to the database}';

    protected $description = "Refresh a Project's Tasks from ClickUp: preserve the old local name as a note in the description, rename using ClickUp's custom_id + name, and set the Task url";

    public function handle(ClickUpTaskDetailsSyncService $service): int
    {
        $projectId = (int) $this->argument('project');
        $project = Project::find($projectId);

        if (! $project) {
            $this->error("Nessun progetto locale trovato con id [{$projectId}].");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modalità dry-run: nessun task verrà modificato, verranno solo mostrati i conteggi previsti.');
        }

        $summary = $service->sync($project, $dryRun, onProgress: fn (string $message) => $this->line($message));

        $this->newLine();
        $this->info('Sincronizzazione completata'.($dryRun ? ' (dry-run, nulla è stato salvato):' : ':'));
        $this->table(['Elemento', 'Conteggio'], [
            ['Task totali nel progetto', $summary->tasksTotal],
            ['Task senza ID ClickUp', $summary->tasksWithoutExternalId],
            ['Task aggiornati', $summary->tasksUpdated],
            ['Task saltati', $summary->tasksSkipped],
            ['Task falliti', $summary->tasksFailed],
        ]);

        if ($summary->warnings !== []) {
            $this->newLine();
            $this->warn(count($summary->warnings).' avvisi:');

            foreach ($summary->warnings as $warning) {
                $this->line("  - {$warning}");
            }
        }

        return $summary->tasksFailed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
