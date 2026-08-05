<?php

namespace App\Console\Commands;

use App\Services\Notion\NotionApiException;
use App\Services\Notion\NotionClient;
use App\Services\Notion\NotionPageBodyRenderer;
use App\Services\Notion\NotionTaskImportService;
use Illuminate\Console\Command;

class ImportNotionTasksCommand extends Command
{
    protected $signature = 'notion:import-tasks
        {--project= : id di progetto locale a cui limitare l\'import}
        {--with-body : Legge anche il testo dentro le pagine Notion (lento: almeno una chiamata API in più per task)}
        {--dry-run : Mostra cosa verrebbe importato senza scrivere nulla nel database}';

    protected $description = 'Importa i task dal database Notion "Task" nei Task locali, sotto un Work Package "Import Notion" per progetto';

    public function handle(): int
    {
        $token = config('services.notion.token');

        if (blank($token)) {
            $this->error('NOTION_API_TOKEN non è impostata. Aggiungila al file .env e riprova.');

            return self::FAILURE;
        }

        $project = $this->option('project');
        $onlyProjectIds = filled($project) ? [(int) $project] : [];
        $withBody = (bool) $this->option('with-body');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modalità dry-run: nessun dato verrà scritto, verranno solo mostrati i conteggi previsti.');
        }

        if ($onlyProjectIds !== []) {
            $this->line('Import limitato al progetto locale '.implode(', ', $onlyProjectIds).'.');
        }

        $client = new NotionClient(
            $token,
            config('services.notion.base_url'),
            '2022-06-28',
        );

        $service = new NotionTaskImportService($client, new NotionPageBodyRenderer($client));

        try {
            $summary = $service->import(
                dryRun: $dryRun,
                onlyProjectIds: $onlyProjectIds,
                withBody: $withBody,
                onProgress: fn (string $message) => $this->line($message),
            );
        } catch (NotionApiException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Importazione completata'.($dryRun ? ' (dry-run, nulla è stato salvato):' : ':'));
        $this->table(['Elemento', 'Conteggio'], [
            ['Pagine Notion lette', $summary->pagesFetched],
            ['Pagine archiviate (ignorate)', $summary->pagesArchived],
            ['Work Package creati', $summary->workPackagesCreated],
            ['Task creati', $summary->tasksCreated],
            ['Task aggiornati', $summary->tasksUpdated],
            ['di cui agganciati a task già esistenti', $summary->tasksAdopted],
            ['Task saltati (progetto non mappato)', $summary->tasksSkippedUnmappedProject],
            ['Task saltati (senza titolo)', $summary->tasksSkippedWithoutTitle],
            ['Task saltati (cancellati in locale)', $summary->tasksSkippedTrashed],
            ['Corpi pagina non letti', $summary->bodiesFailed],
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
