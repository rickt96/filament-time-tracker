<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('app:consolidate-invoice-notes {path : Cartella contenente i file .md delle fatture}')]
#[Description('Consolida le note delle fatture a partire dai file .md esportati (uno per fattura)')]
class ConsolidateInvoiceNotesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var string $path */
        $path = $this->argument('path');

        if (! is_dir($path)) {
            throw new RuntimeException("Cartella non trovata: {$path}");
        }

        $files = glob(rtrim($path, '/\\').'/*.md') ?: [];

        $updated = 0;
        $skipped = 0;

        foreach ($files as $file) {
            $content = file_get_contents($file);

            if ($content === false) {
                $this->components->warn(basename($file).': impossibile leggere il file, saltato.');
                $skipped++;

                continue;
            }

            if (! preg_match('/^#\s*(\d+)/m', $content, $numberMatch)) {
                $this->components->warn(basename($file).': numero fattura non trovato, saltato.');
                $skipped++;

                continue;
            }

            if (! preg_match('/^Anno:\s*(\d{4})/m', $content, $yearMatch)) {
                $this->components->warn(basename($file).': anno fattura non trovato, saltato.');
                $skipped++;

                continue;
            }

            $number = (int) $numberMatch[1];
            $year = (int) $yearMatch[1];

            $invoice = Invoice::query()
                ->where('year', $year)
                ->where('number', $number)
                ->first();

            if (! $invoice) {
                $this->components->warn(basename($file).": fattura {$year}/{$number} non trovata, saltata.");
                $skipped++;

                continue;
            }

            $notes = $invoice->note;

            $extracted = $this->extractNote($content);
            if ($extracted != null) {
                if ($notes != null) {
                    $notes .= "\n### IMPORT\n";
                }
                $notes .= $extracted;
            }

            $this->info($notes);

            $invoice->update([
                'note' => $notes,
            ]);

            $updated++;

            $this->newLine(2);
            $this->line('===================');
        }

        $this->components->info("Note consolidate: {$updated} aggiornate, {$skipped} saltate.");

        return self::SUCCESS;
    }

    private function extractNote(string $content): ?string
    {
        $lines = preg_split('/\R/', $content) ?: [];

        $startIndex = null;

        foreach ($lines as $index => $line) {
            if (str_starts_with(trim($line), 'Creata il:')) {
                $startIndex = $index + 1;

                break;
            }
        }

        if ($startIndex === null) {
            return null;
        }

        $note = trim(implode("\n", array_slice($lines, $startIndex)));

        return $note !== '' ? $note : null;
    }
}
