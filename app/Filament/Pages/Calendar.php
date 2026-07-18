<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\TimeEntryCalendarWidget;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Calendar extends Page
{
    protected string $view = 'filament.pages.calendar';

    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Calendar';

    protected static ?string $title = 'Calendar';

    protected function getHeaderWidgets(): array
    {
        return [
            TimeEntryCalendarWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
