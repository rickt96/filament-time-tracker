<?php

namespace App\Filament\Resources\Documents;

use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\Documents\Pages\ViewDocument;
use App\Filament\Resources\Documents\Schemas\DocumentForm;
use App\Filament\Resources\Documents\Schemas\DocumentInfolist;
use App\Filament\Resources\Documents\Tables\DocumentsTable;
use App\Models\Document;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static ?string $modelLabel = 'documento';

    protected static ?string $pluralModelLabel = 'documenti';

    protected static ?string $navigationLabel = 'Documenti';

    protected static string|UnitEnum|null $navigationGroup = 'Anagrafiche';

    protected static ?int $navigationSort = 120;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $recordTitleAttribute = 'title';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return DocumentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DocumentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocuments::route('/'),
            'create' => CreateDocument::route('/create'),
            'view' => ViewDocument::route('/{record}'),
            'edit' => EditDocument::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'slug', 'content'];
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Document $record */
        return [
            'Progetto' => $record->documentable->name ?? '—',
        ];
    }

    /**
     * Punta alla pagina "documents" della resource proprietaria del model
     * documentable (Project, Client, ...), qualunque essa sia — risolta
     * dinamicamente, non serve sapere in anticipo quali tipi esistono.
     */
    public static function getGlobalSearchResultActions(Model $record): array
    {
        /** @var Document $record */
        $resource = $record->documentable ? Filament::getModelResource($record->documentable) : null;

        if (! $resource || ! $resource::hasPage('documents')) {
            return [];
        }

        return [
            Action::make('goto-url')
                ->label('Visualizza')
                ->url($resource::getUrl('documents', ['record' => $record->documentable_id, 'document' => $record->id])),
        ];
    }
}
