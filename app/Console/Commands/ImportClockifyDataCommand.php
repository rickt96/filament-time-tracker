<?php

namespace App\Console\Commands;

use App\Actions\TimeEntry\CreateTimeEntryAction;
use App\Models\Workspace;
use App\Services\Clockify\ClockifyApiException;
use App\Services\Clockify\ClockifyClient;
use App\Services\Clockify\ClockifyImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ImportClockifyDataCommand extends Command
{
    protected $signature = 'clockify:import
        {clockify-workspace : Clockify workspace ID to import from}
        {--workspace= : Local Workspace ID to import into (required)}
        {--user-email=admin@mail.com : All imported time entries are attributed to this local user; created automatically if missing}
        {--from= : Only import time entries on/after this date (Y-m-d)}
        {--until= : Only import time entries on/before this date (Y-m-d)}
        {--dry-run : Preview counts without writing anything to the database}';

    protected $description = 'Import clients, projects, tasks and time entries from a Clockify workspace';

    public function handle(): int
    {
        $apiKey = config('services.clockify.api_key');

        if (blank($apiKey)) {
            $this->error('CLOCKIFY_API_KEY non è impostata. Aggiungila al file .env e riprova.');

            return self::FAILURE;
        }

        $workspaceId = $this->option('workspace');

        if (blank($workspaceId)) {
            $this->error('Specifica il workspace locale di destinazione con --workspace=<id>.');

            return self::FAILURE;
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace) {
            $this->error("Nessun Workspace locale trovato con id [{$workspaceId}].");

            return self::FAILURE;
        }

        $from = $this->parseDateOption('from');
        $until = $this->parseDateOption('until');

        if ($from === false || $until === false) {
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modalità dry-run: nessun dato verrà scritto, verranno solo mostrati i conteggi previsti.');
        }

        $client = new ClockifyClient($apiKey, config('services.clockify.base_url'));
        $service = new ClockifyImportService($client, app(CreateTimeEntryAction::class));

        try {
            $summary = $service->import(
                workspace: $workspace,
                clockifyWorkspaceId: $this->argument('clockify-workspace'),
                userEmail: $this->option('user-email'),
                from: $from,
                until: $until,
                dryRun: $dryRun,
                onProgress: fn (string $message) => $this->line($message),
            );
        } catch (ClockifyApiException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Import completato'.($dryRun ? ' (dry-run, nulla è stato salvato):' : ':'));
        $this->table(['Elemento', 'Conteggio'], [
            ['Utente locale creato', $summary->userCreated ? 'sì' : 'no'],
            ['Clienti importati', $summary->clientsImported],
            ['Progetti importati', $summary->projectsImported],
            ['Task importati', $summary->tasksImported],
            ['Time entry importate', $summary->timeEntriesImported],
            ['Time entry saltate', $summary->timeEntriesSkipped],
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

    /**
     * @return Carbon|false|null false on invalid input (error already printed)
     */
    private function parseDateOption(string $option): Carbon|false|null
    {
        $value = $this->option($option);

        if (blank($value) || ! is_string($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            $this->error("Valore non valido per --{$option}: [{$value}]. Usa il formato AAAA-MM-GG.");

            return false;
        }
    }
}
