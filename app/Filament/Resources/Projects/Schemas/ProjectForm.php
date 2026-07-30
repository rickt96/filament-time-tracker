<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\ProjectStatus;
use App\Enums\ProjectVisibility;
use App\Models\Project;
use App\Services\Budget\BudgetUtilizationService;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make()
                    ->columns(3)
                    ->columnSpanFull()
                    ->components([
                        TextInput::make('name')
                            ->columnSpan(2)
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                        ColorPicker::make('color')
                            ->columnSpan(1)
                            ->label('Colore'),
                        Select::make('client_id')
                            ->label('Cliente')
                            ->relationship(
                                name: 'client',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->where('is_active', true),
                            )
                            ->searchable()
                            ->preload()
                            ->required(),
                        Textarea::make('description')
                            ->label('Descrizione')
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        ToggleButtons::make('status')
                            ->label('Stato')
                            ->options(ProjectStatus::class)
                            ->default(ProjectStatus::Active)
                            ->inline()
                            ->required(),
                        ToggleButtons::make('visibility')
                            ->label('Visibilità')
                            ->options(ProjectVisibility::class)
                            ->default(ProjectVisibility::Public)
                            ->inline()
                            ->required(),
                    ]),

                Section::make('Note')
                    ->columnSpanFull()
                    ->schema([
                        MarkdownEditor::make('note')
                            ->hiddenLabel()
                            ->columnSpanFull(),
                    ]),

                // budget lo impostiamo a livello di workpackage
                /* Section::make('Budget')
                    ->columns(2)
                    ->components([
                        TextInput::make('budget_hours')
                            ->label('Budget ore')
                            ->numeric()
                            ->suffix('h'),
                        TextInput::make('hourly_rate')
                            ->label('Tariffa oraria')
                            ->numeric()
                            ->prefix('€'),
                    ]), */

                // va spostato da un'altra parte
                /* Section::make('Dashboard budget')
                    ->visible(fn (?Project $record): bool => $record !== null)
                    ->columns(3)
                    ->components([
                        Placeholder::make('budget_consumed')
                            ->label('Ore consumate / budget')
                            ->content(function (?Project $record): string {
                                if (! $record) {
                                    return '—';
                                }

                                $snapshot = app(BudgetUtilizationService::class)->forProject($record);

                                return $snapshot->budgetHours !== null
                                    ? "{$snapshot->consumedHours} / {$snapshot->budgetHours} h"
                                    : "{$snapshot->consumedHours} h (nessun budget impostato)";
                            }),
                        Placeholder::make('budget_utilization')
                            ->label('Percentuale utilizzo')
                            ->content(function (?Project $record): HtmlString {
                                if (! $record) {
                                    return new HtmlString('—');
                                }

                                $snapshot = app(BudgetUtilizationService::class)->forProject($record);

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
                            ->content(function (?Project $record): string {
                                if (! $record) {
                                    return '—';
                                }

                                $snapshot = app(BudgetUtilizationService::class)->forProject($record);

                                return $snapshot->remainingHours !== null ? "{$snapshot->remainingHours} h" : '—';
                            }),
                        Placeholder::make('budget_revenue')
                            ->label('Ricavo totale')
                            ->content(function (?Project $record): string {
                                if (! $record) {
                                    return '—';
                                }

                                $snapshot = app(BudgetUtilizationService::class)->forProject($record);

                                return "€ {$snapshot->totalRevenue}";
                            }),
                        Placeholder::make('budget_average_rate')
                            ->label('Tariffa media applicata')
                            ->content(function (?Project $record): string {
                                if (! $record) {
                                    return '—';
                                }

                                $snapshot = app(BudgetUtilizationService::class)->forProject($record);

                                return $snapshot->averageRate !== null ? "€ {$snapshot->averageRate}/h" : '—';
                            }),
                        Placeholder::make('budget_economic_remaining')
                            ->label('Budget economico residuo')
                            ->content(function (?Project $record): string {
                                if (! $record) {
                                    return '—';
                                }

                                $snapshot = app(BudgetUtilizationService::class)->forProject($record);

                                return $snapshot->economicBudgetRemaining !== null ? "€ {$snapshot->economicBudgetRemaining}" : '—';
                            }),
                    ]),*/

            ]);
    }
}
