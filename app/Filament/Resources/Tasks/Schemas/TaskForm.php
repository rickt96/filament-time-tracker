<?php

namespace App\Filament\Resources\Tasks\Schemas;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Filament\Forms\Components\RichEditor\RichContentCustomBlocks\ChecklistBlock;
use App\Models\Task;
use App\Models\WorkPackage;
use App\Support\TagOptions;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dettagli')
                    ->columns(2)
                    ->inlineLabel()
                    ->columnSpanFull()
                    ->components([
                        TextInput::make('name')
                            ->label('Nome')
                            ->inlineLabel(false)
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->visibleOn('create'),
                        // basic
                        /* Select::make('work_package_id')
                            ->label('Work Package')
                            ->relationship(
                                name: 'workPackage',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query->whereHas(
                                    'project',
                                    fn (Builder $query) => $query->where('workspace_id', Filament::getTenant()?->getKey()),
                                ),
                            )
                            ->searchable()
                            ->preload()
                            ->required(), */

                        Select::make('work_package_id')
                            ->label('Work Package')
                            ->options(fn (): array => WorkPackage::query()
                                ->with('project')
                                ->whereHas('project', fn (Builder $query) => $query->where('workspace_id', Filament::getTenant()?->getKey()))
                                ->get()
                                ->mapWithKeys(fn (WorkPackage $workPackage): array => [
                                    $workPackage->id => "{$workPackage->project->name} — {$workPackage->name}",
                                ])
                                ->all()
                            )
                            ->required()
                            ->searchable(),

                        Select::make('status')
                            ->label('Stato')
                            ->options(TaskStatus::class)
                            ->default(TaskStatus::Todo)
                            ->required(),
                        Select::make('priority')
                            ->label('Priorità')
                            ->options(TaskPriority::class)
                            ->default(TaskPriority::Media)
                            ->required(),
                        Select::make('assignee_id')
                            ->label('Assegnatario')
                            ->relationship('assignee', 'name')
                            ->default(fn (): int|string|null => Auth::id())
                            ->searchable()
                            ->preload(),
                        DateTimePicker::make('expire')
                            ->label('Scadenza'),
                        TextInput::make('external_id')
                            ->label('ID esterno')
                            // ->helperText('Identificativo nel sistema esterno. L\'app non crea task remoti: valorizzare questo campo associa manualmente il task locale a quello remoto.')
                            ->maxLength(255),
                        TextInput::make('url')
                            ->label('URL')
                            ->url()
                            ->maxLength(255),
                        /* TextInput::make('import_old_id')
                            ->label('ID import legacy')
                            ->maxLength(255)
                            ->disabled(), */
                        TagsInput::make('tags')
                            ->inlineLabel()
                            ->label('Tag')
                            ->suggestions(fn (): array => array_values(TagOptions::from(
                                Task::query()->whereHas(
                                    'workPackage.project',
                                    fn (Builder $query) => $query->where('workspace_id', Filament::getTenant()?->getKey()),
                                ),
                            )))
                            ->columnSpanFull(),
                    ]),
                Section::make('Descrizione')
                    ->columnSpanFull()
                    ->schema([
                        RichEditor::make('description')
                            ->hiddenLabel()
                            ->floatingToolbars([
                                'paragraph' => [
                                    'bold', 'italic', 'underline', 'strike', 'subscript', 'superscript',
                                ],
                                'heading' => [
                                    'h1', 'h2', 'h3',
                                ],
                                'table' => [
                                    'tableAddColumnBefore', 'tableAddColumnAfter', 'tableDeleteColumn',
                                    'tableAddRowBefore', 'tableAddRowAfter', 'tableDeleteRow',
                                    'tableMergeCells', 'tableSplitCell',
                                    'tableToggleHeaderRow', 'tableToggleHeaderCell',
                                    'tableDelete',
                                ],
                            ])
                            ->customBlocks([
                                ChecklistBlock::class,
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
