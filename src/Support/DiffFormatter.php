<?php

namespace Digit7s\FilamentAuditToolkit\Support;

use Digit7s\FilamentAuditToolkit\Diff\DiffBuilder;
use JsonException;

final class DiffFormatter
{
    private const DEFAULT_MAX_DEPTH = 6;

    private const DEFAULT_MAX_CHANGES = 200;

    private const DEFAULT_MAX_JSON_BYTES = 12_000;

    /**
     * @return array<int, array{path: string, type: string, before: string, after: string}>
     */
    public static function changes(
        array $before,
        array $after,
        int $maxDepth = self::DEFAULT_MAX_DEPTH,
        int $maxChanges = self::DEFAULT_MAX_CHANGES,
    ): array {
        return array_map(
            fn ($entry): array => [
                'path' => $entry->path,
                'type' => $entry->type,
                'before' => $entry->beforeDisplay,
                'after' => $entry->afterDisplay,
            ],
            (new DiffBuilder(
                maxDepth: max(0, $maxDepth),
                maxEntries: max(1, $maxChanges),
                maxValueLength: 4_000,
            ))->build($before, $after)->entries(),
        );
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public static function format(array $before, array $after): string
    {
        $lines = [];

        foreach (self::changes($before, $after) as $change) {
            $lines[] = '- '.$change['path'].': '.$change['before'];
            $lines[] = '+ '.$change['path'].': '.$change['after'];
        }

        return $lines === [] ? 'No changed values.' : implode("\n", $lines);
    }

    public static function summary(array $before, array $after, int $limit = 3): string
    {
        $changes = self::changes($before, $after);

        if ($changes === []) {
            return 'No changed values.';
        }

        $summary = collect(array_slice($changes, 0, max(1, $limit)))
            ->map(fn (array $change): string => ucfirst($change['type']).' '.$change['path'])
            ->implode(', ');

        $remaining = count($changes) - max(1, $limit);

        return $remaining > 0 ? $summary.' +'.$remaining.' more' : $summary;
    }

    public static function json(mixed $value, int $maxBytes = self::DEFAULT_MAX_JSON_BYTES): string
    {
        try {
            $encoded = json_encode(
                $value,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return '[UNSERIALIZABLE]';
        }

        if (strlen($encoded) <= $maxBytes) {
            return $encoded;
        }

        return substr($encoded, 0, max(1, $maxBytes))."\n… [truncated]";
    }
}
