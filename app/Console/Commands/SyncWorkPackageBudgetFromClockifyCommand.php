<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\Clockify\ClockifyApiException;
use App\Services\Clockify\ClockifyClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:sync-workpackage-budget-from-clockify
    {clockify-workspace : Clockify workspace ID}
    {--dry-run : Preview matches/values without writing anything to the database}')]
#[Description('Per ogni progetto locale trovato su Clockify (match per nome), copia budget ore e tariffa oraria su tutti i suoi WorkPackage')]
class SyncWorkPackageBudgetFromClockifyCommand extends Command
{
    public function handle(): int
    {
        $apiKey = config('services.clockify.api_key');

        if (blank($apiKey)) {
            $this->components->error('CLOCKIFY_API_KEY non è impostata.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->components->warn('Modalità dry-run: nessun dato verrà scritto.');
        }

        $client = new ClockifyClient($apiKey, config('services.clockify.base_url'));

        try {
            /** @var string $clockifyWorkspaceId */
            $clockifyWorkspaceId = $this->argument('clockify-workspace');
            $clockifyProjects = $client->projects($clockifyWorkspaceId);
        } catch (ClockifyApiException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        // Matched by name (trimmed, case-insensitive) rather than by a
        // persisted external id: no clockify_id column exists locally, and
        // this mirrors how ClockifyImportService itself matches Projects.
        $projectsByName = [];

        foreach ($clockifyProjects as $clockifyProject) {
            $key = mb_strtolower(trim((string) $clockifyProject['name']));

            if (isset($projectsByName[$key])) {
                $this->components->warn("Più progetti Clockify con nome \"{$clockifyProject['name']}\", uso il primo trovato.");

                continue;
            }

            $projectsByName[$key] = $clockifyProject;
        }

        $matched = 0;
        $skipped = 0;
        $workPackagesUpdated = 0;

        DB::transaction(function () use ($projectsByName, $dryRun, &$matched, &$skipped, &$workPackagesUpdated): void {
            foreach (Project::query()->with('workPackages')->get() as $project) {
                $key = mb_strtolower(trim($project->name));
                $clockifyProject = $projectsByName[$key] ?? null;

                if (! $clockifyProject) {
                    $this->components->warn("Progetto \"{$project->name}\": nessuna corrispondenza su Clockify, saltato.");
                    $skipped++;

                    continue;
                }

                $budgetHours = $this->extractBudgetHours($clockifyProject);
                $hourlyRate = $this->extractHourlyRate($clockifyProject);

                $this->components->info(
                    "Progetto \"{$project->name}\" -> budget: ".($budgetHours ?? '—').'h, tariffa: '.($hourlyRate ?? '—'),
                );

                $matched++;

                foreach ($project->workPackages as $workPackage) {
                    $updates = array_filter([
                        'budget_hours' => $budgetHours,
                        'hourly_rate' => $hourlyRate,
                    ], fn (?string $value): bool => $value !== null);

                    if ($updates === []) {
                        continue;
                    }

                    $workPackage->update($updates);
                    $workPackagesUpdated++;
                }
            }

            if ($dryRun) {
                DB::rollBack();
            }
        });

        $this->newLine();
        $this->components->info("Progetti abbinati: {$matched}, saltati: {$skipped}, WorkPackage aggiornati: {$workPackagesUpdated}.");

        return self::SUCCESS;
    }

    /**
     * Only a "MANUAL" estimate is a real user-set budget ("AUTO" just
     * mirrors tracked time), and a 0-hour estimate is treated as unset
     * rather than as an explicit zero budget.
     *
     * @param  array<string, mixed>  $clockifyProject
     */
    private function extractBudgetHours(array $clockifyProject): ?string
    {
        $timeEstimate = $clockifyProject['timeEstimate'] ?? null;

        if (! is_array($timeEstimate) || ($timeEstimate['type'] ?? null) !== 'MANUAL') {
            return null;
        }

        $hours = $this->parseIsoDurationToHours($timeEstimate['estimate'] ?? null);

        if ($hours === null || $hours <= 0.0) {
            return null;
        }

        return number_format($hours, 2, '.', '');
    }

    /**
     * Same cents-based representation and fallback as
     * ClockifyImportService::rateToDecimalString().
     *
     * @param  array<string, mixed>  $clockifyProject
     */
    private function extractHourlyRate(array $clockifyProject): ?string
    {
        $rate = $clockifyProject['hourlyRate'] ?? null;

        if (! is_array($rate) || ! isset($rate['amount'])) {
            return null;
        }

        return number_format(((int) $rate['amount']) / 100, 2, '.', '');
    }

    private function parseIsoDurationToHours(?string $duration): ?float
    {
        if (blank($duration) || ! preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $duration, $matches)) {
            return null;
        }

        $hours = (int) ($matches[1] ?? 0);
        $minutes = (int) ($matches[2] ?? 0);
        $seconds = (int) ($matches[3] ?? 0);

        return round($hours + ($minutes / 60) + ($seconds / 3600), 2);
    }
}
