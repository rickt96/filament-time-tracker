<?php

namespace App\Filament\Resources\TimeEntries\Tables;

use App\Actions\Sync\SyncTimeEntryAction;
use App\Actions\TimeEntry\DuplicateTimeEntryAction;
use App\Actions\TimeEntry\UpdateTimeEntryAction;
use App\Enums\InvoiceStatus;
use App\Enums\TimeEntrySyncStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Support\TaskDetailsAction;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Services\Invoice\AttachTimeEntriesToInvoiceService;
use App\Support\DurationFormatter;
use App\Support\TagOptions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * CRUD/admin-oriented counterpart to TimeEntriesTable: that one is tuned for
 * the day-to-day "log my time" workflow (mandatory date grouping, inline-
 * editable description, filters stripped down), which makes it a poor fit
 * for bulk administration — selecting a large, filtered batch of entries
 * across dates/projects to bulk-sync is exactly what its grouping and
 * missing filters get in the way of. This table restores the standard
 * filters, surfaces sync status/columns, and drops the forced grouping.
 */
class TimeEntriesManageTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['project.client', 'task.workPackage', 'user', 'invoices'])
                // ->withCount('invoices')
            )
            ->columns([
                TextColumn::make('date')
                    ->label('Data')
                    ->date()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Descrizione')
                    ->limit(40)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->placeholder('—')
                    ->limit(30),
                TextColumn::make('project.name')
                    ->label('Progetto')
                    ->getStateUsing(fn (TimeEntry $record): string => "{$record->project->name} ({$record->client->name})")
                    ->sortable()
                    ->color(fn ($record) => $record->project?->color ? Color::hex($record->project?->color) : 'gray')
                    ->badge(),
                TextColumn::make('task.name')
                    ->label('Task')
                    ->action(TaskDetailsAction::make(fn (TimeEntry $record): ?Task => $record->task))
                    ->placeholder('—')
                    ->description(fn ($record) => $record->workPackage?->name, 'above'),
                /* TextColumn::make('user.name')
                    ->label('Utente')
                    ->searchable()
                    ->sortable(), */
                TextColumn::make('started_at')
                    ->label('Orario')
                    ->getStateUsing(fn (TimeEntry $record): string => $record->started_at->format('H:i').' - '.($record->ended_at?->format('H:i') ?? '…')),
                TextColumn::make('duration_seconds')
                    ->label('Durata')
                    ->formatStateUsing(fn (int $state): string => DurationFormatter::hoursMinutesSeconds($state))
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->hiddenLabel()
                            ->formatStateUsing(fn (int $state): string => DurationFormatter::hoursMinutesSeconds($state))
                    ),
                TextColumn::make('total_amount')
                    ->label('Importo')
                    ->money('EUR')
                    ->sortable()
                    ->toggleable()
                    ->summarize(
                        Sum::make()
                            ->money('EUR')
                            ->hiddenLabel()
                    ),
                IconColumn::make('sync_status')
                    ->label('')
                    ->icon(fn (TimeEntry $record): string => match ($record->sync_status) {
                        TimeEntrySyncStatus::Synced => 'heroicon-o-cloud-arrow-up',
                        default => 'heroicon-o-cloud'
                    })
                    ->size(IconSize::Medium)
                    ->color(fn (TimeEntry $record): string => match ($record->sync_status) {
                        TimeEntrySyncStatus::Synced => 'success',
                        TimeEntrySyncStatus::Failed => 'warning',
                        default => 'gray'
                    })
                    ->tooltip(fn (TimeEntry $record): ?string => $record->sync_status === TimeEntrySyncStatus::Synced
                        ? $record->synced_at->format('d/m/Y H:i')
                        : $record->sync_error
                    ),
                IconColumn::make('invoices')
                    ->label('')
                    ->icon('heroicon-o-currency-euro')
                    ->size(IconSize::Medium)
                    ->color(fn (TimeEntry $record): string => $record->invoices->isNotEmpty()
                        ? 'success'
                        : 'gray'
                    )
                    ->tooltip(fn (TimeEntry $record): string => $record->invoices->isNotEmpty()
                        ? implode(', ', $record->invoices->pluck('label')->toArray())
                        : 'Time entry non fatturata'
                    )
                    ->action(
                        Action::make('invoices_details')
                            ->schema(function ($record) {
                                return [
                                    RepeatableEntry::make('invoices')
                                        ->hiddenLabel()
                                        ->table([
                                            TableColumn::make('Fattura'),
                                            TableColumn::make('Cliente'),
                                            TableColumn::make('Totale'),
                                        ])
                                        ->schema([
                                            TextEntry::make('label')
                                                ->url(fn ($record) => InvoiceResource::getUrl('edit', ['record' => $record]), true)
                                                ->icon(Heroicon::ArrowTopRightOnSquare)
                                                ->iconPosition(IconPosition::After),
                                            TextEntry::make('client.name'),
                                            TextEntry::make('amount')
                                                ->money('EUR'),
                                        ]),
                                ];
                            })
                    ),
                /* TextColumn::make('synced_at')
                    ->label('Sincronizzato il')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true), */
            ])
            ->filters([
                Filter::make('date_range')
                    ->label('Intervallo date')
                    ->schema([
                        DatePicker::make('from')->label('Dal'),
                        DatePicker::make('until')->label('Al'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '<=', $date));
                    }),
                SelectFilter::make('project')
                    ->label('Progetto')
                    ->multiple()
                    ->preload()
                    ->relationship(
                        name: 'project',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('workspace_id', Filament::getTenant()?->getKey())->selectable(),
                    ),
                SelectFilter::make('client')
                    ->label('Cliente')
                    ->relationship(
                        name: 'project.client',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('workspace_id', Filament::getTenant()?->getKey()),
                    ),
                SelectFilter::make('tags')
                    ->label('Tag')
                    ->multiple()
                    ->options(fn (): array => TagOptions::from(
                        TimeEntry::query()->whereHas('project', fn (Builder $query) => $query->where('workspace_id', Filament::getTenant()?->getKey())),
                    ))
                    ->query(function (Builder $query, array $data): Builder {
                        $values = $data['values'] ?? [];

                        if (blank($values)) {
                            return $query;
                        }

                        return $query->where(function (Builder $query) use ($values): void {
                            foreach ($values as $value) {
                                $query->orWhereJsonContains('tags', $value);
                            }
                        });
                    }),
                SelectFilter::make('user')
                    ->label('Utente')
                    ->relationship('user', 'name'),
                SelectFilter::make('task')
                    ->label('Task')
                    ->relationship('task', 'name'),
                SelectFilter::make('sync_status')
                    ->label('Stato sincronizzazione')
                    ->options(TimeEntrySyncStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->modal()
                        ->mutateRecordDataUsing(function (array $data, TimeEntry $record): array {
                            $data['started_at'] = $record->started_at->format('H:i');
                            $data['ended_at'] = $record->ended_at?->format('H:i');

                            return $data;
                        })
                        ->using(fn (TimeEntry $record, array $data): TimeEntry => app(UpdateTimeEntryAction::class)
                            ->handle($record, $data)),
                    Action::make('duplicate')
                        ->label('Duplica')
                        ->icon('heroicon-o-document-duplicate')
                        ->action(function (TimeEntry $record) {
                            app(DuplicateTimeEntryAction::class)->handle($record);

                            Notification::make()
                                ->title('Time entry duplicato')
                                ->success()
                                ->send();
                        }),
                    Action::make('sync')
                        ->label('Sincronizza')
                        ->icon('heroicon-o-arrow-path')
                        ->visible(fn (TimeEntry $record): bool => filled($record->task?->external_id) && $record->project->client->sync_driver !== null)
                        ->action(function (TimeEntry $record) {
                            $synced = app(SyncTimeEntryAction::class)->handle($record);

                            $notification = Notification::make()->title(
                                $synced->sync_status === TimeEntrySyncStatus::Synced
                                    ? 'Time entry sincronizzato'
                                    : "Sincronizzazione fallita: {$synced->sync_error}",
                            );

                            $synced->sync_status === TimeEntrySyncStatus::Synced
                                ? $notification->success()
                                : $notification->danger();

                            $notification->send();
                        }),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('sync')
                        ->label('Sincronizza selezionati')
                        ->icon('heroicon-o-arrow-path')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            [$synced, $failed] = self::syncMany($records);

                            Notification::make()
                                ->title("Sincronizzazione completata: {$synced} riuscite, {$failed} fallite")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('attachToInvoice')
                        ->label('Aggiungi a fattura')
                        ->icon('heroicon-o-document-currency-euro')
                        ->schema([
                            Radio::make('mode')
                                ->label('Fattura')
                                ->options([
                                    'existing' => 'Fattura esistente',
                                    'new' => 'Nuova fattura',
                                ])
                                ->default('existing')
                                ->live()
                                ->inline()
                                ->required(),
                            Select::make('invoice_id')
                                ->label('Fattura')
                                ->options(fn (): array => Invoice::query()
                                    ->where('workspace_id', Filament::getTenant()?->getKey())
                                    ->with('client')
                                    ->orderByDesc('year')
                                    ->orderByDesc('number')
                                    ->get()
                                    ->mapWithKeys(fn (Invoice $invoice): array => [
                                        $invoice->id => "{$invoice->year}/{$invoice->number} — {$invoice->client->name}",
                                    ])
                                    ->all())
                                ->searchable()
                                ->visible(fn (Get $get): bool => $get('mode') === 'existing')
                                ->required(fn (Get $get): bool => $get('mode') === 'existing'),
                            Select::make('client_id')
                                ->label('Cliente')
                                ->relationship(
                                    name: 'client',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: fn (Builder $query) => $query
                                        ->where('workspace_id', Filament::getTenant()?->getKey())
                                        ->where('is_active', true),
                                )
                                ->searchable()
                                ->preload()
                                ->visible(fn (Get $get): bool => $get('mode') === 'new')
                                ->required(fn (Get $get): bool => $get('mode') === 'new'),
                            TextInput::make('year')
                                ->label('Anno')
                                ->numeric()
                                ->default(now()->year)
                                ->visible(fn (Get $get): bool => $get('mode') === 'new')
                                ->required(fn (Get $get): bool => $get('mode') === 'new'),
                            TextInput::make('number')
                                ->label('Numero anno')
                                ->numeric()
                                ->visible(fn (Get $get): bool => $get('mode') === 'new')
                                ->required(fn (Get $get): bool => $get('mode') === 'new'),
                            TextInput::make('amount')
                                ->label('Importo')
                                ->numeric()
                                ->prefix('€')
                                ->default(fn (Collection $records): string => number_format((float) $records->sum('total_amount'), 2, '.', ''))
                                ->visible(fn (Get $get): bool => $get('mode') === 'new')
                                ->required(fn (Get $get): bool => $get('mode') === 'new'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            if ($data['mode'] === 'existing') {
                                $invoice = Invoice::findOrFail((int) $data['invoice_id']);
                            } else {
                                $invoice = Invoice::create([
                                    'workspace_id' => Filament::getTenant()?->getKey(),
                                    'client_id' => $data['client_id'],
                                    'year' => $data['year'],
                                    'number' => $data['number'],
                                    'amount' => $data['amount'],
                                    'status' => InvoiceStatus::Draft,
                                ]);

                                // The Invoice's own "linked projects" should
                                // already reflect what it's billing for the
                                // moment it exists, not stay empty until
                                // someone edits it manually.
                                $invoice->projects()->syncWithoutDetaching(
                                    $records->pluck('project_id')->unique(),
                                );
                            }

                            $attached = app(AttachTimeEntriesToInvoiceService::class)->attach($records, $invoice);

                            Notification::make()
                                ->title("{$attached} time entry aggiunte alla fattura {$invoice->year}/{$invoice->number}")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->recordUrl(null)
            ->paginated([25, 50, 100, 200])
            ->defaultPaginationPageOption(50)
            ->paginationMode(PaginationMode::Default);
    }

    /**
     * @param  Collection<int, TimeEntry>  $records
     * @return array{0: int, 1: int} [synced count, failed count]
     */
    private static function syncMany(Collection $records): array
    {
        $synced = 0;
        $failed = 0;

        foreach ($records as $record) {
            $result = app(SyncTimeEntryAction::class)->handle($record);

            $result->sync_status === TimeEntrySyncStatus::Synced ? $synced++ : $failed++;
        }

        return [$synced, $failed];
    }
}
