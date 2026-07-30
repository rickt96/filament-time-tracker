<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

#[Signature('app:import-invoice')]
#[Description('Command description')]
class ImportInvoice extends Command
{
    private const CSV_PATH = 'D:\\Download\\34a557da-9e4c-46f7-bca4-98529ffeebf5_ExportBlock-5bdb239e-f03f-478a-b9e1-68297e8b76f3\\ExportBlock-5bdb239e-f03f-478a-b9e1-68297e8b76f3-Part-1\\Fatture 2701cdc26f9a81c2854eea0e717cf553.csv';

    private const STATUS_MAP = [
        'Incassata' => 'collected',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $lines = file(self::CSV_PATH);

        if ($lines === false) {
            throw new RuntimeException('Impossibile leggere il file CSV: '.self::CSV_PATH);
        }

        $rows = array_map('str_getcsv', $lines);
        array_shift($rows); // header

        $projectMap = Project::query()->pluck('id', 'name');
        $clientMap = Client::query()->pluck('id', 'name');

        DB::transaction(function () use ($rows, $projectMap, $clientMap): void {
            // Re-running the import must start from a clean slate. A plain
            // delete() on a SoftDeletes model only stamps deleted_at, so the
            // old rows stick around and collide with the reinserted
            // (workspace_id, year, number) combos on the unique index —
            // forceDelete removes them for good.
            DB::table('invoice_project')->delete();
            Invoice::query()->forceDelete();

            foreach ($rows as $rowNumber => $row) {
                if (count($row) < 8) {
                    continue;
                }

                [$anno, $numero, $destinatario, $progetto, $importo, $stato, $dataInvio, $dataIncasso, $note] = array_pad($row, 9, '');

                $destinatario = trim($destinatario);
                $clientId = $clientMap[$destinatario] ?? null;

                if ($clientId === null) {
                    throw new RuntimeException('Riga '.($rowNumber + 2).": cliente \"{$destinatario}\" non trovato.");
                }

                $invoice = Invoice::create([
                    'workspace_id' => 1,
                    'client_id' => $clientId,
                    'year' => (int) trim($anno),
                    'number' => (int) trim($numero),
                    'amount' => $this->toAmount($importo),
                    'status' => self::STATUS_MAP[trim($stato)] ?? 'draft',
                    'sent_at' => $this->toDate($dataInvio),
                    'collected_at' => $this->toDate($dataIncasso),
                    'note' => trim($note),
                ]);

                foreach (explode(', ', trim($progetto)) as $projectName) {
                    if ($projectName === '') {
                        continue;
                    }

                    $projectId = $projectMap[$projectName] ?? null;

                    if ($projectId === null) {
                        $project = Project::create([
                            'workspace_id' => 1,
                            'client_id' => $clientId,
                            'name' => $projectName,
                        ]);

                        $projectId = $project->id;
                        $projectMap->put($projectName, $projectId);

                        $this->components->info('Riga '.($rowNumber + 2).": progetto \"{$projectName}\" creato.");
                    }

                    $invoice->projects()->syncWithoutDetaching($projectId);
                }
            }
        });

        $this->components->info('Import completato: '.Invoice::query()->count().' fatture.');

        return self::SUCCESS;
    }

    private function toDate(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        [$y, $m, $d] = explode('/', $v);

        return sprintf('%04d-%02d-%02d', (int) $y, (int) $m, (int) $d);
    }

    private function toAmount(string $v): string
    {
        $v = trim($v);
        $v = str_replace('€', '', $v);
        $v = str_replace(',', '', $v);

        return number_format((float) $v, 2, '.', '');
    }
}
