<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Projects\RelationManagers\TasksRelationManager;
use App\Filament\Resources\Projects\RelationManagers\WorkPackagesRelationManager;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Override;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    #[Override]
    public function getTitle(): string|Htmlable
    {
        return $this->record->name;
    }

    #[Override]
    public function getSubheading(): string|Htmlable|null
    {
        return $this->record->client?->name;
    }

    #[Override]
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    #[Override]
    public function getContentTabLabel(): ?string
    {
        return "Settings";
    }

    /* public function getContentTabIcon(): string|\BackedEnum|null
    {
        return Heroicon::OutlinedClipboardDocumentList;
    } */

    /**
     * customizzazione tabs
     */
    public function content(Schema $schema): Schema
    {
        $ownerRecord = $this->getRecord();
        $managerLivewireData = ['ownerRecord' => $ownerRecord, 'pageClass' => static::class];

        return $schema->components([
            Tabs::make()
                //->livewireProperty('activeRelationManager')
                ->contained(false)
                ->activeTab(1)
                ->tabs([
                    // il contenuto del form base
                    $this->getContentTabComponent(),

                    Tab::make('Note')
                        ->schema([
                            MarkdownEditor::make('note')
                                ->hiddenLabel()
                                ->columnSpanFull(),
                        ]),

                    Tab::make('Pacchetti')
                        ->schema([
                            Livewire::make(
                                WorkPackagesRelationManager::class,
                                $managerLivewireData,
                            )->key(WorkPackagesRelationManager::class),
                        ]),

                    Tab::make('Tasks')
                        ->schema([
                            Livewire::make(
                                TasksRelationManager::class,
                                $managerLivewireData,
                            )->key(TasksRelationManager::class),
                        ]),
                ])
                ->activeTab(0),
        ]);
    }
}
