<?php

namespace App\Console\Commands;

use App\Services\Sync\ClickUpTaskNameSyncService;
use Illuminate\Console\Command;

class SyncClickUpTaskNamesCommand extends Command
{
    protected $signature = 'clickup:sync-task-names
        {--dry-run : Preview what would be updated without writing anything to the database}';

    protected $description = 'Refresh local Task names from ClickUp, for every Task with a ClickUp external_id under a Project whose Client uses the ClickUp sync driver';

    public function handle(ClickUpTaskNameSyncService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modalità dry-run: nessun task verrà modificato, verranno solo mostrati i conteggi previsti.');
        }

        $summary = $service->sync($dryRun, onProgress: fn (string $message) => $this->line($message));

        $this->newLine();
        $this->info('Sincronizzazione completata'.($dryRun ? ' (dry-run, nulla è stato salvato):' : ':'));
        $this->table(['Elemento', 'Conteggio'], [
            ['Progetti analizzati', $summary->projectsScanned],
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
