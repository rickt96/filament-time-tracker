<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // register estensione custom richEditor (TipTap)
        // per aprire i link in edit con ctrl+click.
        // vedi app\Filament\Forms\Components\RichEditor\OpenLinkOnCtrlClickPlugin.php
        FilamentAsset::register([
            Js::make('open-link-on-ctrl-click', resource_path('js/rich-editor/open-link-on-ctrl-click.js')),
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );

        // default setting tabelle
        Table::configureUsing(function (Table $table): void {
            $table
                ->paginated([25, 50, 100, 250])
                ->defaultPaginationPageOption(50)
                ->filtersLayout(FiltersLayout::Modal);
        });
    }
}
