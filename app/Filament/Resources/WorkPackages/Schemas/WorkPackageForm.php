<?php

namespace App\Filament\Resources\WorkPackages\Schemas;

use App\Enums\WorkPackageStatus;
use App\Models\WorkPackage;
use App\Services\Budget\BudgetUtilizationService;
use Filament\Facades\Filament;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class WorkPackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dati work package')
                    ->columns(2)
                    ->components([
                        Select::make('project_id')
                            ->label('Progetto')
                            ->relationship(
                                name: 'project',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->where('workspace_id', Filament::getTenant()?->getKey()),
                            )
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                        Select::make('status')
                            ->label('Stato')
                            ->options(WorkPackageStatus::class)
                            ->default(WorkPackageStatus::Planned)
                            ->required(),
                        TextInput::make('budget_hours')
                            ->label('Budget ore')
                            ->numeric()
                            ->suffix('h'),
                        TextInput::make('hourly_rate')
                            ->label('Tariffa oraria')
                            ->numeric()
                            ->prefix('€')
                            ->helperText('Se lasciata vuota, viene usata la tariffa del progetto.'),
                        TextInput::make('sort_order')
                            ->label('Ordinamento')
                            ->numeric()
                            ->default(0),
                    ]),
                Section::make('Dashboard budget')
                    ->visible(fn (?WorkPackage $record): bool => $record !== null)
                    ->columns(3)
                    ->components([
                        Placeholder::make('budget_consumed')
                            ->label('Ore consumate / budget')
                            ->content(function (?WorkPackage $record): string {
                                if (! $record) {
                                    return '—';
                                }

                                $snapshot = app(BudgetUtilizationService::class)->forWorkPackage($record);

                                return $snapshot->budgetHours !== null
                                    ? "{$snapshot->consumedHours} / {$snapshot->budgetHours} h"
                                    : "{$snapshot->consumedHours} h (nessun budget impostato)";
                            }),
                        Placeholder::make('budget_utilization')
                            ->label('Percentuale utilizzo')
                            ->content(function (?WorkPackage $record): HtmlString {
                                if (! $record) {
                                    return new HtmlString('—');
                                }

                                $snapshot = app(BudgetUtilizationService::class)->forWorkPackage($record);

                                if ($snapshot->utilizationPercentage === null) {
                                    return new HtmlString('—');
                                }

                                return new HtmlString(sprintf(
                                    '<span class="fi-badge fi-color-%s">%s%%</span>',
                                    $snapshot->statusColor(),
                                    $snapshot->utilizationPercentage,
                                ));
                            }),
                        Placeholder::make('budget_remaining_hours')
                            ->label('Ore residue')
                            ->content(function (?WorkPackage $record): string {
                                if (! $record) {
                                    return '—';
                                }

                                $snapshot = app(BudgetUtilizationService::class)->forWorkPackage($record);

                                return $snapshot->remainingHours !== null ? "{$snapshot->remainingHours} h" : '—';
                            }),
                        Placeholder::make('budget_task_count')
                            ->label('Numero task')
                            ->content(fn (?WorkPackage $record): string => $record ? (string) $record->tasks()->count() : '—'),
                        Placeholder::make('budget_hourly_rate')
                            ->label('Tariffa oraria applicata')
                            ->content(function (?WorkPackage $record): string {
                                if (! $record) {
                                    return '—';
                                }

                                $rate = $record->effectiveHourlyRate();

                                return $rate !== null ? "€ {$rate}/h" : '—';
                            }),
                        Placeholder::make('budget_revenue')
                            ->label('Ricavo totale')
                            ->content(function (?WorkPackage $record): string {
                                if (! $record) {
                                    return '—';
                                }

                                $snapshot = app(BudgetUtilizationService::class)->forWorkPackage($record);

                                return "€ {$snapshot->totalRevenue}";
                            }),
                    ]),

                Section::make()
                    ->columnSpanFull()
                    ->schema([
                        MarkdownEditor::make('description')
                            ->label('Descrizione')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
