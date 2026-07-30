<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Filament\Support\TaskDetailsAction;
use App\Models\Project;
use App\Models\WorkPackage;
use App\Services\Task\TaskTransferService;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use InvalidArgumentException;

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
                Section::make('Dati task')
                    ->columns(3)
                    ->components([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Select::make('status')
                            ->label('Stato')
                            ->options(TaskStatus::class)
                            ->required(),
                        Select::make('priority')
                            ->label('Priorità')
                            ->options(TaskPriority::class)
                            ->default(TaskPriority::Media)
                            ->required(),
                        DateTimePicker::make('expire')
                            ->label('Scadenza'),
                        Select::make('assignee_id')
                            ->label('Assegnatario')
                            ->relationship('assignee', 'name')
                            ->searchable()
                            ->preload(),
                        TextInput::make('external_id')
                            ->label('ID esterno')
                            ->maxLength(255)
                            ->columnSpan(2),
                        TextInput::make('url')
                            ->label('URL')
                            ->url()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
                Section::make('Descrizione')
                    ->columnSpanFull()
                    ->components([
                        RichEditor::make('description')
                            ->hiddenLabel()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->action(TaskDetailsAction::make()),
                TextColumn::make('workPackage.name')
                    ->label('Work Package')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge(),
                TextColumn::make('priority')
                    ->label('Priorità')
                    ->badge(),
                TextColumn::make('expire')
                    ->label('Scadenza')
                    ->dateTime()
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('assignee.name')
                    ->label('Assegnatario'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options(TaskStatus::class),
                SelectFilter::make('priority')
                    ->label('Priorità')
                    ->options(TaskPriority::class),
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
                    BulkAction::make('transferWorkPackage')
                        ->label('Trasferisci a Work Package')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->schema(fn (): array => [
                            Select::make('work_package_id')
                                ->label('Work Package di destinazione')
                                ->options(fn (): array => $this->ownerRecord->workPackages()
                                    ->orderBy('sort_order')
                                    ->pluck('name', 'id')
                                    ->all())
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $targetWorkPackage = WorkPackage::findOrFail((int) $data['work_package_id']);

                            try {
                                $result = app(TaskTransferService::class)->transfer($records, $targetWorkPackage);
                            } catch (InvalidArgumentException $exception) {
                                Notification::make()
                                    ->title($exception->getMessage())
                                    ->danger()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title("{$result['tasks']} task e {$result['timeEntries']} time entry trasferiti su \"{$targetWorkPackage->name}\"")
                                ->success()
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
