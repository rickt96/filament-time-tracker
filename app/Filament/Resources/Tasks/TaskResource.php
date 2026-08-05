<?php

namespace App\Filament\Resources\Tasks;

use App\Filament\Resources\Tasks\Pages\CreateTask;
use App\Filament\Resources\Tasks\Pages\EditTask;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Filament\Resources\Tasks\Schemas\TaskForm;
use App\Filament\Resources\Tasks\Tables\TasksTable;
use App\Filament\Resources\TimeEntries\TimeEntryResource;
use App\Models\Task;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * @extends resource<Task>
 */
class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static string|UnitEnum|null $navigationGroup = 'Anagrafiche';

    protected static ?int $navigationSort = 130;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Task has no direct workspace_id column — ownership flows through
     * workPackage.project.workspace_id, so tenant scoping is applied manually
     * below instead of relying on Filament's automatic BelongsTo-based tenant scope.
     */
    protected static bool $isScopedToTenant = false;

    /**
     * @return Builder<Task>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas(
                'workPackage.project',
                fn (Builder $query) => $query->where('workspace_id', Filament::getTenant()?->getKey()),
            );
    }

    public static function form(Schema $schema): Schema
    {
        return TaskForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TasksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTasks::route('/'),
            'create' => CreateTask::route('/create'),
            'edit' => EditTask::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * @return array<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'description', 'external_id', 'url'];
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Task $record */
        return [
            'Progetto' => $record->workPackage?->project?->name ?? '—',
            'ID esterno' => $record->external_id ?? '—',
            'Stato' => $record->status->getLabel(),
        ];
    }

    public static function getGlobalSearchResultActions(Model $record): array
    {
        return [
            //EditAction::make(),
            // ->icon(Heroicon::PencilSquare),
            Action::make('create-time-entry')
                ->label('Crea time entry')
                // ->icon(Heroicon::OutlinedClock)
                ->url(TimeEntryResource::getUrl('create', [
                    'record' => $record,
                    // parametri extra in GET per il pre-fill
                    'task_id' => $record->id,
                    'date' => now()->format('Y-m-d'),
                ])),
            Action::make('goto-url')
                ->label('Apri url externo')
                // ->icon(Heroicon::Link)
                ->visible(fn ($record) => $record->url != null)
                ->url(fn ($record) => $record->url, true),
        ];
    }
}
