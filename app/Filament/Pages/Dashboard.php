<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\FavoritesWidget;
use App\Filament\Widgets\RecentsWidget;
use App\Filament\Widgets\TasksDueWidget;
use App\Filament\Widgets\TimeStatsOverviewWidget;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Override;

class Dashboard extends BaseDashboard
{
    protected Width|string|null $maxContentWidth = 'full';

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return Heroicon::OutlinedHome;
    }

    #[Override]
    public function getTitle(): string|Htmlable
    {
        return 'Ciao '.Auth::user()->name.' 👋';
    }

    /**
     * Ordine per priorità di lettura: numeri (quanto ho lavorato) prima di
     * tutto, poi cosa richiede azione (task in scadenza), poi scorciatoie
     * di navigazione (preferiti, recenti) — dal più urgente al più
     * "di servizio".
     */
    public function getWidgets(): array
    {
        return [
            TimeStatsOverviewWidget::class,
            TasksDueWidget::class,
            FavoritesWidget::class,
            RecentsWidget::class,
        ];
    }
}
