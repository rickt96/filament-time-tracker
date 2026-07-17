<?php

namespace App\Exports;

use App\Models\TimeEntry;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * @implements WithMapping<TimeEntry>
 */
class TimeEntriesExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @param  Collection<int, TimeEntry>  $entries
     */
    public function __construct(private readonly Collection $entries) {}

    /**
     * @return Collection<int, TimeEntry>
     */
    public function collection(): Collection
    {
        return $this->entries;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Data', 'Progetto', 'Cliente', 'Utente', 'Task', 'Descrizione', 'Durata (h)', 'Tariffa', 'Importo'];
    }

    /**
     * @return array<int, mixed>
     */
    public function map($entry): array
    {
        /** @var TimeEntry $entry */
        return [
            $entry->date->toDateString(),
            $entry->project->name,
            $entry->client?->name,
            $entry->user->name,
            $entry->task?->name,
            $entry->description,
            round($entry->duration_seconds / 3600, 2),
            $entry->hourly_rate,
            $entry->total_amount,
        ];
    }
}
