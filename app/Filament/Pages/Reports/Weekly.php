<?php

namespace App\Filament\Pages\Reports;

use App\Models\Client;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\WorkPackage;
use App\Models\Workspace;
use App\Services\Reports\TimeReportService;
use App\Support\DurationFormatter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use UnitEnum;

class Weekly extends Page
{
    protected string $view = 'filament.pages.reports.weekly';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Report';

    protected static ?int $navigationSort = 32;

    protected static ?string $navigationLabel = 'Settimanale';

    protected static ?string $title = 'Report — Settimanale';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $filters = [];

    public function mount(): void
    {
        $this->filters['from'] ??= now()->startOfWeek()->toDateString();
        $this->filters['until'] ??= now()->endOfWeek()->toDateString();

        $this->getSchema('filtersForm')?->fill($this->filters);
    }

    public function filtersForm(Schema $schema): Schema
    {
        $workspace = $this->workspace();

        return $schema
            ->statePath('filters')
            ->columns(4)
            ->components([
                Select::make('project_id')
                    ->label('Progetto')
                    ->options(fn () => Project::query()->where('workspace_id', $workspace->id)->pluck('name', 'id'))
                    ->searchable()
                    ->live(),
                Select::make('client_id')
                    ->label('Cliente')
                    ->options(fn () => Client::query()->where('workspace_id', $workspace->id)->pluck('name', 'id'))
                    ->searchable()
                    ->live(),
                Select::make('tag_id')
                    ->label('Tag')
                    ->options(fn () => Tag::query()->where('workspace_id', $workspace->id)->pluck('name', 'id'))
                    ->searchable()
                    ->live(),
                Select::make('user_id')
                    ->label('Utente')
                    ->options(fn () => $workspace->users()->pluck('users.name', 'users.id'))
                    ->searchable()
                    ->live(),
                Select::make('task_id')
                    ->label('Task')
                    ->options(fn () => Task::query()
                        ->whereHas('workPackage.project', fn (Builder $query) => $query->where('workspace_id', $workspace->id))
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->live(),
                Select::make('work_package_id')
                    ->label('Work Package')
                    ->options(fn () => WorkPackage::query()
                        ->whereHas('project', fn (Builder $query) => $query->where('workspace_id', $workspace->id))
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->live(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previousWeek')
                ->label('Settimana precedente')
                ->icon('heroicon-o-chevron-left')
                ->action(fn () => $this->shiftWeek(-7)),
            Action::make('nextWeek')
                ->label('Settimana successiva')
                ->icon('heroicon-o-chevron-right')
                ->action(fn () => $this->shiftWeek(7)),
        ];
    }

    private function shiftWeek(int $days): void
    {
        $this->filters['from'] = Carbon::parse($this->filters['from'] ?? now())->addDays($days)->toDateString();
        $this->filters['until'] = Carbon::parse($this->filters['until'] ?? now())->addDays($days)->toDateString();

        $this->getSchema('filtersForm')?->fill($this->filters);
    }

    /**
     * @return Collection<int, Carbon>
     */
    public function getWeekDays(): Collection
    {
        $from = Carbon::parse($this->filters['from'] ?? now()->startOfWeek());
        $until = Carbon::parse($this->filters['until'] ?? now()->endOfWeek());

        return collect(range(0, max(0, $from->diffInDays($until))))
            ->map(fn (int $offset): Carbon => $from->copy()->addDays($offset));
    }

    /**
     * Project rows for the currently selected week, each with per-day
     * durations already formatted as H:MM:SS for the matrix.
     *
     * @return Collection<int, array{project_id: int, project_name: string, client_name: string, days: array<string, string>, total: string}>
     */
    public function getRows(): Collection
    {
        $days = $this->getWeekDays();

        return app(TimeReportService::class)
            ->totalsByProjectAndDay($this->workspace(), $this->filters ?? [])
            ->map(fn (array $row): array => [
                'project_id' => $row['project_id'],
                'project_name' => $row['project_name'],
                'client_name' => $row['client_name'],
                'days' => $days->mapWithKeys(fn (Carbon $day): array => [
                    $day->toDateString() => DurationFormatter::hoursMinutesSeconds($row['days'][$day->toDateString()] ?? 0),
                ])->all(),
                'total' => DurationFormatter::hoursMinutesSeconds($row['total_seconds']),
            ]);
    }

    /**
     * @return array<string, string>
     */
    public function getDailyTotals(): array
    {
        $days = $this->getWeekDays();
        $rows = app(TimeReportService::class)->totalsByProjectAndDay($this->workspace(), $this->filters ?? []);

        return $days->mapWithKeys(function (Carbon $day) use ($rows): array {
            $seconds = $rows->sum(fn (array $row): int => $row['days'][$day->toDateString()] ?? 0);

            return [$day->toDateString() => DurationFormatter::hoursMinutesSeconds($seconds)];
        })->all();
    }

    public function getGrandTotal(): string
    {
        return DurationFormatter::hoursMinutesSeconds(
            app(TimeReportService::class)->totalSeconds($this->workspace(), $this->filters ?? []),
        );
    }

    private function workspace(): Workspace
    {
        /** @var Workspace $workspace */
        $workspace = Filament::getTenant();

        return $workspace;
    }
}
