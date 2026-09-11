<?php

namespace App\Filament\Resources\Documents\Widgets;

use App\Models\Document;
use App\Models\User;
use App\Services\DocumentImportService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class DocumentTableWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    public ?Model $record = null;

    public function table(Table $table): Table
    {
        return $table
            ->heading('')
            ->hiddenFilterIndicators()
            ->records(fn (?string $search): Collection => $this->getOrderedDocuments($search))
            ->paginated(false)
            ->recordAction('open')
            ->columns([
                TextColumn::make('title')
                    ->label('')
                    ->searchable()
                    ->html()
                    ->formatStateUsing(function (Document $record): string {
                        $indent = str_repeat('　　', (int) ($record->getAttribute('depth') ?? 0));
                        $title = e(Str::words($record->title, 8));

                        if (filled($search = $this->getTableSearch())) {
                            $title = preg_replace(
                                '/('.preg_quote($search, '/').')/iu',
                                '<mark class="rounded-sm bg-warning-200 px-0.5 dark:bg-warning-500/40">$1</mark>',
                                $title,
                            );
                        }

                        return $indent.$title;
                    }),
            ])
            ->recordActions([
                Action::make('open')
                    ->hiddenLabel()
                    ->icon(false) // icona e label spenti per nascondere dall'ui l'action, ma lasciarla richiamare da recordAction()
                    ->action(fn (Document $record) => $this->dispatch('document-selected', documentId: $record->getKey())),

                Action::make('toggleFavorite')
                    ->hiddenLabel()
                    ->iconSize(IconSize::Small)
                    ->icon(fn (Document $record): string => self::isFavorite($record) ? 'heroicon-s-star' : 'heroicon-o-star')
                    ->color('warning')
                    ->tooltip(fn (Document $record): string => self::isFavorite($record) ? 'Rimuovi dai preferiti' : 'Aggiungi ai preferiti')
                    ->action(function (Document $record): void {
                        self::currentUser()->favoriteDocuments()->toggle($record);
                    }),

                Action::make('addSubpage')
                    ->hiddenLabel()
                    ->iconSize(IconSize::Small)
                    ->icon(Heroicon::Plus)
                    ->tooltip('Nuova pagina secondaria')
                    ->modalWidth(Width::Large)
                    ->schema([
                        TextInput::make('title')
                            ->label('Titolo')
                            ->hiddenLabel()
                            ->required(),
                    ])
                    ->action(function (Document $record, $data): void {
                        $newDocument = Document::create([
                            'workspace_id' => Filament::getTenant()->id,
                            'documentable_type' => $this->record?->getMorphClass(),
                            'documentable_id' => $this->record?->getKey(),
                            'parent_id' => $record->getKey(),
                            'title' => $data['title'],
                            'created_by' => Auth::id(),
                        ]);

                        $this->dispatch('document-selected', documentId: $newDocument->getKey());
                    }),

                ActionGroup::make([
                    Action::make('rename')
                        ->hiddenLabel()
                        ->iconSize(IconSize::Small)
                        ->icon(Heroicon::Pencil)
                        ->label('Rinomina')
                        ->modalHeading('Rinomina pagina')
                        ->modalWidth(Width::Large)
                        ->schema([
                            TextInput::make('title')
                                ->label('Titolo')
                                ->hiddenLabel()
                                ->required(),
                        ])
                        ->fillForm(fn (Document $record): array => [
                            'title' => $record->title,
                        ])
                        ->action(function (array $data, Document $record) {
                            Document::find($record->id)->update($data);
                            $this->resetTable();
                        }),

                    Action::make('clone')
                        ->hiddenLabel()
                        ->iconSize(IconSize::Small)
                        ->icon(Heroicon::OutlinedDocumentDuplicate)
                        ->label('Duplica')
                        ->action(function (array $data, Document $record) {
                            $doc = Document::find($record->id);

                            $clone = $doc->replicate();
                            $clone->title .= ' (Copia)';
                            $clone->save();

                            $this->resetTable();
                        }),

                    Action::make('delete')
                        ->hiddenLabel()
                        ->iconSize(IconSize::Small)
                        ->icon('heroicon-o-trash')
                        ->label('Elimina')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('Sei sicuro di voler cancellare il documento e tutti i suoi child?')
                        ->action(fn (Document $record) => $record->delete()),
                ])
                    ->icon(Heroicon::EllipsisHorizontal)
                    ->iconSize(IconSize::Small),
            ])
            ->headerActions([
                Action::make('addRootPage')
                    ->tooltip('Nuova pagina')
                    ->hiddenLabel()
                    ->link()
                    ->icon(Heroicon::Plus)
                    ->modalHeading('Crea pagina')
                    ->schema([
                        TextInput::make('title')
                            ->label('Titolo')
                            ->hiddenLabel()
                            ->required(),
                    ])
                    ->modalWidth(Width::Large)
                    ->action(function ($data): void {
                        $newDocument = Document::create([
                            'workspace_id' => Filament::getTenant()->id,
                            'documentable_type' => $this->record?->getMorphClass(),
                            'documentable_id' => $this->record?->getKey(),
                            'title' => $data['title'],
                            'created_by' => Auth::id(),
                        ]);

                        // $this->dispatch('document-selected', documentId: $newDocument->getKey());
                    }),

                Action::make('importDocuments')
                    ->tooltip('Importa documenti')
                    ->hiddenLabel()
                    ->link()
                    ->icon(Heroicon::ArrowDownTray)
                    ->modalHeading('Importa documenti')
                    ->modalDescription('Ogni file selezionato diventa un documento a sé stante.')
                    ->modalSubmitActionLabel('Importa')
                    ->schema([
                        Select::make('parent_id')
                            ->label('Documento padre')
                            ->placeholder('Nessuno (radice)')
                            ->options(fn (): array => $this->getOrderedDocuments()
                                ->mapWithKeys(fn (Document $document): array => [
                                    $document->getKey() => str_repeat('— ', (int) ($document->getAttribute('depth') ?? 0)).$document->title,
                                ])
                                ->all())
                            ->searchable(),
                        FileUpload::make('files')
                            ->label('File')
                            ->multiple()
                            ->preserveFilenames()
                            ->acceptedFileTypes([
                                'text/plain',
                                'text/markdown',
                                'text/x-markdown',
                                'text/html',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.oasis.opendocument.text',
                            ])
                            ->disk('local')
                            ->directory('document-imports')
                            ->required()
                            ->helperText('Formati supportati: .txt, .md, .html, .docx, .odt'),
                    ])
                    ->action(fn (array $data) => $this->importDocuments($data['files'] ?? [], $data['parent_id'] ?? null)),

                Action::make('reorderDocuments')
                    ->tooltip('Riordina documenti')
                    ->hiddenLabel()
                    ->link()
                    ->icon(Heroicon::ArrowsUpDown)
                    ->modalHeading('Riordina documenti')
                    ->modalWidth(Width::TwoExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Chiudi')
                    ->modalContent(fn () => view('filament.resources.documents.pages.partials.document-tree-reorder-modal', [
                        'record' => $this->record,
                    ])),
            ]);
    }

    /**
     * @param  array<string>  $paths
     */
    private function importDocuments(array $paths, ?int $parentId = null): void
    {
        $disk = Storage::disk('local');
        $service = app(DocumentImportService::class);
        $imported = 0;
        $failed = [];

        foreach ($paths as $path) {
            $originalName = basename($path);

            try {
                $parsed = $service->parse($disk->path($path), $originalName);

                Document::create([
                    'workspace_id' => Filament::getTenant()->id,
                    'documentable_type' => $this->record?->getMorphClass(),
                    'documentable_id' => $this->record?->getKey(),
                    'parent_id' => $parentId,
                    'title' => $parsed['title'],
                    'content' => $parsed['content'],
                    'created_by' => Auth::id(),
                ]);

                $imported++;
            } catch (Throwable $exception) {
                $failed[] = "{$originalName}: {$exception->getMessage()}";
            } finally {
                $disk->delete($path);
            }
        }

        $this->resetTable();

        if ($imported > 0) {
            Notification::make()
                ->title($imported === 1 ? '1 documento importato' : "{$imported} documenti importati")
                ->success()
                ->send();
        }

        if ($failed !== []) {
            Notification::make()
                ->title('Alcuni file non sono stati importati')
                ->body(implode("\n", $failed))
                ->danger()
                ->send();
        }
    }

    /**
     * Bypassa la query di default della table: carica tutti i documenti del
     * progetto in una sola query e li riordina in profondità (DFS) rispettando
     * parent_id/sort_order, in modo che ogni figlio segua subito il suo padre.
     *
     * Con una ricerca attiva l'albero resta lo stesso, ma si restringe ai soli
     * documenti che matchano e alla catena dei loro genitori — così un risultato
     * annidato si vede sempre nel suo contesto, non isolato dalla gerarchia.
     *
     * @return Collection<int, Document>
     */
    protected function getOrderedDocuments(?string $search = null): Collection
    {
        $all = Document::query()
            ->where('documentable_type', $this->record?->getMorphClass())
            ->where('documentable_id', $this->record?->getKey())
            ->orderBy('sort_order')
            ->get()
            ->keyBy(fn (Document $document): int => $document->getKey());

        $includeIds = null;

        if (filled($search)) {
            $includeIds = collect();

            foreach ($all as $document) {
                if (mb_stripos($document->title, $search) === false) {
                    continue;
                }

                // Risale la catena dei genitori per tenere il risultato nel
                // suo contesto gerarchico invece che isolato in un elenco piatto.
                $current = $document;

                while ($current && ! $includeIds->contains($current->getKey())) {
                    $includeIds->push($current->getKey());
                    $current = $all->get($current->parent_id);
                }
            }
        }

        $documentsByParent = ($includeIds !== null ? $all->only($includeIds->all()) : $all)
            ->groupBy('parent_id');

        $ordered = collect();

        $walk = function (int $parentId, int $depth) use (&$walk, &$ordered, $documentsByParent): void {
            foreach ($documentsByParent->get($parentId, collect()) as $document) {
                $document->setAttribute('depth', $depth);
                $ordered->push($document);
                $walk($document->getKey(), $depth + 1);
            }
        };

        $walk(Document::defaultParentKey(), 0);

        return $ordered;
    }

    private static function isFavorite(Document $record): bool
    {
        return self::currentUser()->favoriteDocuments()->whereKey($record->getKey())->exists();
    }

    private static function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
