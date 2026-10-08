<?php

namespace Digit7s\FilamentAuditToolkit;

use Closure;
use Digit7s\FilamentAuditToolkit\Resources\AuditEventResource;
use Digit7s\FilamentAuditToolkit\Support\AuditAuthorization;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentAuditToolkitPlugin implements Plugin
{
    private ?Closure $viewAnyCallback = null;

    private ?Closure $viewCallback = null;

    private ?Closure $viewRawValuesCallback = null;

    private ?Closure $viewSubjectHistoryCallback = null;

    private ?Closure $viewOriginalActorCallback = null;

    public static function make(): static
    {
        return new self;
    }

    public function getId(): string
    {
        return 'filament-audit-toolkit';
    }

    public function authorization(
        Closure $viewAny,
        ?Closure $view = null,
        ?Closure $viewRawValues = null,
        ?Closure $viewSubjectHistory = null,
        ?Closure $viewOriginalActor = null,
    ): static {
        $this->viewAnyCallback = $viewAny;
        $this->viewCallback = $view;
        $this->viewRawValuesCallback = $viewRawValues;
        $this->viewSubjectHistoryCallback = $viewSubjectHistory;
        $this->viewOriginalActorCallback = $viewOriginalActor;

        return $this;
    }

    public function register(Panel $panel): void
    {
        app(AuditAuthorization::class)->configure(
            viewAny: $this->viewAnyCallback,
            view: $this->viewCallback,
            viewRawValues: $this->viewRawValuesCallback,
            viewSubjectHistory: $this->viewSubjectHistoryCallback,
            viewOriginalActor: $this->viewOriginalActorCallback,
            panelId: $panel->getId(),
        );

        $panel->resources([
            AuditEventResource::class,
        ]);
    }

    public function boot(Panel $panel): void {}
}
