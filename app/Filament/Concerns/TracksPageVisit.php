<?php

namespace App\Filament\Concerns;

use App\Models\PageVisit;
use Illuminate\Database\Eloquent\Model;

/**
 * Registra l'ultimo momento in cui l'utente loggato ha aperto la pagina di un record.
 * È un hook di mount di Livewire: gira una sola volta per apertura pagina, dopo il
 * mount() della pagina, quindi getRecord() è già risolto. Non scatta sulle action.
 */
trait TracksPageVisit
{
    public function mountTracksPageVisit(): void
    {
        if (! method_exists($this, 'getRecord')) {
            return;
        }

        $record = $this->getRecord();

        PageVisit::recordVisit($record instanceof Model ? $record : null);
    }
}
