<?php

namespace Digit7s\FilamentAuditToolkit\Diff;

final readonly class DiffEntry
{
    public function __construct(
        public string $path,
        public string $type,
        public bool $beforeExists,
        public bool $afterExists,
        public string $beforeDisplay,
        public string $afterDisplay,
        public string $valueType,
        public string $label,
        public bool $truncated = false,
    ) {}

    /**
     * @return array{path: string, type: string, before_exists: bool, after_exists: bool, before: string, after: string, value_type: string, label: string, truncated: bool}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'type' => $this->type,
            'before_exists' => $this->beforeExists,
            'after_exists' => $this->afterExists,
            'before' => $this->beforeDisplay,
            'after' => $this->afterDisplay,
            'value_type' => $this->valueType,
            'label' => $this->label,
            'truncated' => $this->truncated,
        ];
    }
}
