<?php

namespace App\Filament\Resources\Workspaces\RelationManagers;

use App\Enums\WorkspaceRole;
use App\Models\Workspace;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Membri';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('role')
                    ->label('Ruolo')
                    ->options(WorkspaceRole::class)
                    ->default(WorkspaceRole::Member)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('pivot.role')
                    ->label('Ruolo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => WorkspaceRole::from($state)->getLabel()),
            ])
            ->headerActions([
                AttachAction::make()
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Select::make('role')
                            ->label('Ruolo')
                            ->options(WorkspaceRole::class)
                            ->default(WorkspaceRole::Member)
                            ->required(),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make()
                    ->visible(fn (Workspace $ownerRecord, $record): bool => $record->getKey() !== $ownerRecord->owner_id),
            ]);
    }
}
