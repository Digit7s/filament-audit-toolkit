<?php

namespace Digit7s\FilamentAuditToolkit\Support;

use Digit7s\FilamentAuditToolkit\Diff\DiffStyle;

final class DiffConfiguration
{
    /**
     * @var array<string, array{style: string, available_styles: array<int, string>, allow_switching: bool}>
     */
    private array $panels = [];

    /**
     * @param  array<int, string>|null  $availableStyles
     */
    public function configure(string $panelId, ?string $style = null, ?array $availableStyles = null, ?bool $allowSwitching = null): void
    {
        $globalAvailable = DiffStyle::normalizeList((array) config('filament-audit-toolkit.diff.available_styles', array_keys(DiffStyle::labels())));
        $globalAvailable = $globalAvailable === [] ? [DiffStyle::Unified->value] : $globalAvailable;
        $available = $availableStyles === null ? $globalAvailable : DiffStyle::normalizeList($availableStyles);
        $available = $available === [] ? $globalAvailable : $available;
        $fallback = DiffStyle::normalize((string) config('filament-audit-toolkit.diff.default_style', DiffStyle::Unified->value), $available, DiffStyle::Unified->value);

        $this->panels[$panelId] = [
            'style' => DiffStyle::normalize($style, $available, $fallback),
            'available_styles' => $available,
            'allow_switching' => $allowSwitching ?? (bool) config('filament-audit-toolkit.diff.allow_style_switching', false),
        ];
    }

    /**
     * @return array{style: string, available_styles: array<int, string>, allow_switching: bool}
     */
    public function forPanel(?string $panelId): array
    {
        if ($panelId !== null && isset($this->panels[$panelId])) {
            return $this->panels[$panelId];
        }

        $this->configure('__global__');

        return $this->panels['__global__'];
    }

    public function defaultStyle(?string $panelId): string
    {
        return $this->forPanel($panelId)['style'];
    }

    /**
     * @return array<string, string>
     */
    public function styleOptions(?string $panelId): array
    {
        $configuration = $this->forPanel($panelId);

        return array_intersect_key(DiffStyle::labels(), array_flip($configuration['available_styles']));
    }

    public function allowsSwitching(?string $panelId): bool
    {
        return $this->forPanel($panelId)['allow_switching'];
    }

    public function resolve(?string $panelId, ?string $requested): string
    {
        $configuration = $this->forPanel($panelId);
        $requested = $configuration['allow_switching'] ? $requested : null;

        return DiffStyle::normalize($requested, $configuration['available_styles'], $configuration['style']);
    }
}
