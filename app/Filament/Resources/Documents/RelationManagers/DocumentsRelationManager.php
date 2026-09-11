<?php

namespace App\Filament\Resources\Documents\RelationManagers;

use App\Filament\Resources\Documents\Schemas\DocumentForm;
use App\Models\Document;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documenti';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('Titolo')
                ->required(),
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
            DocumentForm::getContentEditor(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')->label('Titolo')->searchable(),
                TextColumn::make('parent.title')->label('Documento padre')->placeholder('—'),
                TextColumn::make('sort_order')->label('Ordine')->sortable(),
                TextColumn::make('updated_at')->label('Aggiornato il')->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                CreateAction::make()->mutateFormDataUsing(function (array $data): array {
                    $data['created_by'] = Auth::id();

                    return $data;
                }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
