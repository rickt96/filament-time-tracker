<?php

namespace App\Filament\Resources\TimeEntries;

use App\Filament\Resources\TimeEntries\Pages\CreateTimeEntry;
use App\Filament\Resources\TimeEntries\Pages\EditTimeEntry;
use App\Filament\Resources\TimeEntries\Pages\ListTimeEntries;
use App\Filament\Resources\TimeEntries\Pages\ManageTimeEntries;
use App\Filament\Resources\TimeEntries\Schemas\TimeEntryForm;
use App\Filament\Resources\TimeEntries\Tables\TimeEntriesTable;
use App\Models\TimeEntry;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * @extends resource<TimeEntry>
 */
class TimeEntryResource extends Resource
{
    protected static ?string $model = TimeEntry::class;

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Time tracker';

    /**
     * TimeEntry has no direct workspace_id column — ownership flows through
     * project.workspace_id, so tenant scoping is applied manually below instead
     * of relying on Filament's automatic BelongsTo-based tenant scope.
     */
    protected static bool $isScopedToTenant = false;

    /**
     * @return Builder<TimeEntry>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('project', fn (Builder $query) => $query->where('workspace_id', Filament::getTenant()?->getKey()));
    }

    public static function form(Schema $schema): Schema
    {
        // return TimeEntryForm::configure($schema);
        return TimeEntryForm::configureRangeOnly($schema);
    }

    /* public static function table(Table $table): Table
    {
        return TimeEntriesTable::configure($table);
    } */

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTimeEntries::route('/'),
            'manage' => ManageTimeEntries::route('/manage'),
            'create' => CreateTimeEntry::route('/create'),
            'edit' => EditTimeEntry::route('/{record}/edit'),
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
        return ['description'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        /** @var TimeEntry $record */
        return $record->description ?: $record->project->name.' — '.$record->date->toFormattedDateString();
    }
}
