<?php

namespace App\Filament\Resources\WorkPackages;

use App\Filament\Resources\WorkPackages\Pages\CreateWorkPackage;
use App\Filament\Resources\WorkPackages\Pages\EditWorkPackage;
use App\Filament\Resources\WorkPackages\Pages\ListWorkPackages;
use App\Filament\Resources\WorkPackages\RelationManagers\TasksRelationManager;
use App\Filament\Resources\WorkPackages\Schemas\WorkPackageForm;
use App\Filament\Resources\WorkPackages\Tables\WorkPackagesTable;
use App\Models\WorkPackage;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * @extends resource<WorkPackage>
 */
class WorkPackageResource extends Resource
{
    protected static ?string $model = WorkPackage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    /**
     * WorkPackage has no direct workspace_id column — ownership flows through
     * project.workspace_id, so tenant scoping is applied manually below instead
     * of relying on Filament's automatic BelongsTo-based tenant scope.
     */
    protected static bool $isScopedToTenant = false;

    /**
     * @return Builder<WorkPackage>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('project', fn (Builder $query) => $query->where('workspace_id', Filament::getTenant()?->getKey()));
    }

    public static function form(Schema $schema): Schema
    {
        return WorkPackageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkPackagesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TasksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkPackages::route('/'),
            'create' => CreateWorkPackage::route('/create'),
            'edit' => EditWorkPackage::route('/{record}/edit'),
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
