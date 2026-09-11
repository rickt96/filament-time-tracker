<?php

namespace App\Filament\Resources\Documents\Schemas;

use App\Filament\Forms\Components\RichEditor\OpenLinkOnCtrlClickPlugin;
use App\Models\Client;
use App\Models\Document;
use App\Models\Project;
use Filament\Facades\Filament;
use Filament\Forms\Components\RichEditor\ToolbarButtonGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;

class DocumentForm
{
    public const DOCUMENTABLE_TYPES = [
        Project::class => 'Progetto',
        Client::class => 'Cliente',
    ];

    /**
     * Cartella (sul disco allegati dell'editor) dove finiscono le immagini del
     * contenuto: sia quelle caricate a mano dall'editor sia quelle estratte in
     * fase di import da DocumentImportService.
     */
    public const CONTENT_ATTACHMENTS_DIRECTORY = 'documents';

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('title')
                    ->label('Titolo')
                    ->required()
                    ->columnSpanFull(),
                Select::make('parent_id')
                    ->label('Documento padre')
                    ->relationship(
                        name: 'parent',
                        titleAttribute: 'title',
                        modifyQueryUsing: fn (Builder $query, ?Document $record) => $query
                            ->where('workspace_id', Filament::getTenant()->id)
                            ->when($record, fn (Builder $query) => $query->whereKeyNot($record->getKey())),
                    )
                    ->searchable()
                    ->preload(),
                TextInput::make('sort_order')
                    ->label('Ordine')
                    ->numeric()
                    ->default(0),
                Select::make('documentable_type')
                    ->label('Tipo elemento associato')
                    ->options(self::DOCUMENTABLE_TYPES)
                    ->live()
                    ->afterStateUpdated(fn ($set) => $set('documentable_id', null)),
                Select::make('documentable_id')
                    ->label('Elemento associato')
                    ->options(function (Get $get): array {
                        $type = $get('documentable_type');

                        if (blank($type) || ! is_a($type, Model::class, true)) {
                            return [];
                        }

                        return $type::query()
                            ->where('workspace_id', Filament::getTenant()->id)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->searchable()
                    ->visible(fn (Get $get): bool => filled($get('documentable_type'))),
                self::getContentEditor(),
            ]),
        ]);
    }

    public static function getContentEditor(): AdvancedRichEditor
    {
        $floatingTools = [
            ToolbarButtonGroup::make('Testo', ['paragraph', 'h1', 'h2', 'h3', 'h4', 'codeBlock', 'quote']),
            'textColor',
            'bold',
            'italic',
            'underline',
            'strike',
            'code',
            ToolbarButtonGroup::make('Allineamento', ['alignStart', 'alignCenter', 'alignEnd', 'alignJustify']),
            'link',
            ToolbarButtonGroup::make('Elenco', ['bulletList', 'orderedList', 'taskList']),
            'highlight',
        ];

        return AdvancedRichEditor::make('content')
            ->label('Contenuto')
            ->fileAttachmentsDirectory(self::CONTENT_ATTACHMENTS_DIRECTORY)
            ->resizableImages()
            ->plugins([
                OpenLinkOnCtrlClickPlugin::make(),
            ])
            ->taskList()
            ->listTypes(['bulletList', 'orderedList', 'taskList'])
            ->enableToolbarButtons([
                ToolbarButtonGroup::make('Griglia', ['grid', 'gridDelete']),
                'details',
            ])
            ->floatingToolbars([
                'paragraph' => $floatingTools,
                'heading' => $floatingTools,
                'table' => [
                    'tableAddColumnBefore', 'tableAddColumnAfter', 'tableDeleteColumn',
                    'tableAddRowBefore', 'tableAddRowAfter', 'tableDeleteRow',
                    'tableMergeCells', 'tableSplitCell',
                    'tableToggleHeaderRow', 'tableToggleHeaderCell',
                    'tableDelete',
                ],
            ])
            ->columnSpanFull();
    }
}
