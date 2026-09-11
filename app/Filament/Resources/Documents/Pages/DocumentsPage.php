<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Concerns\TracksPageVisit;
use App\Filament\Resources\Documents\Schemas\SimpleDocumentForm;
use App\Models\Document;
use App\Models\PageVisit;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\On;
use Override;

/**
 * Pagina "Documenti" agganciabile a qualsiasi Resource il cui model possa
 * essere il documentable di un Document (Project, Client, ...): ogni risorsa
 * registra una sottoclasse concreta che si limita a impostare $resource,
 * tutta la logica vive qui. Il model di riferimento è sempre e solo
 * $this->getRecord() — non serve altra configurazione.
 *
 * Le azioni "importa"/"riordina" vivono negli headerActions del
 * DocumentTableWidget in aside, non qui.
 */
abstract class DocumentsPage extends ViewRecord
{
    use TracksPageVisit;

    protected Width|string|null $maxContentWidth = 'full';

    protected string $view = 'filament.resources.documents.pages.documents-page';

    protected static ?string $navigationLabel = 'Documenti';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    #[Override]
    public function getTitle(): string|Htmlable
    {
        return 'Documenti';
    }

    public ?int $selectedDocumentId = null;

    public array $documentData = [];

    public function mount(int|string $record, ?string $document = null): void
    {
        parent::mount($record);

        $documentId = $document ? Document::resolveKeyFromUrlSegment($document) : null;

        if ($documentId) {
            $this->selectedDocumentId = $documentId;
            $this->documentEditorForm->fill($this->getSelectedDocument()?->attributesToArray() ?? []);

            PageVisit::recordVisit($this->getSelectedDocument());
        }
    }

    #[On('document-selected')]
    public function selectDocument(int $documentId): void
    {
        $document = Document::query()->findOrFail($documentId);

        $this->redirect(
            static::getResource()::getUrl('documents', ['record' => $this->getRecord(), 'document' => $document->urlSegment()], tenant: Filament::getTenant()),
            navigate: true,
        );
    }

    public function documentEditorForm(Schema $schema): Schema
    {
        return SimpleDocumentForm::configure($schema)
            ->model(fn () => $this->getSelectedDocument() ?? Document::class)
            ->statePath('documentData')
            ->operation('edit');
    }

    public function saveDocument(): void
    {
        $document = $this->getSelectedDocument();

        if (! $document) {
            return;
        }

        $document->update($this->documentEditorForm->getState());

        Notification::make()->title('Documento salvato')->success()->send();
    }

    protected function getSelectedDocument(): ?Document
    {
        if (! $this->selectedDocumentId) {
            return null;
        }

        return Document::query()->find($this->selectedDocumentId);
    }
}
