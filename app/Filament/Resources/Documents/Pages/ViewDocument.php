<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Concerns\TracksPageVisit;
use App\Filament\Resources\Documents\DocumentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDocument extends ViewRecord
{
    use TracksPageVisit;

    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
