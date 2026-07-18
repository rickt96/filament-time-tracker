<?php

namespace App\Filament\Resources\Tasks;

use App\Filament\Resources\Tasks\Pages\CreateTask;
use App\Filament\Resources\Tasks\Pages\EditTask;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Filament\Resources\Tasks\Schemas\TaskForm;
use App\Filament\Resources\Tasks\Tables\TasksTable;
use App\Models\Task;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * @extends resource<Task>
 */
class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static string|UnitEnum|null $navigationGroup = "Gestione";
    
    protected static ?int $navigationSort = 100;

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
}
