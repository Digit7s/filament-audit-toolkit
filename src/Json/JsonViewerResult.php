<?php

namespace Digit7s\FilamentAuditToolkit\Json;

final readonly class JsonViewerResult
{
    /**
     * @param  array<string, mixed>  $root
     * @param  array<int, array{type: string, text: string}>  $tokens
     */
    public function __construct(
        public array $root,
        public string $json,
        public array $tokens,
        public bool $truncated,
    ) {}
}
