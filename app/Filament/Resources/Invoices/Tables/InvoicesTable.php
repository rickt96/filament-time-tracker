<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Enums\InvoiceStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(function (Builder $query): Builder {
                return $query
                    // ->orderByRaw('CONCAT(year, "-", number) DESC')
                    ->orderBy('year', 'desc')
                    ->orderBy('number', 'desc');
            })
            /* ->defaultGroup(
                Group::make('year')
                    ->label('')
                    ->orderQueryUsing(fn (Builder $query): Builder => $query->orderBy('year', 'desc')->orderBy('number', 'asc')),
            ) */
            ->columns([
                TextColumn::make('year')
                    ->label('Anno'),
                TextColumn::make('number')
                    ->label('Numero'),
                TextColumn::make('client.name')
                    ->label('Cliente'),
                TextColumn::make('amount')
                    ->label('Importo')
                    ->money('EUR')
                    ->summarize(
                        Sum::make()
                            ->money('EUR')
                            ->hiddenLabel()
                    ),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge(),
                TextColumn::make('sent_at')
                    ->label('Data invio')
                    ->date(),
                TextColumn::make('collected_at')
                    ->label('Data incasso')
                    ->date(),
                IconColumn::make('note')
                    ->label('')
                    ->icon(Heroicon::OutlinedInformationCircle)
                    ->tooltip(fn ($state) => $state ?: null),
                // lista dei progetti, con badge colorato per ogni progetto
                TextColumn::make('projects')
                    ->label('Progetti')
                    // ->getStateUsing(fn ($record) => $record->projects) superfluo
                    ->formatStateUsing(fn ($state) => $state->name)
                    ->badge()
                    ->color(fn ($state) => filled($state->color) ? Color::hex($state->color) : 'gray'),
            ])
            ->filters([
                SelectFilter::make('year')
                    ->label('Anno')
                    ->options(function () {
                        $r = array_reverse(range(2020, (int) date('Y')));
                        $options = array_combine($r, $r);

                        return $options;
                    })
                    ->default(date('Y')),
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options(InvoiceStatus::class),
                SelectFilter::make('projects')
                    ->label('Progetto')
                    ->relationship(
                        name: 'projects',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('workspace_id', Filament::getTenant()?->getKey()),
                    )
                    ->searchable()
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()
                    ->hiddenLabel(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->paginationPageOptions([25, 50, 100, 250, 'all'])
            ->persistFiltersInSession();
    }
}
