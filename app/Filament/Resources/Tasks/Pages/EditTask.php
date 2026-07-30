<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Override;
use Parallax\FilamentComments\Actions\CommentsAction;

class EditTask extends EditRecord
{
    protected static string $resource = TaskResource::class;

    #[Override]
    public function getHeading(): string|Htmlable|null
    {
        return $this->record->name;
    }

    #[Override]
    public function getSubheading(): string|Htmlable|null
    {
        return $this->record->workPackage?->project?->name ?? null;
    }

    protected function getHeaderActions(): array
    {
        return [

            CommentsAction::make(),

            Action::make('rename')
                ->label('Rinomina')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->modalHeading('Rinomina il task')
                ->modalSubmitActionLabel('Salva')
                ->fillForm(fn (Task $record): array => [
                    'title' => $record->title,
                ])
                ->form([
                    TextInput::make('name')
                        ->label('Titolo')
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (Task $record, array $data): void {
                    $record->update(['title' => $data['title']]);

                    Notification::make()
                        ->success()
                        ->title('Titolo aggiornato')
                        ->send();
                }),

            DeleteAction::make()
                ->icon(Heroicon::OutlinedTrash),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
