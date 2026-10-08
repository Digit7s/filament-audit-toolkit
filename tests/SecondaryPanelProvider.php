<?php

namespace Digit7s\FilamentAuditToolkit\Tests;

use Digit7s\FilamentAuditToolkit\FilamentAuditToolkitPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class SecondaryPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('secondary')
            ->path('secondary')
            ->authGuard('admin')
            ->plugin(
                FilamentAuditToolkitPlugin::make()
                    ->authorization(
                        viewAny: fn (?object $user): bool => $user?->name === 'Secondary Admin',
                        view: fn ($event, ?object $user): bool => $user?->name === 'Secondary Admin' && $event->event === 'secondary.allowed',
                        viewRawValues: fn ($event, ?object $user): bool => $user?->name === 'Secondary Admin',
                        viewSubjectHistory: fn ($subject, ?object $user): bool => $user?->name === 'Secondary Admin',
                    ),
            );
    }
}
