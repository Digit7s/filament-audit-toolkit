<?php

namespace Digit7s\FilamentAuditToolkit;

use Digit7s\FilamentAuditToolkit\Support\AuditAuthorization;
use Digit7s\FilamentAuditToolkit\Support\DiffConfiguration;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;

class FilamentAuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filament-audit-toolkit.php', 'filament-audit-toolkit');
        $this->app->singleton(AuditAuthorization::class);
        $this->app->singleton(DiffConfiguration::class);
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-audit-toolkit');
    }

    public function boot(): void
    {
        FilamentAsset::register([
            Css::make('filament-audit-toolkit', __DIR__.'/../resources/css/filament-audit-toolkit.css'),
        ], package: 'digit7s/filament-audit-toolkit');
    }
}
