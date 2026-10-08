<?php

namespace Digit7s\FilamentAuditToolkit;

use Digit7s\FilamentAuditToolkit\Support\AuditAuthorization;
use Illuminate\Support\ServiceProvider;

class FilamentAuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filament-audit-toolkit.php', 'filament-audit-toolkit');
        $this->app->singleton(AuditAuthorization::class);
    }
}
