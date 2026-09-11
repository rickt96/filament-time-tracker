<?php

namespace App\Filament\Resources\Documents\Tables;

use App\Filament\Resources\Documents\Schemas\DocumentForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Titolo')->searchable()->sortable(),
                TextColumn::make('parent.title')->label('Documento padre')->placeholder('—'),
                TextColumn::make('documentable_type')
                    ->label('Associato a')
                    ->formatStateUsing(fn (?string $state): string => $state ? (DocumentForm::DOCUMENTABLE_TYPES[$state] ?? $state) : '—'),
                TextColumn::make('createdBy.name')->label('Autore')->placeholder('—'),
                TextColumn::make('sort_order')->label('Ordine')->sortable(),
                TextColumn::make('updated_at')->label('Aggiornato il')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
