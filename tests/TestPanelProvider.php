<?php

namespace Digit7s\FilamentAuditToolkit\Tests;

use Digit7s\FilamentAuditToolkit\FilamentAuditToolkitPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('test')
            ->path('test')
            ->authGuard('web')
            ->plugin(
                FilamentAuditToolkitPlugin::make()
                    ->authorization(
                        viewAny: fn (?object $user): bool => $user !== null,
                        view: fn (): bool => true,
                        viewRawValues: fn (): bool => true,
                    ),
            );
    }
}
