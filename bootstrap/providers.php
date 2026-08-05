<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AppPanelProvider;
use App\Providers\FortifyServiceProvider;
use Mokhosh\FilamentKanban\FilamentKanbanServiceProvider;

return [
    AppServiceProvider::class,
    AppPanelProvider::class,
    FortifyServiceProvider::class,
    // Lives in packages/ and is only PSR-4 autoloaded, so Composer's package
    // discovery never sees it — it has to be registered by hand.
    FilamentKanbanServiceProvider::class,
];
