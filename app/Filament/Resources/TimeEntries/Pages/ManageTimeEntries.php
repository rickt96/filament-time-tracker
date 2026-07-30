<?php

namespace App\Filament\Resources\TimeEntries\Pages;

use App\Filament\Resources\TimeEntries\Tables\TimeEntriesManageTable;
use App\Filament\Resources\TimeEntries\TimeEntryResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Filament\Tables\Table;

/**
 * Admin/CRUD-oriented sibling to ListTimeEntries (the "index" page, tuned
 * for day-to-day time logging) — see TimeEntriesManageTable for why a
 * separate table configuration was needed rather than reusing that one.
 */
class ManageTimeEntries extends ListRecords
{
    protected static string $resource = TimeEntryResource::class;

    protected static ?string $title = 'Gestione time entry';

    protected Width|string|null $maxContentWidth = 'full';

    public function table(Table $table): Table
    {
        return TimeEntriesManageTable::configure($table);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToLog')
                ->label('Torna al time tracker')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn (): string => TimeEntryResource::getUrl('index')),
        ];
    }
}
