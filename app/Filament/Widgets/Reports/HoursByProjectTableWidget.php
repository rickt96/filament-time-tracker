<?php

namespace App\Filament\Widgets\Reports;

use App\Services\Reports\TimeReportService;
use App\Support\DurationFormatter;
use Filament\Actions\BulkActionGroup;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Override;
use TimeEntry;

class HoursByProjectTableWidget extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = "";

    protected int | string | array $columnSpan = [
        'md' => 2
    ];

    //protected ?string $maxHeight = "400px";

    public function table(Table $table): Table
    {  
        return $table
            ->records(function() {
                if (blank($this->filters['from'] ?? null) || blank($this->filters['until'] ?? null)) {
                    return [];
                }

                $rows = app(TimeReportService::class)
                        ->totalsByProject(Filament::getTenant(), $this->filters ?? []);

                $rows[] = [
                    'project_id' => null,
                    'project_name' => "TOTALE",
                    'color' => null,
                    'hours' => collect($rows)->sum("hours"), // DurationFormatter::hoursMinutesSeconds(),
                    'amount' => collect($rows)->sum("amount")
                ];

                return $rows;
            })
            ->columns([
                TextColumn::make('project_name')
                    ->label('Progetto')
                    ->color(fn($record) => isset($record["project_color"]) ? Color::hex($record["project_color"]) : null),
                TextColumn::make('hours')
                    ->label('Ore'),
                TextColumn::make('amount')
                    ->label('Importo')
                    ->money('EUR'),

            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }
}
