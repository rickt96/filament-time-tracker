<?php

namespace App\Console\Commands;

use App\Services\Project\EnsureDefaultWorkPackageService;
use Illuminate\Console\Command;

class CreateDefaultWorkPackagesCommand extends Command
{
    protected $signature = 'projects:create-default-work-packages
        {--dry-run : Mostra cosa verrebbe creato/spostato senza scrivere nulla nel database}';

    protected $description = 'Crea un Work Package (con lo stesso nome del progetto) per ogni progetto che non ne ha ancora nessuno, migrando le relative time entry e task';

    public function handle(EnsureDefaultWorkPackageService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modalità dry-run: nessuna modifica verrà salvata, verranno solo mostrati i conteggi previsti.');
        }

        $summary = $service->run(
            dryRun: $dryRun,
            onProgress: fn (string $message) => $this->line($message),
        );

        $this->newLine();
        $this->info('Operazione completata'.($dryRun ? ' (dry-run, nulla è stato salvato):' : ':'));
        $this->table(['Elemento', 'Conteggio'], [
            ['Work Package creati', $summary->workPackagesCreated],
            ['Time entry migrate', $summary->timeEntriesMoved],
            ['Task migrati', $summary->tasksMoved],
        ]);

        if ($summary->warnings !== []) {
            $this->newLine();
            $this->warn(count($summary->warnings).' avvisi:');

            foreach ($summary->warnings as $warning) {
                $this->line("  - {$warning}");
            }
        }

        return self::SUCCESS;
    }
}
