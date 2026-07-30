<?php

namespace App\Filament\Resources\WorkPackages\Pages;

use App\Filament\Resources\WorkPackages\WorkPackageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListWorkPackages extends ListRecords
{
    protected static string $resource = WorkPackageResource::class;

    protected Width|string|null $maxContentWidth = 'full';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
