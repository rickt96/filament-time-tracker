<?php

namespace App\Services\Invoice;

use App\Models\Invoice;
use App\Models\TimeEntry;
use Illuminate\Support\Collection;

class AttachTimeEntriesToInvoiceService
{
    /**
     * Links the given Time Entries to the Invoice, without disturbing any
     * Time Entry already attached to it (e.g. from a previous batch) — a
     * Time Entry already on this same Invoice is left as-is rather than
     * erroring or duplicating the pivot row.
     *
     * @param  Collection<int, TimeEntry>  $timeEntries
     * @return int number of Time Entries newly attached (already-attached ones don't count)
     */
    public function attach(Collection $timeEntries, Invoice $invoice): int
    {
        $result = $invoice->timeEntries()->syncWithoutDetaching($timeEntries->pluck('id'));

        return count($result['attached']);
    }
}
