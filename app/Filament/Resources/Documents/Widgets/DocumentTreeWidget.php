<?php

namespace App\Filament\Resources\Documents\Widgets;

use App\Models\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use SolutionForest\FilamentTree\Widgets\Tree as BaseWidget;

class DocumentTreeWidget extends BaseWidget
{
    protected static string $model = Document::class;

    protected ?string $treeTitle = 'Documenti';

    protected static int $maxDepth = 3;

    public ?Model $record = null;

    protected function getTreeQuery(): Builder
    {
        return Document::query()
            ->where('documentable_type', $this->record?->getMorphClass())
            ->where('documentable_id', $this->record?->getKey());
    }

    /**
     * Vuoto di proposito: creazione/rinomina/eliminazione vivono già nella
     * tabella dell'aside (DocumentTableWidget) — questo widget, montato solo
     * dentro la modale di riordino, serve unicamente per il drag&drop.
     */
    protected function getTreeToolbarActions(): array
    {
        return [];
    }

    public function getTreeRecordTitle(?Model $record = null): string
    {
        if (! $record) {
            return '';
        }

        return Str::limit($record->title, 30);
    }

    /**
     * Vuoto di proposito, vedi getTreeToolbarActions().
     */
    protected function getTreeActions(): array
    {
        return [];
    }
}
