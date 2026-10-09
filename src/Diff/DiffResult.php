<?php

namespace Digit7s\FilamentAuditToolkit\Diff;

final readonly class DiffResult
{
    /**
     * @param  array<int, DiffEntry>  $entries
     */
    public function __construct(
        private array $entries,
        private bool $truncated = false,
    ) {}

    /**
     * @return array<int, DiffEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public function isEmpty(): bool
    {
        return $this->changeCount() === 0;
    }

    public function isTruncated(): bool
    {
        return $this->truncated;
    }

    public function changeCount(): int
    {
        return count(array_filter($this->entries, fn (DiffEntry $entry): bool => $entry->type !== 'truncated'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(fn (DiffEntry $entry): array => $entry->toArray(), $this->entries);
    }
}
