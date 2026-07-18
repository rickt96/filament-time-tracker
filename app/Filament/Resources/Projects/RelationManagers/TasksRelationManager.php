<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\TaskStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Tasks belong to a Project only indirectly, through a Work Package — there's
 * no work_package_id-less way to attach one directly to a Project — so unlike
 * the other relation managers, records are created from WorkPackageResource's
 * own Tasks relation manager instead. This one aggregates tasks across all of
 * the Project's Work Packages for a project-wide view, and is read/edit only.
 */
class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $title = 'Task';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),
                Select::make('status')
                    ->label('Stato')
                    ->options(TaskStatus::class)
                    ->required(),
                Select::make('assignee_id')
                    ->label('Assegnatario')
                    ->relationship('assignee', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('external_id')
                    ->label('ID esterno')
                    ->maxLength(255),
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
                TextColumn::make('workPackage.name')
                    ->label('Work Package')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge(),
                TextColumn::make('assignee.name')
                    ->label('Assegnatario'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options(TaskStatus::class),
                SelectFilter::make('work_package_id')
                    ->label('Work Package')
                    ->relationship('workPackage', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
