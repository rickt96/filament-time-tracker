<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Concerns\TracksPageVisit;
use App\Filament\Resources\Documents\DocumentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDocument extends EditRecord
{
    use TracksPageVisit;

    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()];
    }
}
