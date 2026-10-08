<?php

namespace Digit7s\FilamentAuditToolkit\Support;

use Closure;
use Digit7s\AuditToolkit\Models\AuditEvent;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Throwable;

final class AuditAuthorization
{
    /** @var array<string, array{viewAny: ?Closure, view: ?Closure, viewRawValues: ?Closure, viewSubjectHistory: ?Closure, viewOriginalActor: ?Closure}> */
    private array $configurations = [];

    public function configure(
        ?Closure $viewAny = null,
        ?Closure $view = null,
        ?Closure $viewRawValues = null,
        ?Closure $viewSubjectHistory = null,
        ?Closure $viewOriginalActor = null,
        ?string $panelId = null,
    ): void {
        $panelId ??= $this->currentPanelId() ?? '__default';

        $this->configurations[$panelId] = [
            'viewAny' => $viewAny,
            'view' => $view,
            'viewRawValues' => $viewRawValues,
            'viewSubjectHistory' => $viewSubjectHistory,
            'viewOriginalActor' => $viewOriginalActor,
        ];
    }

    public function canViewAny(): bool
    {
        $callback = $this->configuration()['viewAny'];

        return $callback !== null && (bool) $callback($this->user());
    }

    public function canView(AuditEvent $event): bool
    {
        $callback = $this->configuration()['view'];

        return $callback !== null && (bool) $callback($event, $this->user());
    }

    public function canViewRawValues(AuditEvent $event): bool
    {
        $callback = $this->configuration()['viewRawValues'];

        if ($callback === null) {
            return $this->canView($event);
        }

        return (bool) $callback($event, $this->user());
    }

    public function canViewSubjectHistory(Model $subject): bool
    {
        $callback = $this->configuration()['viewSubjectHistory'];

        if ($callback !== null) {
            return (bool) $callback($subject, $this->user());
        }

        return $this->canViewAny();
    }

    public function canViewOriginalActor(AuditEvent $event): bool
    {
        $callback = $this->configuration()['viewOriginalActor'];

        if ($callback === null) {
            return $this->canView($event);
        }

        return (bool) $callback($event, $this->user());
    }

    /** @return array{viewAny: ?Closure, view: ?Closure, viewRawValues: ?Closure, viewSubjectHistory: ?Closure, viewOriginalActor: ?Closure} */
    private function configuration(): array
    {
        $panelId = $this->currentPanelId();

        return $this->configurations[$panelId ?? '__default']
            ?? $this->configurations['__default']
            ?? [
                'viewAny' => null,
                'view' => null,
                'viewRawValues' => null,
                'viewSubjectHistory' => null,
                'viewOriginalActor' => null,
            ];
    }

    private function currentPanelId(): ?string
    {
        try {
            return Filament::getCurrentOrDefaultPanel()?->getId();
        } catch (Throwable) {
            return null;
        }
    }

    private function user(): mixed
    {
        try {
            return Filament::auth()->user();
        } catch (Throwable) {
            return null;
        }
    }
}
