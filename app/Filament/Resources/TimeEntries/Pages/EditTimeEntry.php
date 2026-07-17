<?php

namespace App\Filament\Resources\TimeEntries\Pages;

use App\Actions\TimeEntry\UpdateTimeEntryAction;
use App\Filament\Resources\TimeEntries\TimeEntryResource;
use App\Models\TimeEntry;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTimeEntry extends EditRecord
{
    protected static string $resource = TimeEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var TimeEntry $record */
        $record = $this->getRecord();

        $data['entry_mode'] = 'range';
        $data['started_at'] = $record->started_at->format('H:i');
        $data['ended_at'] = $record->ended_at?->format('H:i');

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var TimeEntry $record */
        return app(UpdateTimeEntryAction::class)->handle($record, $data);
    }
}
