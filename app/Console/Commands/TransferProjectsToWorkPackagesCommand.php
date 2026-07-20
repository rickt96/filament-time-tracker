<?php

namespace App\Console\Commands;

use App\Services\Project\ProjectToWorkPackageTransferService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class TransferProjectsToWorkPackagesCommand extends Command
{
    /**
     * Id del progetto "master" che riceverà i Work Package (e le relative
     * Time Entry) dei progetti sorgente. Impostare prima di ogni esecuzione.
     */
    private const int MASTER_PROJECT_ID = 0;

    /**
     * Id dei progetti "sorgente" da consolidare nel master — uno per ogni
     * vecchio progetto usato in passato come fosse un Work Package (es. una
     * riga per anno). Impostare prima di ogni esecuzione, poi lanciare il
     * comando una volta per ciascun gruppo di progetti da consolidare.
     *
     * @var array<int, int>
     */
    private const array SOURCE_PROJECT_IDS = [];

    protected $signature = 'projects:transfer-to-work-packages
        {--dry-run : Mostra cosa verrebbe spostato senza scrivere nulla nel database}';

    protected $description = 'Consolida i progetti sorgente (MASTER_PROJECT_ID/SOURCE_PROJECT_IDS in cima al comando) come Work Package di un progetto master';

    public function handle(ProjectToWorkPackageTransferService $service): int
    {
        if (self::MASTER_PROJECT_ID === 0 || self::SOURCE_PROJECT_IDS === []) {
            $this->error('Imposta MASTER_PROJECT_ID e SOURCE_PROJECT_IDS in cima a TransferProjectsToWorkPackagesCommand prima di eseguirlo.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modalità dry-run: nessuna modifica verrà salvata, verranno solo mostrati i conteggi previsti.');
        }

        try {
            $summary = $service->transfer(
                masterProjectId: self::MASTER_PROJECT_ID,
                sourceProjectIds: self::SOURCE_PROJECT_IDS,
                dryRun: $dryRun,
                onProgress: fn (string $message) => $this->line($message),
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Trasferimento completato'.($dryRun ? ' (dry-run, nulla è stato salvato):' : ':'));
        $this->table(['Elemento', 'Conteggio'], [
            ['Progetti trasferiti', $summary->projectsTransferred],
            ['Work Package spostati', $summary->workPackagesMoved],
            ['Time entry spostate', $summary->timeEntriesMoved],
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
