<?php

namespace App\Filament\Resources\WorkPackages\Pages;

use App\Filament\Resources\WorkPackages\WorkPackageResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Override;

class EditWorkPackage extends EditRecord
{
    protected static string $resource = WorkPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    #[Override]
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
