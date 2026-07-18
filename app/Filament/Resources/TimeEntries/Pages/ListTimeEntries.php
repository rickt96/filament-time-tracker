<?php

namespace App\Filament\Resources\TimeEntries\Pages;

use App\Actions\Sync\SyncTimeEntryAction;
use App\Actions\TimeEntry\CopyLastActivityAction;
use App\Actions\TimeEntry\CopyPreviousDayAction;
use App\Actions\TimeEntry\CreateTimeEntryAction;
use App\Enums\TimeEntrySyncStatus;
use App\Filament\Resources\TimeEntries\TimeEntryResource;
use App\Models\TimeEntry;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ListTimeEntries extends ListRecords
{
    protected static string $resource = TimeEntryResource::class;

    protected Width|string|null $maxContentWidth = 'full';
    
    protected function getHeaderActions(): array
    {
        return [
            /* Action::make('copyPreviousDay')
                ->label('Copia giorno precedente')
                ->icon('heroicon-o-clipboard-document')
                ->action(function () {
                    $user = Auth::user();

                    $copied = app(CopyPreviousDayAction::class)->handle($user, Carbon::today());

                    Notification::make()
                        ->title($copied->count().' registrazioni copiate dal giorno precedente')
                        ->success()
                        ->send();
                }),
            Action::make('copyLastActivity')
                ->label('Copia ultima attività')
                ->icon('heroicon-o-clock')
                ->action(function () {
                    $user = Auth::user();

                    $entry = app(CopyLastActivityAction::class)->handle($user);

                    $notification = Notification::make()
                        ->title($entry ? 'Ultima attività copiata' : 'Nessuna attività precedente da copiare');

                    $entry ? $notification->success() : $notification->warning();

                    $notification->send();
                }),
            Action::make('syncAllFiltered')
                ->label('Sincronizza tutti i filtrati')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(function () {
                    $entries = $this->getFilteredTableQuery()?->get() ?? collect();

                    $synced = 0;
                    $failed = 0;

                    foreach ($entries as $entry) {
                        $result = app(SyncTimeEntryAction::class)->handle($entry);

                        $result->sync_status === TimeEntrySyncStatus::Synced ? $synced++ : $failed++;
                    }

                    Notification::make()
                        ->title("Sincronizzazione completata: {$synced} riuscite, {$failed} fallite")
                        ->send();
                }), */
            CreateAction::make()
                ->modal()
                ->using(function (array $data): TimeEntry {
                    /** @var User $user */
                    $user = Auth::user();

                    return app(CreateTimeEntryAction::class)->handle($user, CreateTimeEntry::normalize($data));
                }),
        ];
    }
}
