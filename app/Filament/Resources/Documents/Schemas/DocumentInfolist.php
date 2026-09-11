<?php

namespace App\Filament\Resources\Documents\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DocumentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('title')->label('Titolo'),
            TextEntry::make('parent.title')->label('Documento padre')->placeholder('—'),
            TextEntry::make('documentable_type')
                ->label('Associato a')
                ->formatStateUsing(fn (?string $state): string => $state ? (DocumentForm::DOCUMENTABLE_TYPES[$state] ?? $state) : '—'),
            TextEntry::make('createdBy.name')->label('Autore')->placeholder('—'),
            TextEntry::make('content')->label('Contenuto')->html()->columnSpanFull(),
        ]);
    }
}
