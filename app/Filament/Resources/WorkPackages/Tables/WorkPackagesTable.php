<?php

namespace App\Filament\Resources\WorkPackages\Tables;

use App\Enums\WorkPackageStatus;
use App\Filament\Support\WorkPackageBudgetColumn;
use App\Models\Project;
use App\Models\WorkPackage;
use App\Services\WorkPackage\WorkPackageTransferService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class WorkPackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // ->defaultSort('project.name')
            /* ->defaultGroup(
                Group::make('project.name')
                    ->label('')
                    ->date()
                    // Forced regardless of $direction: the grouping-direction
                    // toggle is meaningless with no alternative groups to pick
                    // from, but its default of 'asc' would otherwise leak in.
                    ->orderQueryUsing(fn (Builder $query): Builder => $query->orderBy('date', 'desc')),
            ) */
            ->defaultGroup('project.name')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['project'])->withCount('timeEntries'))
            ->columns([
                /* TextColumn::make('project.name')
                    ->label('Progetto')
                    ->searchable()
                    ->sortable()
                    ->color(fn ($record) => $record->project?->color ? Color::hex($record->project?->color) : 'gray')
                    ->badge(), */
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->sortable(),
                TextColumn::make('hourly_rate')
                    ->label('Tariffa oraria')
                    ->getStateUsing(fn (WorkPackage $record): ?string => $record->effectiveHourlyRate())
                    ->money('EUR')
                    ->sortable(),
                ...WorkPackageBudgetColumn::make(),
                TextColumn::make('tasks_count')
                    ->label('Task')
                    ->counts('tasks')
                    ->sortable(),
                TextColumn::make('time_entries_count')
                    ->label('Time entries')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options(WorkPackageStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('transfer')
                    ->label('Trasferisci')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->schema(fn (WorkPackage $record): array => [
                        Select::make('project_id')
                            ->label('Progetto di destinazione')
                            ->options(fn (): array => Project::query()
                                ->where('workspace_id', Filament::getTenant()?->getKey())
                                ->where('id', '!=', $record->project_id)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (WorkPackage $record, array $data): void {
                        try {
                            $movedTimeEntries = app(WorkPackageTransferService::class)
                                ->transfer($record, Project::findOrFail((int) $data['project_id']));
                        } catch (InvalidArgumentException $exception) {
                            Notification::make()
                                ->title($exception->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title("Work Package trasferito ({$movedTimeEntries} time entry spostate)")
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('transfer')
                        ->label('Trasferisci')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->schema([
                            Select::make('project_id')
                                ->label('Progetto di destinazione')
                                ->options(fn (): array => Project::query()
                                    ->where('workspace_id', Filament::getTenant()?->getKey())
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all())
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $targetProject = Project::findOrFail((int) $data['project_id']);
                            $service = app(WorkPackageTransferService::class);

                            $transferred = 0;
                            $movedTimeEntries = 0;
                            $skipped = 0;

                            foreach ($records as $record) {
                                try {
                                    $movedTimeEntries += $service->transfer($record, $targetProject);
                                    $transferred++;
                                } catch (InvalidArgumentException) {
                                    $skipped++;
                                }
                            }

                            Notification::make()
                                ->title("Work Package trasferiti: {$transferred} ({$movedTimeEntries} time entry spostate), saltati: {$skipped}")
                                ->success()
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
