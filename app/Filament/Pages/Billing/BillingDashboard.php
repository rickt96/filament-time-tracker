<?php

namespace App\Filament\Pages\Billing;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Workspace;
use App\Services\Billing\InvoiceReportService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Financial-control dashboard: how much value has been produced (from
 * logged hours), how much of that has actually been invoiced and collected,
 * and what's still outstanding — the three figures a plain Invoice list
 * doesn't answer on its own. See InvoiceReportService for the aggregation
 * logic behind every widget/table on this page.
 */
class BillingDashboard extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.billing.billing-dashboard';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Fatturazione';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 40;

    protected static ?string $title = 'Dashboard fatturazione';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $filters = [];

    public function mount(): void
    {
        $this->filters['year'] ??= (string) now()->year;

        $this->getSchema('filtersForm')?->fill();
    }

    public function filtersForm(Schema $schema): Schema
    {
        $workspace = $this->workspace();

        return $schema
            ->statePath('filters')
            ->columns(4)
            ->components([
                Select::make('year')
                    ->label('Anno')
                    ->options(fn (): array => $this->availableYears())
                    ->live(),
                Select::make('client_id')
                    ->label('Cliente')
                    ->options(fn () => Client::query()->where('workspace_id', $workspace->id)->pluck('name', 'id'))
                    ->searchable()
                    ->live(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => app(InvoiceReportService::class)->outstandingInvoicesQuery($this->workspace(), $this->filters ?? []))
            ->heading('Fatture in attesa di incasso')
            ->columns([
                TextColumn::make('client.name')
                    ->label('Cliente'),
                TextColumn::make('label')
                    ->label('Fattura'),
                TextColumn::make('amount')
                    ->label('Importo')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('sent_at')
                    ->label('Data invio')
                    ->date()
                    ->sortable(),
                TextColumn::make('days_pending')
                    ->label('Giorni in attesa')
                    ->getStateUsing(fn (Invoice $record): int => (int) $record->sent_at?->diffInDays(now()))
                    ->sortable(),
            ])
            ->paginated([10, 25, 50]);
    }

    /**
     * @return array<int, array{client_id: int, client_name: string, produced: string, invoiced: string, collected: string, gap: string}>
     */
    public function getClientBreakdown(): array
    {
        return app(InvoiceReportService::class)
            ->totalsByClient($this->workspace(), $this->filters ?? [])
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function availableYears(): array
    {
        $firstYear = Invoice::query()
            ->where('workspace_id', $this->workspace()->id)
            ->min('year') ?? now()->year;

        $years = range((int) now()->year, (int) $firstYear);

        return array_combine($years, $years);
    }

    private function workspace(): Workspace
    {
        /** @var Workspace $workspace */
        $workspace = Filament::getTenant();

        return $workspace;
    }
}
