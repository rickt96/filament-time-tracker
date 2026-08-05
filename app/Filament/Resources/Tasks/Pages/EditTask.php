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
use Illuminate\Support\HtmlString;
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
        return $this->record->workPackage?->project?->name;

        /* if ($this->record->workPackage?->project) {
            return new HtmlString(
                '<span class="size-2 shrink-0 rounded-full" style="background-color: ' . ($this->record->workPackage?->project->color ?: '#9ca3af') . '"></span>' .
                $this->record->workPackage?->project->name
            );
        }
        else
        {
            return null;
        } */
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
                    'name' => $record->name,
                ])
                ->schema([
                    TextInput::make('name')
                        ->label('Titolo')
                        ->hiddenLabel()
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (Task $record, array $data): void {
                    $record->update(['name' => $data['name']]);

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
