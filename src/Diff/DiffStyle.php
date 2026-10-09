<?php

namespace Digit7s\FilamentAuditToolkit\Diff;

enum DiffStyle: string
{
    case Unified = 'unified';
    case Split = 'split';
    case Fields = 'fields';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::Unified->value => 'Unified',
            self::Split->value => 'Split',
            self::Fields->value => 'Fields',
        ];
    }

    public static function label(string $style): string
    {
        return self::labels()[$style] ?? self::labels()[self::Unified->value];
    }

    /**
     * @param  array<int, mixed>  $styles
     * @return array<int, string>
     */
    public static function normalizeList(array $styles): array
    {
        $normalized = [];

        foreach ($styles as $style) {
            $style = is_string($style) ? self::tryFrom($style)?->value : null;

            if ($style !== null && ! in_array($style, $normalized, true)) {
                $normalized[] = $style;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<int, string>  $allowed
     */
    public static function normalize(?string $style, array $allowed, string $fallback): string
    {
        if ($style !== null && in_array($style, $allowed, true)) {
            return $style;
        }

        return in_array($fallback, $allowed, true)
            ? $fallback
            : ($allowed[0] ?? self::Unified->value);
    }
}
