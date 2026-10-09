<?php

namespace Digit7s\FilamentAuditToolkit\Diff;

use Illuminate\Support\Str;
use Throwable;

final class DiffBuilder
{
    public function __construct(
        private readonly int $maxDepth = 5,
        private readonly int $maxEntries = 100,
        private readonly int $maxValueLength = 2_000,
        private readonly int $maxArrayElements = 100,
    ) {}

    /**
     * Build a deterministic diff from already-authorized safe values.
     *
     * Arrays are compared by key, and list values are compared by numeric index.
     * This intentionally does not attempt move detection.
     *
     * @param  array<string|int, mixed>  $before
     * @param  array<string|int, mixed>  $after
     */
    public function build(array $before, array $after): DiffResult
    {
        $entries = [];
        $truncated = false;

        try {
            $this->walk($before, $after, '', 0, $entries, $truncated);
        } catch (Throwable) {
            // The detail page must remain usable if legacy or malformed values
            // contain an unserializable structure.
            $entries = [new DiffEntry(
                '[unavailable]',
                'truncated',
                true,
                true,
                '[unable to compare]',
                '[unable to compare]',
                'unknown',
                'Unavailable',
                true,
            )];
            $truncated = true;
        }

        return new DiffResult($entries, $truncated);
    }

    /**
     * @param  array<string|int, mixed>  $before
     * @param  array<string|int, mixed>  $after
     * @param  array<int, DiffEntry>  $entries
     */
    private function walk(array $before, array $after, string $prefix, int $depth, array &$entries, bool &$truncated): void
    {
        $keys = array_values(array_unique([...array_keys($before), ...array_keys($after)], SORT_REGULAR));
        $keys = array_slice($keys, 0, $this->maxArrayElements);

        foreach ($keys as $key) {
            if (count($entries) >= max(1, $this->maxEntries)) {
                $this->appendTruncation($entries, $truncated);

                return;
            }

            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $beforeExists = array_key_exists($key, $before);
            $afterExists = array_key_exists($key, $after);
            $beforeValue = $beforeExists ? $before[$key] : null;
            $afterValue = $afterExists ? $after[$key] : null;

            if ($beforeExists && $afterExists && $beforeValue === $afterValue) {
                continue;
            }

            if ($beforeExists && $afterExists && is_array($beforeValue) && is_array($afterValue)) {
                if ($depth >= max(0, $this->maxDepth)) {
                    $this->append($entries, $path, 'modified', $beforeValue, $afterValue, true, true, true);
                } else {
                    $this->walk($beforeValue, $afterValue, $path, $depth + 1, $entries, $truncated);
                }

                continue;
            }

            $type = match (true) {
                ! $beforeExists => 'added',
                ! $afterExists => 'removed',
                default => 'modified',
            };

            $this->append($entries, $path, $type, $beforeValue, $afterValue, $beforeExists, $afterExists);
        }

        if (count(array_keys($before)) > $this->maxArrayElements || count(array_keys($after)) > $this->maxArrayElements) {
            $this->appendTruncation($entries, $truncated);
        }
    }

    /**
     * @param  array<int, DiffEntry>  $entries
     */
    private function append(
        array &$entries,
        string $path,
        string $type,
        mixed $before,
        mixed $after,
        bool $beforeExists,
        bool $afterExists,
        bool $isTruncated = false,
    ): void {
        $entries[] = new DiffEntry(
            $path,
            $type,
            $beforeExists,
            $afterExists,
            $this->display($before, $beforeExists),
            $this->display($after, $afterExists),
            $this->valueType($before, $beforeExists, $after, $afterExists),
            Str::of($path)->replace(['.', '_', '-'], ' ')->headline()->toString(),
            $isTruncated,
        );
    }

    /**
     * @param  array<int, DiffEntry>  $entries
     */
    private function appendTruncation(array &$entries, bool &$truncated): void
    {
        if ($truncated) {
            return;
        }

        $entries[] = new DiffEntry(
            '[additional changes]',
            'truncated',
            true,
            true,
            '[additional changes omitted]',
            '[additional changes omitted]',
            'truncated',
            'Additional changes omitted',
            true,
        );
        $truncated = true;
    }

    private function display(mixed $value, bool $exists): string
    {
        if (! $exists) {
            return '[missing]';
        }

        try {
            $encoded = json_encode(
                $value,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
        } catch (Throwable) {
            return '[unserializable]';
        }

        if (strlen($encoded) <= max(1, $this->maxValueLength)) {
            return $encoded;
        }

        return substr($encoded, 0, max(1, $this->maxValueLength))."\n… [value truncated]";
    }

    private function valueType(mixed $before, bool $beforeExists, mixed $after, bool $afterExists): string
    {
        $beforeType = $beforeExists ? get_debug_type($before) : 'missing';
        $afterType = $afterExists ? get_debug_type($after) : 'missing';

        return $beforeType === $afterType ? $beforeType : $beforeType.' → '.$afterType;
    }
}
