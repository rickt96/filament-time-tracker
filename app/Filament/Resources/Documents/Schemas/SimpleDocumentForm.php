<?php

namespace App\Filament\Resources\Documents\Schemas;

use App\Models\Document;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class SimpleDocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('Titolo')
                ->required()
                ->columnSpanFull(),

            DocumentForm::getContentEditor(),

            SpatieMediaLibraryFileUpload::make('attachments')
                ->label('Allegati')
                ->collection(Document::MEDIA_COLLECTION_ATTACHMENTS)
                ->disk('public')
                ->multiple()
                ->reorderable()
                ->downloadable()
                ->openable()
                ->preserveFilenames()
                ->columnSpanFull(),

            TextEntry::make('infos')
                ->hiddenLabel()
                ->state(function (?Document $record) {
                    $parts = [
                        $record?->createdBy?->name,
                        $record?->updated_at ? ('Ultimo aggiornamento '.$record->updated_at->format('d/m/Y H:i')) : null,
                    ];

                    return new HtmlString(implode(' • ', array_filter($parts)));
                }),
        ]);
    }
}
